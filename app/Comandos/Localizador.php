<?php

namespace App\Comandos;

use App\Models\Clientes\Cliente;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Produtos\Produto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Busca registros da empresa pelo que o usuário digitou: "#15" (botão do assistente), código,
 * nome igual, parte do nome ou palavras soltas. Sem resultado, sugere nomes de grafia parecida.
 */
final class Localizador
{
    private const LIMITE_OPCOES = 5;

    private const LIMITE_CANDIDATOS = 80;

    private const SEMELHANCA_MINIMA = 0.6;

    private const SUGESTOES = 3;

    /** Palavras que não precisam estar no nome ("bateria do iphone" acha "Bateria iPhone XR"). */
    private const LIGACOES = ['de', 'do', 'da', 'dos', 'das', 'com', 'para', 'pra', 'pro', 'e', 'o', 'a', 'os', 'as', 'um', 'uma'];

    public function __construct(private readonly int $empresaId) {}

    public function cliente(string $nome): Achado
    {
        return $this->buscar($this->ativos(Cliente::class), $nome);
    }

    public function fornecedor(string $nome): Achado
    {
        return $this->buscar($this->ativos(Fornecedor::class), $nome);
    }

    /** Código e código de barras exatos têm prioridade sobre o nome. */
    public function produto(string $termo): Achado
    {
        $termo = trim($termo);
        $ativos = $this->ativos(Produto::class);

        $porCodigo = $this->porCodigo($ativos, $termo, 2);

        return $porCodigo->count() === 1
            ? Achado::unico($termo, $porCodigo->first())
            : $this->buscar($ativos, $termo);
    }

    /**
     * Lista para consulta de preço e estoque.
     *
     * @return Collection<int, Produto>
     */
    public function produtos(string $termo, int $limite): Collection
    {
        $termo = trim($termo);
        $ativos = $this->ativos(Produto::class);

        if ($id = self::id($termo)) {
            return (clone $ativos)->whereKey($id)->get();
        }

        $porCodigo = $this->porCodigo($ativos, $termo, $limite);

        return $porCodigo->isNotEmpty() ? $porCodigo : $this->porNome($ativos, $termo, $limite);
    }

    /** @return Collection<int, Produto> */
    public function produtosParecidos(string $termo): Collection
    {
        return $this->parecidos($this->ativos(Produto::class), $termo);
    }

    /**
     * Forma de pagamento citada na frase ("no pix", "cartão de crédito").
     * O nome inteiro na frase vale mais que uma palavra solta; empate vira lista para escolher.
     */
    public function formaPagamento(string $frase): Achado
    {
        $frase = ' '.self::soPalavras($frase).' ';
        $pontos = [];

        $formas = FormaPagamento::query()->where('empresa_id', $this->empresaId)->where('ativo', true)->orderBy('nome')->get();
        foreach ($formas as $forma) {
            $nome = self::soPalavras($forma->nome);
            if (str_contains($frase, ' '.$nome.' ')) {
                $pontos[$forma->id] = 2;

                continue;
            }
            $palavras = array_diff(explode(' ', $nome), ['de', 'do', 'da', 'em', 'a', 'e', 'vista']);
            foreach ($palavras as $palavra) {
                if (strlen($palavra) >= 3 && str_contains($frase, ' '.$palavra.' ')) {
                    $pontos[$forma->id] = 1;
                }
            }
        }

        if ($pontos === []) {
            return Achado::opcoes(trim($frase), collect());
        }

        $melhor = max($pontos);
        $ids = array_keys(array_filter($pontos, fn (int $p) => $p === $melhor));

        return Achado::opcoes(trim($frase), $formas->whereIn('id', $ids)->values());
    }

    /** @param class-string<Model> $modelo */
    private function ativos(string $modelo): Builder
    {
        return $modelo::query()->where('empresa_id', $this->empresaId)->where('ativo', true);
    }

