<?php

namespace App\Services\Nfces;

use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Vendas\Venda;
use App\Repositories\Nfces\NfceRepository;
use App\Services\Empresas\EmpresaService;
use App\Services\Nfes\NfeBuilderService;
use App\Services\Nfes\NfeSenderService;
use App\Services\Nfes\NfeVinculoFiscal;
use App\Support\MensagemPublica;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use NFePHP\Common\Keys;
use NFePHP\DA\NFe\Danfce;
use RuntimeException;
use Throwable;

class NfceService
{
    public function __construct(
        protected EmpresaService $empresaService,
        protected NfceRepository $repository,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    public function validarAntesDaVenda(Empresa $empresa, array $itens, float $total, ?string $documento): void
    {
        $mensagem = $this->mensagemBloqueio($empresa, $itens, $total, $documento);
        if ($mensagem === null) {
            return;
        }

        $campo = str_contains($mensagem, 'CPF') || str_contains($mensagem, 'CNPJ')
            ? 'nfce_documento'
            : 'nfce';

        throw ValidationException::withMessages([$campo => $mensagem]);
    }

    /**
     * @return array{ok: bool, situacao: ?string, mensagem: string, numero: ?string, chave: ?string, danfe_url: ?string}
     */
    public function emitir(Venda $venda, ?string $documento = null, float $valorRecebido = 0): array
    {
        $this->autorizarVenda($venda);
        $empresa = $this->repository->empresa((int) $venda->empresa_id);
        $venda->loadMissing(['itens.produto.perfilFiscal', 'cliente']);

        if ($venda->situacao !== 'confirmada') {
            return $this->resposta(false, null, 'Apenas vendas confirmadas podem emitir NFC-e.');
        }

        $documento = $this->resolverDocumento($venda, $documento);
        $bloqueio = $this->mensagemBloqueio(
            $empresa,
            $this->itensDaVenda($venda),
            (float) $venda->total,
            $documento,
        );
        if ($bloqueio !== null) {
            return $this->resposta(false, null, $bloqueio);
        }

        try {
            $builder = new NfceBuilderService($empresa);
        } catch (RuntimeException $e) {
            return $this->resposta(false, null, MensagemPublica::de($e));
        }

        if ($sefaz = $this->mensagemSefazIndisponivel($empresa, $builder)) {
            return $this->resposta(false, null, $sefaz);
        }

        $nfe = DB::transaction(function () use ($venda, $empresa) {
            $venda = $this->repository->travarVenda((int) $venda->id);
            $ambiente = (int) ($empresa->nfe_ambiente ?: 2);
            $serie = $this->serie($empresa);

            $nfes = $this->repository->daVenda((int) $venda->empresa_id, (int) $venda->id);

            if ($nfes->contains(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFE && $nota->situacao === 'autorizada')) {
                return 'Esta venda já possui NF-e autorizada. A NFC-e não foi emitida.';
            }

            if ($nfes->contains(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFCE && $nota->situacao === 'autorizada')) {
                return 'Esta venda já possui NFC-e autorizada.';
            }

            if ($nfes->where('situacao', 'pendente')->isNotEmpty()) {
                return 'Esta venda já possui uma nota pendente. Consulte a SEFAZ antes de emitir de novo. Nenhum número novo foi usado.';
            }

            if ($this->repository->existePendente((int) $empresa->id, $ambiente, $serie)) {
                return 'Há uma NFC-e pendente de confirmação na SEFAZ. Consulte essa nota antes de emitir outra. Nenhum número novo foi usado.';
            }

            $ultima = $nfes->first(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFCE);
            if ($ultima
                && $ultima->situacao === 'rejeitada'
                && (int) $ultima->ambiente === $ambiente
                && $ultima->serie === $serie
                && $ultima->codigo_status !== '539') {
                return $this->repository->reabrirRejeitada($ultima);
            }

            $numero = $this->empresaService->proximoNumeronNfe($empresa, Nfe::MODELO_NFCE);

            return $this->repository->criarPendente(
                (int) $empresa->id,
                (int) $venda->id,
                $numero,
                $serie,
                $ambiente,
            );
        });

        if (is_string($nfe)) {
            return $this->resposta(false, null, $nfe);
        }

        $venda = $this->repository->recarregarVenda($venda);
        $this->gravarCodigosResolvidos($venda, $empresa);

        try {
            $signedXml = $builder->buildAndSignNfce(
                $this->repository->carregarItensFiscais($venda),
                (string) $nfe->numero,
                $valorRecebido,
                $documento,
            );
            $nfe = $this->repository->atualizar($nfe, [
                'xml_assinado' => $signedXml,
                'chave_acesso' => $this->extrairChave($signedXml),
            ]);
        } catch (RuntimeException $e) {
            $nfe = $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'motivo_status' => mb_substr(MensagemPublica::de($e), 0, 255),
            ]);

            return $this->resposta(false, $nfe, MensagemPublica::de($e));
        } catch (Throwable $e) {
            Log::error('Falha ao montar NFC-e', [
                'venda_id' => $venda->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);
            $nfe = $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'motivo_status' => 'Não foi possível montar a NFC-e.',
            ]);

