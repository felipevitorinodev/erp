<?php

namespace App\Services\Estoque;

use App\Http\Requests\Estoque\EntradaEstoqueRequest;
use App\Http\Requests\Estoque\ImportarXmlRequest;
use App\Models\Estoque\EntradaEstoque;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Produtos\Produto;
use App\Repositories\Estoque\EntradaEstoqueRepository;
use App\Services\Estoque\NfeXmlParser;
use App\Traits\ConversorMoeda;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use RuntimeException;

class EntradaEstoqueService
{
    use ConversorMoeda;

    public function __construct(
        protected EntradaEstoqueRepository $repository
    ) {}

    public function index(Request $request)
    {
        $entradas = $this->repository->index($request);

        return view('entrada-estoque.index', [
            'entradas' => $entradas,
            'filtros'  => $request->only(['busca', 'situacao', 'data_inicio', 'data_fim']),
        ]);
    }

    /**
     * Process NF-e XML and return enriched data for form pre-fill.
     *
     * @param string $xmlContent Raw XML content
     * @param int $empresaId Company ID for lookups
     * @return array Parsed and enriched data
     * @throws RuntimeException
     */
    public function processarXml(string $xmlContent, int $empresaId): array
    {
        // 1. Parse the XML
        $parser = new NfeXmlParser();
        $parsed = $parser->parse($xmlContent);

        // 2. Duplicate check (ignora excluídas; libera chave órfã para reimportar)
        EntradaEstoque::onlyTrashed()
            ->where('empresa_id', $empresaId)
            ->where('chave_acesso', $parsed['nota']['chave_acesso'])
            ->update(['chave_acesso' => null, 'xml_importado' => null]);

        $entradaExistente = EntradaEstoque::where('empresa_id', $empresaId)
            ->where('chave_acesso', $parsed['nota']['chave_acesso'])
            ->first();

        if ($entradaExistente) {
            throw new RuntimeException(
                'Esta NF-e já foi importada (Entrada #' . $entradaExistente->numero .
                ' — ' . $entradaExistente->data_entrada->format('d/m/Y') . ').'
            );
        }

        // 3. Fornecedor lookup by CNPJ — cria automaticamente se não existir
        $cnpjEmitente = $parsed['emitente']['cnpj'];
        $fornecedorNome = $parsed['emitente']['razao_social'];
        $fornecedorIe = $parsed['emitente']['ie'] ?? null;

        $fornecedor = null;

        // Busca fornecedor pelo CNPJ
        $fornecedores = Fornecedor::where('empresa_id', $empresaId)
            ->whereNotNull('cnpj')
            ->get();

        foreach ($fornecedores as $f) {
            $cnpjFornecedor = preg_replace('/\D/', '', $f->cnpj);
            if ($cnpjFornecedor === $cnpjEmitente) {
                $fornecedor = $f;
                break;
            }
        }

        // Se não encontrou, cria automaticamente
        if (!$fornecedor && $cnpjEmitente) {
            $fornecedor = Fornecedor::create([
                'empresa_id'         => $empresaId,
                'nome'               => $fornecedorNome,
                'nome_fantasia'      => null,
                'cnpj'               => $this->formatarCnpj($cnpjEmitente),
                'inscricao_estadual' => $fornecedorIe,
                'ativo'              => true,
            ]);
        }

        $parsed['fornecedor_id'] = $fornecedor?->id;
        $parsed['fornecedor_nome'] = $fornecedor?->nome ?? $fornecedorNome;
        $parsed['fornecedor_criado'] = $fornecedor && !$fornecedor->wasRecentlyCreated ? false : true;

        // 4. Produto lookup per item
        foreach ($parsed['itens'] as $key => $item) {
            $produtoId = null;
            $produtoEncontrado = false;

            // First try: by codigo (codigo_produto_fornecedor)
            $produto = Produto::where('empresa_id', $empresaId)
                ->where('codigo', $item['codigo_produto_fornecedor'])
                ->where('ativo', true)
                ->first();

            if (!$produto && !empty($item['ean'])) {
                // Second try: by codigo_barras (EAN)
                $produto = Produto::where('empresa_id', $empresaId)
                    ->where('codigo_barras', $item['ean'])
                    ->where('ativo', true)
                    ->first();
            }

            if ($produto) {
                $produtoId = $produto->id;
                $produtoEncontrado = true;
            }

            $parsed['itens'][$key]['produto_id'] = $produtoId;
            $parsed['itens'][$key]['produto_encontrado'] = $produtoEncontrado;
        }

        // 5. Add raw XML for session storage
        $parsed['_xml_raw'] = $xmlContent;

        return $parsed;
    }