    private function buscar(Builder $consulta, string $termo): Achado
    {
        $termo = trim($termo);

        if ($id = self::id($termo)) {
            $registro = (clone $consulta)->whereKey($id)->first();

            return $registro ? Achado::unico($termo, $registro) : Achado::opcoes($termo, collect());
        }

        $achados = $this->porNome($consulta, $termo, self::LIMITE_OPCOES);

        return $achados->isNotEmpty()
            ? Achado::opcoes($termo, $achados)
            : Achado::opcoes($termo, collect(), $this->parecidos($consulta, $termo));
    }

    /** "#15": registro escolhido num botão do assistente. */
    private static function id(string $termo): ?int
    {
        return preg_match('/^#(\d{1,10})$/', $termo, $m) ? (int) $m[1] : null;
    }

    private function porCodigo(Builder $consulta, string $termo, int $limite): Collection
    {
        if ($termo === '') {
            return collect();
        }

        return (clone $consulta)
            ->where(fn (Builder $q) => $q->where('codigo', $termo)->orWhere('codigo_barras', $termo))
            ->limit($limite)
            ->get();
    }

    /** Nome igual; senão o termo dentro do nome; senão todas as palavras do termo, em qualquer ordem. */
    private function porNome(Builder $consulta, string $termo, int $limite): Collection
    {
        if ($termo === '') {
            return collect();
        }

        $iguais = (clone $consulta)->whereRaw('LOWER(nome) = ?', [mb_strtolower($termo)])->limit($limite)->get();
        if ($iguais->isNotEmpty()) {
            return $iguais;
        }

        $contem = (clone $consulta)->where('nome', 'like', self::like($termo))->orderBy('nome')->limit($limite)->get();
        if ($contem->isNotEmpty()) {
            return $contem;
        }

        $palavras = array_values(array_filter(
            preg_split('/[\s,.\/-]+/u', $termo) ?: [],
            fn (string $p) => mb_strlen($p) >= 2 && ! in_array(Leitor::normalizar($p), self::LIGACOES, true),
        ));
        if (count($palavras) < 2) {
            return collect();
        }

        $todas = clone $consulta;
        foreach ($palavras as $palavra) {
            $todas->where('nome', 'like', self::like($palavra));
        }

        return $todas->orderBy('nome')->limit($limite)->get();
    }

    /**
     * Nomes com grafia parecida: os candidatos vêm pelo começo e pelo fim de cada palavra
     * e são ordenados pela semelhança com o que foi digitado.
     */
    private function parecidos(Builder $consulta, string $termo): Collection
    {
        $pedacos = [];
        foreach (preg_split('/\s+/u', trim($termo)) ?: [] as $palavra) {
            if (mb_strlen($palavra) >= 3) {
                $pedacos[] = mb_substr($palavra, 0, 3);
                $pedacos[] = mb_substr($palavra, -3);
            }
        }

        if ($pedacos === [] || self::id($termo)) {
            return collect();
        }

        $candidatos = (clone $consulta)
            ->where(function (Builder $q) use ($pedacos) {
                foreach (array_unique($pedacos) as $pedaco) {
                    $q->orWhere('nome', 'like', self::like($pedaco));
                }
            })
            ->limit(self::LIMITE_CANDIDATOS)
            ->get();

        return $candidatos
            ->map(fn (Model $registro) => ['registro' => $registro, 'nota' => Vocabulario::semelhanca($termo, (string) $registro->nome)])
            ->filter(fn (array $par) => $par['nota'] >= self::SEMELHANCA_MINIMA)
            ->sortByDesc('nota')
            ->take(self::SUGESTOES)
            ->pluck('registro')
            ->values();
    }

    /** Minúsculo, sem acento e sem pontuação: "no pix, cliente" → "no pix cliente". */
    private static function soPalavras(string $texto): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', Leitor::normalizar($texto)));
    }

    private static function like(string $texto): string
    {
        return '%'.addcslashes($texto, '%_\\').'%';
    }
}
