<?php

namespace App\Services\Nfses;

use App\Models\Empresas\Empresa;
use App\Models\Fiscal\Municipio;
use App\Models\Fiscal\NfseClassificacao;
use App\Models\Fiscal\NfseIndicadorOperacao;
use App\Models\Fiscal\NfseNbs;
use App\Models\Fiscal\NfseServico;
use Carbon\CarbonInterface;

/**
 * Regras de validação da DPS (Anexo I do leiaute da NFS-e Nacional, produção restrita de 20/10/2026)
 * conferidas antes de reservar número e transmitir. Cada mensagem cita o código que a Sefin devolveria.
 *
 * Esta versão emite DPS pelo próprio prestador (tpEmit = 1), serviço prestado no Brasil, tributação do ISSQN
 * normal (tribISSQN = 1) ou não incidência para o item 99 (tribISSQN = 4), sem obra, evento, imóvel,
 * comércio exterior, dedução, benefício municipal ou tributação federal destacada.
 */
class NfseRegras
{
    public const OP_NAO_OPTANTE = 1;

    public const OP_MEI = 2;

    public const OP_ME_EPP = 3;

    public const ALIQUOTA_MAXIMA = 5.0;

    /** Obs. da regra E0621/E0628: com retenção e apuração pelo SN, a alíquota mínima aceita é 1,8%. */
    public const ALIQUOTA_MINIMA_SN_RETIDO = 1.8;

    public const SERIE_MAXIMA = 49999;

    /** Regime especial de tributação municipal (TSRegEspTrib). */
    public const REGIMES_ESPECIAIS = [
        0 => 'Nenhum',
        1 => 'Ato cooperado (cooperativa)',
        2 => 'Estimativa',
        3 => 'Microempresa municipal',
        4 => 'Notário ou registrador',
        5 => 'Profissional autônomo',
        6 => 'Sociedade de profissionais',
        9 => 'Outros',
    ];

    /** Regime de apuração dos tributos pelo Simples Nacional (TSRegimeApuracaoSimpNac). */
    public const REGIMES_APURACAO_SN = [
        1 => 'Tributos federais e ISSQN pelo Simples Nacional',
        2 => 'Federais pelo Simples Nacional e ISSQN fora dele, pela alíquota do município',
        3 => 'Federais e ISSQN fora do Simples Nacional',
    ];

    /** Subitens que exigem o grupo de obra (E0370). */
    public const SERVICOS_COM_OBRA = ['070201', '070202', '070401', '070501', '070502', '070601', '070602', '070701', '070801', '071701', '071901', '141403', '141404'];

    /** Itens que exigem o tipo de operação com ente governamental (E0903). */
    public const ITENS_COM_TP_OPER = ['2505', '1509', '1712', '1005'];

    public static function opSimpNac(Empresa $empresa): int
    {
        return match ((int) $empresa->crt) {
            4 => self::OP_MEI,
            1, 2 => self::OP_ME_EPP,
            default => self::OP_NAO_OPTANTE,
        };
    }

    public static function regApTribSN(Empresa $empresa): ?int
    {
        if (self::opSimpNac($empresa) !== self::OP_ME_EPP) {
            return null;
        }

        $configurado = (int) $empresa->nfse_regime_apuracao_sn;
        if (isset(self::REGIMES_APURACAO_SN[$configurado])) {
            return $configurado;
        }

        // CRT 2 = Simples Nacional com excesso de sublimite: o ISSQN sai do Simples.
        return (int) $empresa->crt === 2 ? 2 : 1;
    }

    public static function regEspTrib(Empresa $empresa): int
    {
        $regime = (int) $empresa->nfse_regime_especial;

        return isset(self::REGIMES_ESPECIAIS[$regime]) ? $regime : 0;
    }

    /** Convênio sem consulta conta como ativo: a Sefin só aceita DPS de município conveniado (E0037). */
    public static function convenioAtivo(Empresa $empresa): bool
    {
        return $empresa->nfse_convenio_situacao !== 'inativo';
    }

    public static function tribIssqn(string $cTribNac): int
    {
        return str_starts_with($cTribNac, '99') ? 4 : 1;
    }

    /**
     * Quando a alíquota do ISSQN (pAliq) vai na DPS. E0595 a E0640.
     */
    public static function aliquotaObrigatoria(int $opSimpNac, ?int $regApTribSN, int $regEspTrib, int $tribIssqn, bool $retido, bool $convenioAtivo): bool
    {
        if ($opSimpNac === self::OP_MEI || $regEspTrib !== 0 || $tribIssqn !== 1) {
            return false;
        }

        if ($opSimpNac === self::OP_NAO_OPTANTE) {
            return ! $convenioAtivo;
        }

        if ($regApTribSN === 1) {
            return $retido;
        }

        return ! $convenioAtivo;
    }

