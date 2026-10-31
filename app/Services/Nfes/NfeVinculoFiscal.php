<?php

namespace App\Services\Nfes;

use App\Models\Empresas\Empresa;
use App\Models\PerfisFiscais\PerfilFiscal;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use App\Repositories\PerfisFiscais\PerfilFiscalRepository;
use Illuminate\Support\Collection;

/**
 * Ordem fixa, da mais específica para a geral:
 * 1. exceção gravada no item
 * 2. campo do produto
 * 3. perfil fiscal ligado ao produto e ativo
 * 4. perfil ativo que casa com o contexto (quanto mais campos preenchidos, maior a prioridade; empate: o mais antigo)
 * 5. padrão da empresa
 * 6. valor de reserva já usado hoje (5102 / 400 / 07)
 *
 * Campo vazio de operação ou UF casa com qualquer valor.
 * Perfil sem NCM, sem operação e sem UF não entra sozinho: só vale se estiver ligado ao produto.
 * Tipo de destinatário (indIEDest) não entra na escolha até o contador confirmar a regra.
 * Um campo preenchido no produto continua vencendo o perfil. Nota já autorizada não passa por aqui.
 */
class NfeVinculoFiscal
{
    /**
     * @param  Collection<int, PerfilFiscal>  $perfis
     * @param  array{operacao: ?string, uf_destino: ?string, tipo_destinatario: ?string}  $contexto
     */
    public function __construct(
        private readonly Empresa $empresa,
        private readonly Collection $perfis,
        private array $contexto = [
            'operacao' => null,
            'uf_destino' => null,
            'tipo_destinatario' => null,
        ],
    ) {}

    public static function daEmpresa(Empresa $empresa): self
    {
        $perfis = app(PerfilFiscalRepository::class)->ativosDaEmpresa((int) $empresa->id);

        return new self($empresa, $perfis);
    }

    public function usarVenda(Venda $venda): self
    {
        $cliente = $venda->cliente;

        $this->contexto = [
            'operacao' => self::operacaoDaNatureza($venda->natureza_operacao),
            'uf_destino' => self::ufDestino($cliente, $this->empresa->uf),
            'tipo_destinatario' => null,
        ];

        return $this;
    }

    /**
     * @param  array{operacao?: ?string, uf_destino?: ?string, tipo_destinatario?: ?string}  $contexto
     */
    public function usarContexto(array $contexto): self
    {
        $this->contexto = [
            'operacao' => self::vazio($contexto['operacao'] ?? null),
            'uf_destino' => self::vazio(isset($contexto['uf_destino']) ? strtoupper((string) $contexto['uf_destino']) : null),
            'tipo_destinatario' => null,
        ];

        return $this;
    }

    public static function operacaoDaNatureza(?string $natureza): string
    {
        $texto = mb_strtolower(trim((string) $natureza));

        return str_contains($texto, 'devolu') ? 'devolucao' : 'venda';
    }

    public static function ufDestino(?object $cliente, ?string $ufEmpresa): string
    {
        $uf = strtoupper(trim((string) ($cliente->estado ?? '')));

        if (strlen($uf) === 2) {
            return $uf;
        }

        return strtoupper(trim((string) $ufEmpresa));
    }

    /**
     * @return array{valor: string, origem: string, motivo: string}
     */
    public function cfop(object $item, ?Produto $produto): array
    {
        return $this->escolherCampo($item, $produto, 'cfop', 'cfop_padrao', 'cfop', 'nfe_cfop_padrao', '5102');
    }

    /**
     * @return array{valor: string, origem: string, motivo: string}
     */
    public function csosn(object $item, ?Produto $produto): array
    {
        return $this->escolherCampo($item, $produto, 'csosn', 'cst_icms', 'csosn', 'nfe_csosn_padrao', '400');
    }

    /**
     * @return array{valor: string, origem: string, motivo: string}
     */
    public function cst(object $item, ?Produto $produto, string $campoItem, string $campoProduto, string $campoPerfil, string $campoEmpresa): array
    {
        $escolha = $this->escolherCampo($item, $produto, $campoItem, $campoProduto, $campoPerfil, $campoEmpresa, '07');
        $digitos = preg_replace('/\D/', '', $escolha['valor']) ?? '';

        return [
            'valor' => str_pad(substr($digitos, 0, 2), 2, '0', STR_PAD_LEFT),
            'origem' => $escolha['origem'],
            'motivo' => $escolha['motivo'],
        ];
    }

