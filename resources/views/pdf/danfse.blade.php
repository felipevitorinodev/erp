<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>DANFSe {{ $d['numero'] }}</title>
    {{-- NT 008/2026: A4 retrato, página única, margens de 0,15 a 0,20 cm, borda de 1 pt, linhas de 0,5 pt,
         títulos dos blocos em caixa alta 7 pt com fundo cinza 5%, rótulos 6 pt, conteúdo 7 pt. --}}
    <style>
        @page { margin: 0.18cm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 7pt; color: #000; }
        .pagina { border: 1pt solid #000; position: relative; }
        table { width: 100%; border-collapse: collapse; }
        td { border: 0.5pt solid #000; padding: 1.5pt 3pt; vertical-align: top; }
        .sem-borda td, td.sem-borda { border: none; }
        .bloco { background: #f2f2f2; font-weight: bold; font-size: 7pt; text-transform: uppercase; padding: 2pt 3pt; }
        .rotulo { display: block; font-size: 6pt; font-weight: bold; }
        .rotulo-id { display: block; font-size: 7pt; font-weight: bold; text-transform: uppercase; }
        .valor { display: block; font-size: 7pt; min-height: 8pt; }
        .mono { font-family: Courier, monospace; letter-spacing: 0.3pt; }
        .cinza { background: #f2f2f2; }
        .centro { text-align: center; }
        .direita { text-align: right; }
        .titulo { font-size: 9pt; font-weight: bold; }
        .sem-validade { font-size: 9pt; font-weight: bold; color: #ff0000; }
        .logo { font-size: 15pt; font-weight: bold; letter-spacing: 0.5pt; }
        .municipio { font-size: 8pt; }
        .pequeno { font-size: 6pt; }
        .nao-identificado { font-size: 7pt; font-weight: bold; text-align: center; padding: 3pt; }
        .descricao { white-space: pre-line; min-height: 40pt; }
        .cancelada {
            position: absolute; top: 330pt; left: 0; right: 0; text-align: center;
            font-size: 64pt; font-weight: bold; color: #c00; opacity: 0.25;
        }
    </style>
</head>
<body>
@php
    $moeda = fn ($valor) => $valor === null || $valor === '' ? '-' : 'R$ '.number_format((float) $valor, 2, ',', '.');
    $percentual = fn ($valor) => $valor === null || $valor === '' ? '-' : number_format((float) $valor, 2, ',', '.').'%';
    $texto = fn ($valor) => filled($valor) ? $valor : '-';
@endphp
<div class="pagina">
    @if ($d['cancelada'])
        <div class="cancelada">CANCELADA</div>
    @endif

    {{-- Cabeçalho (2.4.3) --}}
    <table>
        <tr>
            <td style="width: 28%;" class="cinza sem-borda"><span class="logo">NFS-e</span><br><span class="pequeno">Nota Fiscal de Serviço eletrônica</span></td>
            <td style="width: 44%;" class="cinza centro sem-borda">
                <div class="titulo">DANFSe v2.0</div>
                <div class="titulo">Documento Auxiliar da NFS-e</div>
                @if ($d['homologacao'])
                    <div class="sem-validade">NFS-e SEM VALIDADE JURÍDICA</div>
                @endif
            </td>
            <td style="width: 28%;" class="cinza direita sem-borda">
                <div class="municipio">{{ $texto($d['local_emissao']) }}</div>
                <div class="pequeno">Ambiente gerador: {{ $d['ambiente_gerador'] }}</div>
                <div class="pequeno">{{ $d['ambiente_texto'] }}</div>
            </td>
        </tr>
    </table>

    {{-- Identificação (2.1.2) --}}
    <table>
        <tr>
            <td colspan="3"><span class="rotulo-id">Chave de acesso da NFS-e</span><span class="valor mono">{{ $d['chave'] }}</span></td>
            <td rowspan="4" style="width: 21%;" class="centro">
                @if ($d['qrcode'])
                    <img src="{{ $d['qrcode'] }}" style="width: 1.7cm; height: 1.7cm;" alt="QR Code">
                @endif
                <div class="pequeno">A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e</div>
            </td>
        </tr>
        <tr>
            <td><span class="rotulo-id">Número da NFS-e</span><span class="valor">{{ $texto($d['numero']) }}</span></td>
            <td><span class="rotulo-id">Competência da NFS-e</span><span class="valor">{{ $d['competencia'] }}</span></td>
            <td><span class="rotulo-id">Data e hora da emissão da NFS-e</span><span class="valor">{{ $d['emissao_nfse'] }}</span></td>
        </tr>
        <tr>
            <td><span class="rotulo-id">Número da DPS</span><span class="valor">{{ $texto($d['numero_dps']) }}</span></td>
            <td><span class="rotulo-id">Série da DPS</span><span class="valor">{{ $texto($d['serie_dps']) }}</span></td>
            <td><span class="rotulo-id">Data e hora da emissão da DPS</span><span class="valor">{{ $d['emissao_dps'] }}</span></td>
        </tr>
        <tr>
            <td class="cinza"><span class="rotulo-id">Emitente da NFS-e</span><span class="valor">{{ $d['emitente'] }}</span></td>
            <td><span class="rotulo-id">Situação da NFS-e</span><span class="valor">{{ $d['cancelada'] ? 'Cancelada' : 'Normal' }}</span></td>
            <td><span class="rotulo-id">Finalidade</span><span class="valor">{{ $d['finalidade'] }}</span></td>
        </tr>
    </table>

    {{-- Prestador (2.1.3) --}}
    @php $p = $d['prestador']; @endphp
    <table>
        <tr><td colspan="4" class="bloco">Prestador / Fornecedor</td></tr>
        <tr>
            <td style="width: 25%;"><span class="rotulo">CNPJ / CPF / NIF</span><span class="valor">{{ $texto($p['documento'] ?? '') }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Inscrição Municipal</span><span class="valor">{{ $texto($p['im'] ?? '') }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Telefone</span><span class="valor">{{ $texto($p['fone'] ?? '') }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Município / UF</span><span class="valor">{{ $texto($p['municipio'] ?? '') }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="rotulo">Nome / Nome Empresarial</span><span class="valor">{{ $texto($p['nome'] ?? '') }}</span></td>
            <td colspan="2"><span class="rotulo">Código IBGE / CEP</span><span class="valor">{{ $texto($p['cod_ibge'] ?? '') }} / {{ $texto($p['cep'] ?? '') }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="rotulo">Endereço</span><span class="valor">{{ $texto($p['logradouro'] ?? '') }}</span></td>
            <td colspan="2"><span class="rotulo">E-mail</span><span class="valor">{{ $texto($p['email'] ?? '') }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="rotulo">Simples Nacional na Data de Competência</span><span class="valor">{{ $d['simples'] }}</span></td>
            <td colspan="2"><span class="rotulo">Regime de Apuração Tributária pelo SN</span><span class="valor">{{ $d['regime_apuracao'] }}</span></td>
        </tr>
    </table>

    {{-- Tomador (2.1.4) --}}
    @php $t = $d['tomador']; @endphp
    <table>
        <tr><td colspan="4" class="bloco">Tomador / Adquirente da Operação</td></tr>
        @if ($t)
            <tr>
                <td style="width: 25%;"><span class="rotulo">CNPJ / CPF / NIF</span><span class="valor">{{ $texto($t['documento']) }}</span></td>
                <td style="width: 25%;"><span class="rotulo">Inscrição Municipal</span><span class="valor">{{ $texto($t['im']) }}</span></td>
                <td style="width: 25%;"><span class="rotulo">Telefone</span><span class="valor">{{ $texto($t['fone']) }}</span></td>
                <td style="width: 25%;"><span class="rotulo">Município / UF</span><span class="valor">{{ $texto($t['municipio']) }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="rotulo">Nome / Nome Empresarial</span><span class="valor">{{ $texto($t['nome']) }}</span></td>
                <td colspan="2"><span class="rotulo">Código IBGE / CEP</span><span class="valor">{{ $texto($t['cod_ibge']) }} / {{ $texto($t['cep']) }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="rotulo">Endereço</span><span class="valor">{{ $texto($t['logradouro']) }}</span></td>
                <td colspan="2"><span class="rotulo">E-mail</span><span class="valor">{{ $texto($t['email']) }}</span></td>
            </tr>
        @else
            <tr><td colspan="4" class="nao-identificado">TOMADOR/ADQUIRENTE DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e</td></tr>
        @endif
    </table>

    {{-- Destinatário (2.3.2) e intermediário (2.3.1) --}}
    <table>
        <tr><td class="bloco">Destinatário da Operação</td></tr>
        <tr><td class="nao-identificado">O DESTINATÁRIO É O PRÓPRIO TOMADOR/ADQUIRENTE DA OPERAÇÃO</td></tr>
        <tr><td class="bloco">Intermediário da Operação</td></tr>
        <tr><td class="nao-identificado">INTERMEDIÁRIO DA OPERAÇÃO NÃO IDENTIFICADO NA NFS-e</td></tr>
    </table>

    {{-- Serviço (2.1.7) --}}
    @php $s = $d['servico']; @endphp
    <table>
        <tr><td colspan="3" class="bloco">Serviço Prestado</td></tr>
        <tr>
            <td style="width: 33%;"><span class="rotulo">Código de Tributação Nacional / Municipal</span><span class="valor">{{ $texto($s['c_trib_nac']) }} / {{ $texto($s['c_trib_mun']) }}</span></td>
            <td style="width: 33%;"><span class="rotulo">Código da NBS</span><span class="valor">{{ $texto($s['nbs']) }}</span></td>
            <td style="width: 34%;"><span class="rotulo">Local da Prestação / UF / País</span><span class="valor">{{ $texto($s['local']) }} / Brasil</span></td>
        </tr>
        <tr>
            <td colspan="3"><span class="rotulo">Descrição do Código de Tributação Nacional / Municipal</span><span class="valor">{{ $texto($s['x_trib_nac']) }}@if ($s['x_trib_mun']) / {{ $s['x_trib_mun'] }}@endif</span></td>
        </tr>
        <tr>
            <td colspan="3"><span class="rotulo">Descrição do Serviço</span><span class="valor descricao">{{ $texto($s['descricao']) }}</span></td>
        </tr>
    </table>

    {{-- ISSQN (2.1.8) --}}
    @php $i = $d['iss']; @endphp
    <table>
        <tr><td colspan="4" class="bloco">Tributação Municipal (ISSQN)</td></tr>
        @if ($i['sujeito'])
            <tr>
                <td style="width: 25%;"><span class="rotulo">Tipo de Tributação do ISSQN</span><span class="valor">{{ $i['tributacao'] }}</span></td>
                <td style="width: 25%;"><span class="rotulo">Município / UF / País de Incidência do ISSQN</span><span class="valor">{{ $i['incidencia'] }}</span></td>
                <td style="width: 25%;"><span class="rotulo">Regime Especial de Tributação do ISSQN</span><span class="valor">{{ $i['regime_especial'] }}</span></td>
                <td style="width: 25%;"><span class="rotulo">Desconto Incondicionado</span><span class="valor">{{ $moeda($i['desconto_incondicionado']) }}</span></td>
            </tr>
            <tr>
                <td><span class="rotulo">BC ISSQN</span><span class="valor">{{ $moeda($i['base']) }}</span></td>
                <td><span class="rotulo">Alíquota Aplicada</span><span class="valor">{{ $percentual($i['aliquota']) }}</span></td>
                <td><span class="rotulo">Retenção do ISSQN</span><span class="valor">{{ $i['retencao'] }}</span></td>
                <td><span class="rotulo">ISSQN Apurado</span><span class="valor">{{ $moeda($i['valor']) }}</span></td>
            </tr>
        @else
            <tr><td colspan="4" class="nao-identificado">TRIBUTAÇÃO MUNICIPAL (ISSQN) - OPERAÇÃO NÃO SUJEITA AO ISSQN</td></tr>
        @endif
    </table>

    {{-- Federal (2.1.9) --}}
    @php $f = $d['federal']; @endphp
    <table>
        <tr><td colspan="4" class="bloco">Tributação Federal (Exceto CBS)</td></tr>
        <tr>
            <td style="width: 25%;"><span class="rotulo">IRRF</span><span class="valor">{{ $moeda($f['irrf']) }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Contribuição Previdenciária - Retida</span><span class="valor">{{ $moeda($f['cp']) }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Contribuições Sociais - Retidas</span><span class="valor">{{ $moeda($f['csll']) }}</span></td>
            <td style="width: 25%;"><span class="rotulo">PIS / COFINS - Débito Apuração Própria</span><span class="valor">{{ $moeda($f['pis']) }} / {{ $moeda($f['cofins']) }}</span></td>
        </tr>
    </table>

    {{-- IBS/CBS (2.1.10) --}}
    @php $b = $d['ibscbs']; @endphp
    <table>
        <tr><td colspan="4" class="bloco">Tributação IBS / CBS</td></tr>
        <tr>
            <td style="width: 25%;"><span class="rotulo">CST / cClassTrib</span><span class="valor">{{ $texto($b['cst']) }} / {{ $texto($b['class_trib']) }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Indicador de Operação / Município de Incidência</span><span class="valor">{{ $texto($b['ind_op']) }} / {{ $b['incidencia'] }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Base de Cálculo Após Exclusões e Reduções</span><span class="valor">{{ $moeda($b['base']) }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Alíquota do IBS Estadual / IBS Municipal</span><span class="valor">{{ $percentual($b['p_ibs_uf']) }} / {{ $percentual($b['p_ibs_mun']) }}</span></td>
        </tr>
        <tr>
            <td><span class="rotulo">Alíquota Efetiva / Valor do IBS Estadual</span><span class="valor">{{ $percentual($b['p_ibs_uf_efetiva']) }} / {{ $moeda($b['v_ibs_uf']) }}</span></td>
            <td><span class="rotulo">Alíquota Efetiva / Valor do IBS Municipal</span><span class="valor">{{ $percentual($b['p_ibs_mun_efetiva']) }} / {{ $moeda($b['v_ibs_mun']) }}</span></td>
            <td><span class="rotulo">Valor Total Apurado do IBS</span><span class="valor">{{ $moeda($b['v_ibs']) }}</span></td>
            <td><span class="rotulo">Alíquota / Efetiva / Valor da CBS</span><span class="valor">{{ $percentual($b['p_cbs']) }} / {{ $percentual($b['p_cbs_efetiva']) }} / {{ $moeda($b['v_cbs']) }}</span></td>
        </tr>
    </table>

    {{-- Totais (2.1.11) --}}
    @php $v = $d['totais']; @endphp
    <table>
        <tr><td colspan="4" class="bloco">Valor Total da NFS-e</td></tr>
        <tr>
            <td style="width: 25%;"><span class="rotulo">Valor da Operação / Serviço</span><span class="valor">{{ $moeda($v['servico']) }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Desconto Incondicionado</span><span class="valor">{{ $moeda($v['desconto_incondicionado']) }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Desconto Condicionado</span><span class="valor">{{ $moeda($v['desconto_condicionado']) }}</span></td>
            <td style="width: 25%;"><span class="rotulo">Total das Retenções (ISSQN / Federais)</span><span class="valor">{{ $moeda($v['retencoes']) }}</span></td>
        </tr>
        <tr>
            <td><span class="rotulo">Valor Líquido da NFS-e</span><span class="valor">{{ $moeda($v['liquido']) }}</span></td>
            <td><span class="rotulo">Total do IBS / CBS</span><span class="valor">{{ $moeda($v['ibscbs']) }}</span></td>
            <td colspan="2" class="cinza"><span class="rotulo">Valor Líquido da NFS-e + IBS/CBS</span><span class="valor">{{ $moeda($v['liquido_ibscbs']) }}</span></td>
        </tr>
    </table>

    {{-- Informações complementares (2.1.12) --}}
    <table>
        <tr><td class="bloco">Informações Complementares</td></tr>
        <tr>
            <td style="height: 46pt;">
                @if ($d['informacoes'])<span class="valor descricao">{{ $d['informacoes'] }}</span>@endif
                @if ($d['tributos_aproximados'])<span class="valor">Totais aproximados dos tributos (Lei 12.741/2012): {{ $d['tributos_aproximados'] }}</span>@endif
            </td>
        </tr>
    </table>
</div>
</body>
</html>