    /**
     * Texto curto para a tela explicar quando a alíquota é pedida.
     */
    public static function explicacaoAliquota(int $opSimpNac, ?int $regApTribSN, bool $convenioAtivo): string
    {
        return match (true) {
            $opSimpNac === self::OP_MEI => 'MEI não informa alíquota de ISSQN.',
            $opSimpNac === self::OP_ME_EPP && $regApTribSN === 1 => 'Só vai na nota quando o tomador retém o ISSQN (mínimo de 1,8%).',
            $convenioAtivo => 'O Sistema Nacional aplica a alíquota cadastrada pelo município.',
            default => 'O convênio do município não está ativo: informe a alíquota.',
        };
    }

    /**
     * @param  array<string, mixed>  $d  dados montados pelo NfseService::dadosDaEmissao()
     * @return list<string>
     */
    public static function validar(array $d): array
    {
        $erros = [];
        $prest = $d['prestador'];
        $serv = $d['servico'];
        $iss = $d['iss'];
        $valores = $d['valores'];

        // Prestador
        if (! in_array(strlen($prest['documento']), [11, 14], true)) {
            $erros[] = 'Informe o CNPJ (ou CPF) da empresa.';
        }
        if (! preg_match('/^\d{7}$/', $prest['cod_ibge'])) {
            $erros[] = 'Informe o código IBGE do município da empresa (7 dígitos).';
        }
        if ($prest['im'] !== '' && mb_strlen($prest['im']) > 15) {
            $erros[] = 'A inscrição municipal da empresa passa de 15 caracteres.';
        }
        if ($prest['op_simp_nac'] === self::OP_MEI && $prest['reg_esp_trib'] !== 0) {
            $erros[] = 'MEI usa regime especial de tributação "Nenhum".';
        }

        // Série e número (E0010)
        if (! preg_match('/^\d{1,5}$/', (string) $d['serie']) || (int) $d['serie'] > self::SERIE_MAXIMA) {
            $erros[] = 'A série da DPS precisa ser um número de 0 a '.self::SERIE_MAXIMA.' (faixa do aplicativo próprio). Ajuste na empresa.';
        }

        // Datas
        $competencia = $d['competencia'];
        $emissao = $d['emissao'];
        if (! $competencia instanceof CarbonInterface) {
            $erros[] = 'Informe a data de competência.';
        } elseif ($emissao instanceof CarbonInterface && $competencia->format('Y-m-d') > $emissao->format('Y-m-d')) {
            $erros[] = 'A competência não pode ser posterior à data de emissão.';
        } elseif ($competencia->format('Y-m-d') < '2026-01-01') {
            $erros[] = 'Competência anterior a 2026 não pode ser emitida no leiaute com IBS/CBS (E0850).';
        }

        // Serviço
        $cTribNac = $serv['c_trib_nac'];
        if (! preg_match('/^\d{6}$/', $cTribNac) || ! NfseServico::query()->where('codigo', $cTribNac)->where('ativo', true)->exists()) {
            $erros[] = 'Escolha um código de tributação nacional da lista de serviços (E0310).';
        } else {
            if (str_starts_with($cTribNac, '9903') || $cTribNac === '990401') {
                $erros[] = 'Os códigos 99.03 e 99.04.01 são recusados pela Sefin (E0309).';
            }
            if (in_array($cTribNac, self::SERVICOS_COM_OBRA, true)) {
                $erros[] = 'Este serviço de construção exige os dados da obra (E0370). Emita pelo Emissor Nacional.';
            }
            if (str_starts_with($cTribNac, '12')) {
                $erros[] = 'Serviços do item 12 (eventos) exigem os dados do evento (E0390). Emita pelo Emissor Nacional.';
            }
            if (in_array(substr($cTribNac, 0, 4), self::ITENS_COM_TP_OPER, true)) {
                $erros[] = 'Este serviço exige o tipo de operação do IBS/CBS (E0903). Emita pelo Emissor Nacional.';
            }
        }

        $cTribMun = $serv['c_trib_mun'];
        if ($cTribMun !== '') {
            if ($prest['op_simp_nac'] === self::OP_MEI) {
                $erros[] = 'MEI não informa código de tributação municipal (E0313). Deixe em branco.';
            } elseif (! preg_match('/^\d{3}$/', $cTribMun)) {
                $erros[] = 'O código de tributação municipal tem 3 dígitos.';
            } elseif ($cTribMun === '000') {
                $erros[] = 'O código de tributação municipal não pode ser 000 (E0315).';
            }
        }

        if (! preg_match('/^\d{9}$/', $serv['nbs']) || ! NfseNbs::query()->where('codigo', $serv['nbs'])->where('ativo', true)->exists()) {
            $erros[] = 'Escolha o código NBS do serviço: é obrigatório com o grupo IBS/CBS (E0322, E0316).';
        }

        $descricao = trim($serv['descricao']);
        if ($descricao === '') {
            $erros[] = 'Descreva o serviço prestado.';
        } elseif (mb_strlen($descricao) > 2000) {
            $erros[] = 'A descrição do serviço passa de 2000 caracteres.';
        }

        if (! preg_match('/^\d{7}$/', $serv['municipio_prestacao']) || ! Municipio::query()->where('codigo', $serv['municipio_prestacao'])->exists()) {
            $erros[] = 'Escolha o município onde o serviço foi prestado.';
        }

        // Valores (E0431)
        if ($valores['servico'] <= 0) {
            $erros[] = 'O valor dos serviços precisa ser maior que zero.';
        }
        if ($valores['desconto'] < 0 || ($valores['desconto'] > 0 && $valores['desconto'] >= $valores['servico'])) {
            $erros[] = 'O desconto precisa ser menor que o valor dos serviços (E0431).';
        }

        // ISSQN
        $retido = $iss['retido'];
        if ($retido && $prest['op_simp_nac'] === self::OP_MEI) {
            $erros[] = 'MEI não tem ISSQN retido pelo tomador.';
        }
        if ($retido && $prest['reg_esp_trib'] !== 0) {
            $erros[] = 'Com regime especial de tributação o ISSQN não é retido.';
        }
        if ($retido && $d['tomador'] === null) {
            $erros[] = 'Para reter o ISSQN, o tomador precisa estar identificado por CPF ou CNPJ (E0204).';
        }
        if ($retido && $iss['trib_issqn'] !== 1) {
            $erros[] = 'Serviço sem incidência de ISSQN não tem retenção.';
        }

        $aliquota = $iss['aliquota'];
        if ($iss['aliquota_obrigatoria']) {
            if ($aliquota === null || $aliquota <= 0) {
                $erros[] = 'Informe a alíquota do ISSQN. '.self::explicacaoAliquota($prest['op_simp_nac'], $prest['reg_ap_trib_sn'], $d['convenio_ativo']);
            } elseif ($aliquota > self::ALIQUOTA_MAXIMA) {
                $erros[] = 'A alíquota do ISSQN não pode passar de 5% (E0595).';
            } elseif ($prest['op_simp_nac'] === self::OP_ME_EPP && $prest['reg_ap_trib_sn'] === 1 && $aliquota < self::ALIQUOTA_MINIMA_SN_RETIDO) {
                $erros[] = 'Com retenção e ISSQN pelo Simples Nacional, a alíquota mínima é 1,8% (E0621).';
            }
        }

        // Total aproximado de tributos (Lei 12.741/2012)
        $tot = $d['tot_trib'];
        if ($tot['tipo'] === 'sn' && $tot['sn'] === null) {
            $erros[] = 'Informe na empresa o percentual aproximado de tributos do Simples Nacional (NFS-e).';
        }
        if ($tot['tipo'] === 'percentual' && ($tot['fed'] === null || $tot['est'] === null || $tot['mun'] === null)) {
            $erros[] = 'Informe na empresa os percentuais aproximados de tributos federais, estaduais e municipais (NFS-e).';
        }

        // Tomador
        $toma = $d['tomador'];
        if ($toma !== null) {
            if ($toma['documento'] === $prest['documento']) {
                $erros[] = 'O tomador não pode ser a própria empresa (E0202).';
            }
            if (! self::documentoValido($toma['tipo'], $toma['documento'])) {
                $erros[] = $toma['tipo'] === 'cnpj'
                    ? 'O CNPJ do cliente é inválido (E0188). Corrija o cadastro.'
                    : 'O CPF do cliente é inválido (E0206). Corrija o cadastro.';
            }
            if ($toma['tipo'] === 'cnpj' && $toma['endereco'] === null) {
                $erros[] = 'Tomador com CNPJ precisa de endereço no cadastro (E0235). '
                    .($toma['pendencia_endereco'] ?? 'Informe CEP, logradouro, bairro, cidade e UF do cliente.');
            }
            if ($toma['im'] !== '' && mb_strlen($toma['im']) > 15) {
                $erros[] = 'A inscrição municipal do cliente passa de 15 caracteres.';
            }
        } elseif ($d['tomador_pendencia'] !== null) {
            $erros[] = $d['tomador_pendencia'];
        }

        // IBS/CBS (E0855, E0958, E0959, E0860, E0901)
        $ibs = $d['ibscbs'];
        $classificacao = preg_match('/^\d{6}$/', $ibs['class_trib'])
            ? NfseClassificacao::query()->where('codigo', $ibs['class_trib'])->where('ativo', true)->first()
            : null;
        if (! $classificacao) {
            $erros[] = 'Escolha a classificação tributária do IBS/CBS (cClassTrib) aceita na NFS-e (E0958).';
        }

        $exigeIndicador = $classificacao?->exige_tributacao || $d['leiaute'] === '20260209';
        if ($ibs['ind_op'] === '') {
            if ($exigeIndicador) {
                $erros[] = 'Escolha o indicador da operação (cIndOp) do IBS/CBS (E0860).';
            }
        } elseif (! NfseIndicadorOperacao::query()->where('codigo', $ibs['ind_op'])->where('ativo', true)->exists()) {
            $erros[] = 'O indicador da operação (cIndOp) não é aceito na prestação de serviços (E0901).';
        } elseif (str_starts_with($ibs['ind_op'], '02')) {
            $erros[] = 'Indicadores de operação com imóvel (02xxxx) exigem os dados do imóvel (E0932). Emita pelo Emissor Nacional.';
        }

        return array_values(array_unique($erros));
    }