    public function descrever(?PerfilFiscal $perfil): string
    {
        if (! $perfil) {
            return '';
        }

        $partes = [$perfil->nome];
        if ($perfil->operacao === 'venda') {
            $partes[] = 'venda';
        } elseif ($perfil->operacao === 'devolucao') {
            $partes[] = 'devolução';
        }
        if ($perfil->uf_destino) {
            $partes[] = 'destino '.$perfil->uf_destino;
        }
        if ($perfil->tipo_destinatario === 'contribuinte') {
            $partes[] = 'contribuinte';
        } elseif ($perfil->tipo_destinatario === 'nao_contribuinte') {
            $partes[] = 'não contribuinte';
        } elseif ($perfil->tipo_destinatario === 'exterior') {
            $partes[] = 'exterior';
        }
        if ($perfil->ncm) {
            $partes[] = 'NCM '.$perfil->ncm;
        }

        return 'Perfil '.implode(', ', $partes);
    }

    public static function gtinValido(string $digitos): bool
    {
        $tamanho = strlen($digitos);
        if (! in_array($tamanho, [8, 12, 13, 14], true)) {
            return false;
        }

        $corpo = substr($digitos, 0, -1);
        $informado = (int) substr($digitos, -1);
        $soma = 0;
        $pesoTres = true;

        for ($i = strlen($corpo) - 1; $i >= 0; $i--) {
            $soma += (int) $corpo[$i] * ($pesoTres ? 3 : 1);
            $pesoTres = ! $pesoTres;
        }

        $calculado = (10 - ($soma % 10)) % 10;

        return $calculado === $informado;
    }

    /**
     * @return array{valor: string, origem: string, motivo: string}
     */
    private function escolherCampo(object $item, ?Produto $produto, string $campoItem, string $campoProduto, string $campoPerfil, string $campoEmpresa, string $reserva): array
    {
        $doItem = trim((string) ($item->{$campoItem} ?? ''));
        if ($doItem !== '') {
            return ['valor' => $doItem, 'origem' => 'item', 'motivo' => 'Informado no item'];
        }

        $doProduto = trim((string) ($produto?->{$campoProduto} ?? ''));
        if ($doProduto !== '') {
            return ['valor' => $doProduto, 'origem' => 'produto', 'motivo' => 'Cadastro do produto'];
        }

        $ligado = $this->perfilLigado($produto);
        $valorLigado = trim((string) ($ligado?->{$campoPerfil} ?? ''));
        if ($valorLigado !== '') {
            return ['valor' => $valorLigado, 'origem' => 'perfil', 'motivo' => $this->descrever($ligado)];
        }

        $regra = $this->perfilAutomatico($item, $produto);
        $valorRegra = trim((string) ($regra?->{$campoPerfil} ?? ''));
        if ($valorRegra !== '') {
            return ['valor' => $valorRegra, 'origem' => 'regra', 'motivo' => $this->descrever($regra)];
        }

        $daEmpresa = trim((string) ($this->empresa->{$campoEmpresa} ?? ''));
        if ($daEmpresa !== '') {
            return ['valor' => $daEmpresa, 'origem' => 'empresa', 'motivo' => 'Padrão da empresa'];
        }

        return ['valor' => $reserva, 'origem' => 'reserva', 'motivo' => 'Reserva '.$reserva];
    }

    private function perfilLigado(?Produto $produto): ?PerfilFiscal
    {
        $perfil = $produto?->perfilFiscal;

        if (! $perfil || ! $perfil->ativo) {
            return null;
        }

        return $perfil;
    }

    private function perfilAutomatico(object $item, ?Produto $produto): ?PerfilFiscal
    {
        $ncm = preg_replace('/\D/', '', (string) ($item->ncm ?? $produto?->ncm)) ?? '';
        $melhor = null;
        $melhorScore = -1;

        foreach ($this->perfis as $perfil) {
            if ($produto?->perfil_fiscal_id && (int) $perfil->id === (int) $produto->perfil_fiscal_id) {
                continue;
            }

            $score = $this->pontuacao($perfil, $ncm);
            if ($score === null || $score < 1 || $score < $melhorScore) {
                continue;
            }

            if ($score === $melhorScore && $melhor !== null) {
                continue;
            }

            $melhor = $perfil;
            $melhorScore = $score;
        }

        return $melhor;
    }

    private function pontuacao(PerfilFiscal $perfil, string $ncm): ?int
    {
        $pontos = 0;

        if (filled($perfil->ncm)) {
            $ncmPerfil = preg_replace('/\D/', '', (string) $perfil->ncm) ?? '';
            if ($ncm === '' || $ncmPerfil !== $ncm) {
                return null;
            }
            $pontos++;
        }

        if (filled($perfil->operacao)) {
            if ($this->contexto['operacao'] === null || $perfil->operacao !== $this->contexto['operacao']) {
                return null;
            }
            $pontos++;
        }

        if (filled($perfil->uf_destino)) {
            $uf = strtoupper((string) $this->contexto['uf_destino']);
            if ($uf === '' || strtoupper((string) $perfil->uf_destino) !== $uf) {
                return null;
            }
            $pontos++;
        }

        return $pontos;
    }

    private static function vazio(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