    public function create()
    {
        $dadosXml = session('dados_xml');
        $observacoesXml = null;

        if ($dadosXml) {
            $observacoesXml = sprintf(
                'NF-e nº %s série %s — %s — Chave: %s',
                $dadosXml['nota']['numero'] ?? '',
                $dadosXml['nota']['serie'] ?? '',
                $dadosXml['emitente']['razao_social'] ?? '',
                $dadosXml['nota']['chave_acesso'] ?? ''
            );
        }

        return view('entrada-estoque.create', [
            'dadosXml'       => $dadosXml,
            'observacoesXml' => $observacoesXml,
        ]);
    }

    /**
     * Import XML file and redirect to create form with pre-filled data.
     */
    public function importarXml(ImportarXmlRequest $request)
    {
        try {
            $xmlContent = file_get_contents($request->file('xml_file')->getRealPath());
            $result = $this->processarXml($xmlContent, auth()->user()->empresa_id);

            session(['dados_xml' => $result, 'xml_content' => $result['_xml_raw']]);

            return redirect()->route('entrada-estoque.create')
                ->with('success', 'XML importado com sucesso. Revise os dados antes de salvar.');
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Clear XML session data and redirect to create form.
     */
    public function limparXml()
    {
        session()->forget(['dados_xml', 'xml_content']);

        return redirect()->route('entrada-estoque.create');
    }

    public function store(EntradaEstoqueRequest $request)
    {
        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $empresaId = auth()->user()->empresa_id;
        $fornecedorId = $request->fornecedor_id ?: null;

        // Verificar duplicidade de NF-e antes de salvar
        if (session()->has('dados_xml')) {
            $dadosXml = session('dados_xml');
            $chaveAcesso = $dadosXml['nota']['chave_acesso'] ?? null;
            
            if ($chaveAcesso) {
                EntradaEstoque::onlyTrashed()
                    ->where('empresa_id', $empresaId)
                    ->where('chave_acesso', $chaveAcesso)
                    ->update(['chave_acesso' => null, 'xml_importado' => null]);

                $entradaExistente = EntradaEstoque::where('empresa_id', $empresaId)
                    ->where('chave_acesso', $chaveAcesso)
                    ->first();

                if ($entradaExistente) {
                    session()->forget(['dados_xml', 'xml_content']);
                    
                    return redirect()->route('entrada-estoque.show', $entradaExistente)
                        ->with('error', 'Esta NF-e já foi importada (Entrada #' . $entradaExistente->numero .
                            ' — ' . $entradaExistente->data_entrada->format('d/m/Y') . ').');
                }
            }
        }

        if ($fornecedorId) {
            $fornecedor = Fornecedor::where('id', $fornecedorId)
                ->where('empresa_id', $empresaId)
                ->first();

            if (!$fornecedor) {
                return back()
                    ->withErrors(['fornecedor_id' => 'Fornecedor inválido para esta empresa.'])
                    ->withInput();
            }
        }

        $desconto = $this->parseMoeda($request->desconto);
        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $total = max(0, round($subtotal - $desconto, 2));

        $dados = [
            'empresa_id'    => $empresaId,
            'fornecedor_id' => $fornecedorId,
            'numero'        => trim((string) $request->numero),
            'data_entrada'  => $request->data_entrada,
            'subtotal'      => $subtotal,
            'desconto'      => $desconto,
            'total'         => $total,
            'observacoes'   => $request->observacoes,
            'situacao'      => 'rascunho',
        ];

        // Merge XML fields from session if present
        if (session()->has('dados_xml')) {
            $dadosXml = session('dados_xml');
            $dados['chave_acesso']     = $dadosXml['nota']['chave_acesso'] ?? null;
            $dados['xml_importado']    = session('xml_content');
            $dados['numero_nfe']       = $dadosXml['nota']['numero'] ?? null;
            $dados['serie_nfe']        = $dadosXml['nota']['serie'] ?? null;
            $dados['data_emissao_nfe'] = $dadosXml['nota']['data_emissao'] ?? null;
        }

        try {
            $entrada = $this->repository->store($dados, $itens);
        } catch (UniqueConstraintViolationException $e) {
            // NF-e já foi importada - buscar entrada existente
            session()->forget(['dados_xml', 'xml_content']);
            
            $chaveAcesso = $dados['chave_acesso'] ?? null;
            if ($chaveAcesso) {
                $entradaExistente = EntradaEstoque::where('empresa_id', $empresaId)
                    ->where('chave_acesso', $chaveAcesso)
                    ->first();

                if ($entradaExistente) {
                    return redirect()->route('entrada-estoque.show', $entradaExistente)
                        ->with('error', 'Esta NF-e já foi importada (Entrada #' . $entradaExistente->numero .
                            ' — ' . $entradaExistente->data_entrada->format('d/m/Y') . ').');
                }
            }
            
            return redirect()->route('entrada-estoque.index')
                ->with('error', 'Esta NF-e já foi importada anteriormente.');
        }

        // Clear XML session data after successful save
        session()->forget(['dados_xml', 'xml_content']);

        return redirect()->route('entrada-estoque.show', $entrada)
            ->with('success', 'Entrada de estoque #' . $entrada->numero . ' cadastrada com sucesso.');
    }

    public function show(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);
        $entradaEstoque->load(['fornecedor', 'itens.produto']);

        return view('entrada-estoque.show', ['entrada' => $entradaEstoque]);
    }

    public function edit(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        if ($entradaEstoque->situacao !== 'rascunho') {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', 'Somente entradas em rascunho podem ser editadas.');
        }

        $entradaEstoque->load(['fornecedor', 'itens.produto']);

        return view('entrada-estoque.edit', ['entrada' => $entradaEstoque]);
    }

    public function update(EntradaEstoqueRequest $request, EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        if ($entradaEstoque->situacao !== 'rascunho') {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', 'Somente entradas em rascunho podem ser editadas.');
        }

        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $empresaId = (int) $entradaEstoque->empresa_id;
        $fornecedorId = $request->fornecedor_id ?: null;

        if ($fornecedorId) {
            $fornecedor = Fornecedor::where('id', $fornecedorId)
                ->where('empresa_id', $empresaId)
                ->first();

            if (!$fornecedor) {
                return back()
                    ->withErrors(['fornecedor_id' => 'Fornecedor inválido para esta empresa.'])
                    ->withInput();
            }
        }

        $desconto = $this->parseMoeda($request->desconto);
        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $total = max(0, round($subtotal - $desconto, 2));

        $dados = [
            'fornecedor_id' => $fornecedorId,
            'numero'        => trim((string) $request->numero),
            'data_entrada'  => $request->data_entrada,
            'subtotal'      => $subtotal,
            'desconto'      => $desconto,
            'total'         => $total,
            'observacoes'   => $request->observacoes,
            'situacao'      => 'rascunho',
        ];

        $this->repository->update($entradaEstoque, $dados, $itens);

        return redirect()->route('entrada-estoque.show', $entradaEstoque)
            ->with('success', 'Entrada de estoque #' . $entradaEstoque->numero . ' atualizada com sucesso.');
    }

    public function confirmar(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        if ($entradaEstoque->situacao !== 'rascunho') {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', 'Somente entradas em rascunho podem ser confirmadas.');
        }

        try {
            $this->repository->confirmar($entradaEstoque->load('itens'));
        } catch (\Exception $ex) {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', $ex->getMessage());
        }

        return redirect()->route('entrada-estoque.show', $entradaEstoque)
            ->with('success', 'Entrada #' . $entradaEstoque->numero . ' confirmada. Estoque atualizado e conta a pagar gerada.');
    }

    public function cancelar(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        if ($entradaEstoque->situacao !== 'confirmada') {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', 'Somente entradas confirmadas podem ser canceladas.');
        }

        try {
            $this->repository->cancelar($entradaEstoque->load('itens'));
        } catch (\Exception $ex) {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', $ex->getMessage());
        }

        return redirect()->route('entrada-estoque.show', $entradaEstoque)
            ->with('success', 'Entrada #' . $entradaEstoque->numero . ' cancelada. Estoque e conta a pagar revertidos.');
    }

    public function destroy(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        $numero = $entradaEstoque->numero;
        $eraConfirmada = $entradaEstoque->situacao === 'confirmada';

        try {
            $this->repository->destroy($entradaEstoque->id);
        } catch (\Exception $ex) {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', $ex->getMessage());
        }

        $msg = $eraConfirmada
            ? 'Compra #' . $numero . ' excluída. Estoque e conta a pagar foram revertidos.'
            : 'Compra #' . $numero . ' excluída com sucesso.';

        return redirect()->route('entrada-estoque.index')
            ->with('success', $msg);
    }

    private function autorizarEntrada(EntradaEstoque $entrada): void
    {
        if ((int) $entrada->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    /**
     * Formata CNPJ com pontuação (##.###.###/####-##)
     */
    private function formatarCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);
        
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }

    private function processarItens(array $itens): array
    {
        $resultado = [];
        $empresaId = (int) auth()->user()->empresa_id;

        foreach ($itens as $item) {
            $quantidade = $this->parseQuantidade($item['quantidade'] ?? 0);
            if ($quantidade <= 0) {
                continue;
            }

            $precoUnit = $this->parseMoeda($item['preco_unitario'] ?? 0);
            $descItem  = $this->parseMoeda($item['desconto'] ?? 0);
            $total     = max(0, ($quantidade * $precoUnit) - $descItem);

            // Se tem produto_id, usa o produto existente
            if (!empty($item['produto_id'])) {
                $produto = Produto::find($item['produto_id']);

                if (!$produto || (int) $produto->empresa_id !== $empresaId) {
                    continue;
                }

                // Atualiza preço de custo do produto existente
                $produto->preco_custo = $precoUnit;
                $produto->save();

                $resultado[] = [
                    'produto_id'     => $produto->id,
                    'produto_nome'   => $produto->nome,
                    'produto_codigo' => $produto->codigo,
                    'quantidade'     => $quantidade,
                    'preco_unitario' => $precoUnit,
                    'desconto'       => $descItem,
                    'total'          => $total,
                ];
            } else {
                // Cria novo produto automaticamente
                $nomeProduto = trim($item['produto_nome'] ?? 'Produto sem nome');
                $codigoProduto = trim($item['produto_codigo'] ?? '');
                
                // Gera código automático se não informado
                if (empty($codigoProduto)) {
                    $ultimoCodigo = Produto::where('empresa_id', $empresaId)
                        ->where('codigo', 'like', 'AUTO%')
                        ->orderByRaw("CAST(SUBSTRING(codigo, 5) AS UNSIGNED) DESC")
                        ->value('codigo');
                    
                    $proximoNum = 1;
                    if ($ultimoCodigo) {
                        $proximoNum = ((int) substr($ultimoCodigo, 4)) + 1;
                    }
                    $codigoProduto = 'AUTO' . str_pad($proximoNum, 5, '0', STR_PAD_LEFT);
                }

                // Verifica se já existe produto com esse código
                $produtoExistente = Produto::where('empresa_id', $empresaId)
                    ->where('codigo', $codigoProduto)
                    ->first();

                if ($produtoExistente) {
                    // Atualiza o produto existente
                    $produtoExistente->preco_custo = $precoUnit;
                    $produtoExistente->save();
                    $produto = $produtoExistente;
                } else {
                    // Cria novo produto
                    $produto = Produto::create([
                        'empresa_id'       => $empresaId,
                        'codigo'           => $codigoProduto,
                        'nome'             => $nomeProduto,
                        'preco_custo'      => $precoUnit,
                        'preco_venda'      => $precoUnit * 1.3, // Margem padrão de 30%
                        'estoque_atual'    => 0, // Será atualizado na confirmação
                        'estoque_minimo'   => 0,
                        'controla_estoque' => true,
                        'tipo'             => 1, // Produto
                        'ativo'            => true,
                    ]);
                }

                $resultado[] = [
                    'produto_id'     => $produto->id,
                    'produto_nome'   => $produto->nome,
                    'produto_codigo' => $produto->codigo,
                    'quantidade'     => $quantidade,
                    'preco_unitario' => $precoUnit,
                    'desconto'       => $descItem,
                    'total'          => $total,
                ];
            }
        }

        return $resultado;
    }
}