            return $this->resposta(false, $nfe, 'Não foi possível montar a NFC-e. Verifique os dados fiscais da empresa e dos produtos.');
        }

        $resultado = (new NfeSenderService($empresa, $builder->tools()))->enviar($signedXml, $empresa);
        $nfe = $this->gravarResultado($nfe, $resultado);

        return $this->resposta(
            $nfe->situacao === 'autorizada',
            $nfe,
            $this->mensagemResultado($nfe),
        );
    }

    public function danfe(Venda $venda)
    {
        $this->autorizarVenda($venda);

        $nfe = $this->repository->autorizadaDaVenda((int) $venda->empresa_id, (int) $venda->id);
        if ($nfe === null) {
            abort(404);
        }

        return $this->pdf($nfe);
    }

    private function pdf(Nfe $nfe)
    {
        try {
            $pdf = (new Danfce($nfe->xml_autorizado))->render();
        } catch (Throwable $e) {
            Log::error('Falha ao gerar DANFE', [
                'nfe_id' => $nfe->id,
                'exception' => $e->getMessage(),
            ]);

            return back()->with('error', 'Não foi possível gerar o DANFE desta NF-e.');
        }

        $nome = 'danfe_'.$nfe->numero.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    private function mensagemBloqueio(Empresa $empresa, array $itens, float $total, ?string $documento): ?string
    {
        $documento = preg_replace('/\D/', '', (string) $documento) ?? '';
        $documento = $documento === '' ? null : $documento;

        if ($documento !== null && ! NfceBuilderService::documentoValido($documento)) {
            return 'CPF ou CNPJ do consumidor é inválido.';
        }

        if (strtoupper((string) $empresa->uf) === 'PB' && $total + 0.001 >= 500 && $documento === null) {
            return 'Na Paraíba, a NFC-e de R$ 500,00 ou mais precisa do CPF ou CNPJ do consumidor.';
        }

        try {
            $builder = new NfceBuilderService($empresa);
            $builder->validarCadastroNfce($itens);
        } catch (RuntimeException $e) {
            return MensagemPublica::de($e);
        }

        return null;
    }

    private function mensagemSefazIndisponivel(Empresa $empresa, NfceBuilderService $builder): ?string
    {
        if (app()->bound('nfe.sefaz.status')) {
            return app('nfe.sefaz.status')($empresa);
        }

        try {
            return (new NfeSenderService($empresa, $builder->tools()))->motivoSeIndisponivel();
        } catch (Throwable) {
            return 'Não foi possível consultar o status da SEFAZ. A emissão foi bloqueada e nenhum número foi usado.';
        }
    }

    private function gravarResultado(Nfe $nfe, array $resultado): Nfe
    {
        $status = $resultado['status'] ?? 'erro';
        $motivo = (string) ($resultado['motivo'] ?? 'erro desconhecido');
        $codigo = $resultado['codigo'] ?? null;

        if ($status === 'autorizada' || $status === 'denegada') {
            return $this->repository->atualizar($nfe, [
                'situacao' => $status,
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'protocolo' => $resultado['protocolo'] ?? null,
                'xml_autorizado' => $resultado['xml_autorizado'] ?? null,
                'codigo_status' => $codigo ?? '100',
                'motivo_status' => mb_substr($status === 'autorizada' && ! isset($resultado['motivo']) ? 'Autorizado o uso da NFC-e' : $motivo, 0, 255),
                'data_autorizacao' => now(),
            ]);
        }

        if ($status === 'rejeitada' || $status === 'nao_encontrada') {
            return $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'codigo_status' => $codigo,
                'motivo_status' => mb_substr($motivo, 0, 255),
            ]);
        }

        return $this->repository->atualizar($nfe, [
            'codigo_status' => $codigo,
            'motivo_status' => mb_substr($motivo, 0, 255),
            'recibo' => $resultado['recibo'] ?? $nfe->recibo,
        ]);
    }

    private function mensagemResultado(Nfe $nfe): string
    {
        $motivo = trim((string) $nfe->motivo_status);
        $codigo = $nfe->codigo_status ? ' Código '.$nfe->codigo_status.'.' : '';

        return match ($nfe->situacao) {
            'autorizada' => 'NFC-e autorizada.',
            'rejeitada' => 'NFC-e rejeitada: '.($motivo !== '' ? $motivo : 'a SEFAZ não informou o motivo.').$codigo,
            'denegada' => 'NFC-e denegada: '.$motivo.$codigo.' O número foi consumido.',
            default => 'A NFC-e ficou pendente de confirmação na SEFAZ. Consulte a nota antes de emitir outra. '.$motivo,
        };
    }

    /**
     * @return array{ok: bool, situacao: ?string, mensagem: string, numero: ?string, chave: ?string, danfe_url: ?string}
     */
    private function resposta(bool $ok, ?Nfe $nfe, string $mensagem): array
    {
        return [
            'ok' => $ok,
            'situacao' => $nfe?->situacao,
            'mensagem' => $mensagem,
            'numero' => $nfe?->numero,
            'chave' => $nfe?->chave_acesso,
            'danfe_url' => $ok && $nfe?->venda_id ? route('venda.nfce.danfe', $nfe->venda_id) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itensDaVenda(Venda $venda): array
    {
        return $venda->itens->map(fn ($item) => [
            'produto_id' => $item->produto_id,
            'produto_nome' => $item->produto_nome,
            'ncm' => $item->ncm,
            'ean' => $item->ean,
            'cfop' => $item->cfop,
            'csosn' => $item->csosn,
            'cst_pis' => $item->cst_pis,
            'cst_cofins' => $item->cst_cofins,
        ])->all();
    }

    private function resolverDocumento(Venda $venda, ?string $informado): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $informado) ?? '';
        if ($digitos === '' && $venda->cliente) {
            $cnpj = preg_replace('/\D/', '', (string) $venda->cliente->cnpj) ?? '';
            $cpf = preg_replace('/\D/', '', (string) $venda->cliente->cpf) ?? '';
            $digitos = strlen($cnpj) === 14 ? $cnpj : $cpf;
        }

        return $digitos === '' ? null : $digitos;
    }

    private function gravarCodigosResolvidos(Venda $venda, Empresa $empresa): void
    {
        $venda = $this->repository->carregarItensFiscais($venda);
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa)->usarVenda($venda);

        foreach ($venda->itens as $item) {
            $produto = $item->produto;
            $dados = [];
            $campos = [
                'cfop' => $vinculo->cfop($item, $produto)['valor'],
                'csosn' => $vinculo->csosn($item, $produto)['valor'],
                'cst_pis' => $vinculo->cst($item, $produto, 'cst_pis', 'cst_pis', 'cst_pis', 'nfe_cst_pis_padrao')['valor'],
                'cst_cofins' => $vinculo->cst($item, $produto, 'cst_cofins', 'cst_cofins', 'cst_cofins', 'nfe_cst_cofins_padrao')['valor'],
            ];

            foreach ($campos as $campo => $valor) {
                if (blank($item->{$campo}) && $valor !== '') {
                    $dados[$campo] = $valor;
                }
            }

            if ($dados !== []) {
                $this->repository->atualizarItem($item, $dados);
            }
        }

        if (blank($venda->nfe_tpag)) {
            $this->repository->gravarTpag($venda, NfeBuilderService::tPag((string) $venda->forma_pagamento));
        }
    }

    private function extrairChave(string $xml): ?string
    {
        try {
            $chave = Keys::extractAccessKey($xml);

            return $chave !== '' ? $chave : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function autorizarVenda(Venda $venda): void
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    private function serie(Empresa $empresa): string
    {
        return str_pad(preg_replace('/\D/', '', (string) ($empresa->nfce_serie ?: '001')) ?: '1', 3, '0', STR_PAD_LEFT);
    }
}