    /**
     * Endereço nacional do tomador (cMun pelo nome da cidade e UF). Devolve [endereco|null, pendencia|null].
     *
     * @return array{0: array<string, string>|null, 1: string|null}
     */
    public static function enderecoTomador(object $cliente): array
    {
        $cep = preg_replace('/\D/', '', (string) $cliente->cep) ?? '';
        $logradouro = trim((string) $cliente->logradouro);
        $bairro = trim((string) $cliente->bairro);
        $cidade = trim((string) $cliente->cidade);
        $uf = strtoupper(trim((string) $cliente->estado));

        if ($logradouro === '' && $bairro === '' && $cidade === '' && $cep === '') {
            return [null, null];
        }

        if (strlen($cep) !== 8 || $logradouro === '' || $bairro === '' || $cidade === '' || strlen($uf) !== 2) {
            return [null, 'Complete o endereço do cliente (CEP com 8 dígitos, logradouro, bairro, cidade e UF).'];
        }

        $codigo = self::codigoMunicipio($cidade, $uf);
        if ($codigo === null) {
            return [null, 'Não encontrei o município "'.$cidade.'/'.$uf.'" do cliente na tabela do IBGE. Confira a grafia da cidade no cadastro.'];
        }

        return [[
            'cod_ibge' => $codigo,
            'cep' => $cep,
            'logradouro' => mb_substr($logradouro, 0, 255),
            'numero' => mb_substr(trim((string) $cliente->numero) ?: 'S/N', 0, 60),
            'complemento' => mb_substr(trim((string) $cliente->complemento), 0, 156),
            'bairro' => mb_substr($bairro, 0, 60),
        ], null];
    }

