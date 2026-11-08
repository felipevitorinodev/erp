<?php

namespace App\Comandos;

use App\Models\Clientes\Cliente;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Base das rotinas: conversa (perguntas e respostas), formatação e mensagens comuns. */
abstract class Rotina implements Comando
{
    /** Resposta que dispensa o dado perguntado ("sem cliente", "nenhum"). */
    private const SEM = '/^(?:sem|nenhum|nenhuma|nao tem|nao sei|deixa sem|pula|pular)\b/u';

    /** Rótulo no começo de um trecho corrigido ("cpf 123", "vence 31/02"): fica na frase, só o valor muda. */
    private const ROTULO = '/^(?:cpf|cnpj|documento|doc|telefone|tel|fone|celular|cel|whatsapp|whats|zap|(?:com\s+)?(?:vencimento|vencendo|vence|venc\.?)(?:\s+(?:em|no dia|dia|para|na|no))?)\s*:?\s+/u';

    public function reconhecerNatural(string $texto): ?string
    {
        return null;
    }

    /**
     * Com termo, a resposta troca aquele trecho da frase (o nome ambíguo, a data errada);
     * "sem ..." tira o trecho. Sem termo, a resposta entra no fim com o conector do campo ("vence amanhã").
     */
    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        $resposta = Leitor::limpar($resposta);

        if ($termo !== null && $termo !== '') {
            if (preg_match(self::SEM, Leitor::normalizar($resposta))) {
                return $this->semTermo($argumento, $termo);
            }

            if (preg_match(self::ROTULO, Leitor::normalizar($termo), $rotulo) && ! preg_match(self::ROTULO, Leitor::normalizar($resposta).' ')) {
                $resposta = mb_substr($termo, 0, mb_strlen($rotulo[0])).$resposta;
            }

            $trocado = preg_replace_callback(
                '/(?<![\p{L}\p{N}#])'.preg_quote($termo, '/').'(?![\p{L}\p{N}])/iu',
                fn () => $resposta,
                $argumento,
                1,
                $trocas,
            );
            if ($trocas > 0) {
                return Leitor::limpar((string) $trocado);
            }
        }

        return Leitor::limpar($argumento.' '.($this->conectores()[$campo] ?? '').' '.$resposta);
    }

    /** @return array<string, string> palavra que liga cada resposta à frase ("vence" antes da data) */
    protected function conectores(): array
    {
        return [];
    }

    protected function usuario(): User
    {
        return auth()->user();
    }

    protected function empresaId(): int
    {
        return (int) $this->usuario()->empresa_id;
    }

    protected function localizador(): Localizador
    {
        return new Localizador($this->empresaId());
    }

    protected static function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected static function data(CarbonInterface $data): string
    {
        return $data->format('d/m/Y');
    }

    protected static function quantidade(float $quantidade): string
    {
        return rtrim(rtrim(number_format($quantidade, 3, ',', '.'), '0'), ',');
    }

    /** @param list<string|array{rotulo: string, enviar: string}> $opcoes */
    protected function perguntar(string $texto, string $campo, array $opcoes = [], ?string $termo = null): Resposta
    {
        return Resposta::pergunta($texto, $campo, $opcoes, $termo);
    }

    /** Erros do formulário da tela, no máximo dois, numa frase só. */
    protected function erroDeValidacao(ValidationException $excecao): Resposta
    {
        $mensagens = array_values(array_unique(Arr::flatten($excecao->errors())));

        return Resposta::erro(implode(' ', array_slice($mensagens, 0, 2)));
    }

    /** Dado digitado errado (data, CPF): pergunta o certo e troca só aquele trecho. */
    protected function corrigirEntrada(EntradaInvalida $excecao, string $pergunta, array $opcoes = []): Resposta
    {
        return $excecao->trecho === null
            ? Resposta::erro($excecao->getMessage())
            : $this->perguntar($excecao->getMessage().' '.$pergunta, 'corrigir', $opcoes, $excecao->trecho);
    }

    /**
     * Quando a busca não achou exatamente um: pergunta qual (vários) ou sugere nomes parecidos (nenhum).
     * Null quando achou.
     *
     * @param  string  $oque  "o cliente", "o produto"...
     * @param  list<array{rotulo: string, enviar: string}>  $extras  botões a mais ("Sem cliente", "Cadastrar")
     */
    protected function problemaNaBusca(Achado $achado, string $oque, string $campo, array $extras = []): ?Resposta
    {
        if ($achado->encontrou()) {
            return null;
        }

        $termo = $achado->termo;

        if (! $achado->nenhum()) {
            return $this->perguntar('Achei mais de um “'.$termo.'”. Qual deles?', $campo, $this->opcoesDe($achado->opcoes), $termo);
        }

        if ($achado->sugestoes->isNotEmpty()) {
            return $this->perguntar(
                'Não encontrei '.$oque.' “'.$termo.'”. Seria algum destes?',
                $campo,
                [...$this->opcoesDe($achado->sugestoes), ...$extras],
                $termo,
            );
        }

        return $this->perguntar('Não encontrei '.$oque.' “'.$termo.'”. Qual é o nome certo?', $campo, $extras, $termo);
    }

    /**
     * Cada registro vira um botão que envia "#id" (o nome pode se repetir; o id não).
     *
     * @param  Collection<int, Model>  $registros
     * @return list<array{rotulo: string, enviar: string}>
     */
    protected function opcoesDe(Collection $registros): array
    {
        return $registros->map(fn (Model $r) => Resposta::opcao($this->rotuloDaOpcao($r), '#'.$r->getKey()))->values()->all();
    }

    protected function rotuloDaOpcao(Model $registro): string
    {
        return match (true) {
            $registro instanceof Produto => $registro->nome.' · '.self::moeda((float) $registro->preco_venda),
            $registro instanceof Cliente && filled($registro->cidade) => $registro->nome.' · '.$registro->cidade,
            default => (string) $registro->nome,
        };
    }

    /** Tira o termo e o rótulo antes dele ("do cliente Mria", "para Mria", "cpf 123"). */
    private function semTermo(string $argumento, string $termo): string
    {
        $leitor = new Leitor($argumento);
        $leitor->extrair(
            '/(?:\b(?:do|da|de|ao|a|o|para|pra|pro)\s+)?(?:\b(?:cliente|fornecedor)\s+)?(?<![\p{L}\p{N}#])'
            .preg_quote(Leitor::normalizar($termo), '/').'(?![\p{L}\p{N}])/u'
        );

        return $leitor->resto();
    }
}