    public static function codigoMunicipio(string $cidade, string $uf): ?string
    {
        $alvo = self::normalizar($cidade);
        if ($alvo === '') {
            return null;
        }

        $municipios = Municipio::query()->where('uf', strtoupper($uf))->get(['codigo', 'nome']);

        foreach ($municipios as $municipio) {
            if (self::normalizar($municipio->nome) === $alvo) {
                return $municipio->codigo;
            }
        }

        return null;
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $semAcento = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        if ($semAcento !== false) {
            $texto = $semAcento;
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $texto));
    }

    /** CPF/CNPJ do cliente para a NFS-e: [tipo, digitos] ou null quando não identifica. */
    public static function documentoCliente(object $cliente): ?array
    {
        $cnpj = preg_replace('/\D/', '', (string) $cliente->cnpj) ?? '';
        if (strlen($cnpj) === 14) {
            return ['cnpj', $cnpj];
        }

        $cpf = preg_replace('/\D/', '', (string) $cliente->cpf) ?? '';
        if (strlen($cpf) === 11) {
            return ['cpf', $cpf];
        }

        return null;
    }

    public static function documentoValido(string $tipo, string $digitos): bool
    {
        if (preg_match('/^(\d)\1+$/', $digitos)) {
            return false;
        }

        $pesos = $tipo === 'cnpj'
            ? [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]]
            : [range(10, 2), range(11, 2)];

        foreach ($pesos as $posicao => $lista) {
            $soma = 0;
            foreach ($lista as $i => $peso) {
                $soma += (int) $digitos[$i] * $peso;
            }
            $resto = $soma % 11;
            $dv = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $digitos[count($lista)] !== $dv) {
                return false;
            }
        }

        return true;
    }

    public static function telefone(?string $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        return strlen($digitos) >= 6 && strlen($digitos) <= 20 ? $digitos : null;
    }

    public static function email(?string $valor): ?string
    {
        $email = trim((string) $valor);

        return $email !== '' && mb_strlen($email) <= 80 && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
