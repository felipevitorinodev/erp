<?php

namespace App\Providers;

use App\Models\CategoriasFinanceiras\CategoriaFinanceira;
use App\Models\Clientes\Cliente;
use App\Models\Comissoes\Comissao;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Estoque\EntradaEstoque;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Funcionarios\Funcionario;
use App\Models\Grupos\Grupo;
use App\Models\Orcamentos\Orcamento;
use App\Models\Produtos\Produto;
use App\Models\UnidadesMedida\UnidadeMedida;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Paginator::defaultView('vendor.pagination.visys');
        Paginator::defaultSimpleView('vendor.pagination.visys');

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->registrarBindingsPorEmpresa();
    }

    /**
     * Resolve parâmetros de rota apenas dentro da empresa do usuário autenticado.
     */
    private function registrarBindingsPorEmpresa(): void
    {
        $porEmpresa = function (string $model) {
            return function ($value) use ($model) {
                $empresaId = auth()->user()->empresa_id ?? 0;

                return $model::where('empresa_id', $empresaId)->findOrFail($value);
            };
        };

        Route::bind('venda', $porEmpresa(Venda::class));
        Route::bind('orcamento', $porEmpresa(Orcamento::class));
        Route::bind('entradaEstoque', $porEmpresa(EntradaEstoque::class));
        Route::bind('contaReceber', $porEmpresa(ContaReceber::class));
        Route::bind('contaPagar', $porEmpresa(ContaPagar::class));
        Route::bind('comissao', $porEmpresa(Comissao::class));
        Route::bind('cliente', $porEmpresa(Cliente::class));
        Route::bind('fornecedor', $porEmpresa(Fornecedor::class));
        Route::bind('funcionario', $porEmpresa(Funcionario::class));
        Route::bind('produto', $porEmpresa(Produto::class));
        Route::bind('grupo', $porEmpresa(Grupo::class));
        Route::bind('unidadeMedida', $porEmpresa(UnidadeMedida::class));
        Route::bind('formaPagamento', $porEmpresa(FormaPagamento::class));
        Route::bind('categoriaFinanceira', $porEmpresa(CategoriaFinanceira::class));

        Route::bind('usuario', function ($value) {
            $query = User::query();

            if ((auth()->user()->perfil ?? '') !== 'admin') {
                $query->where('empresa_id', auth()->user()->empresa_id ?? 0);
            }

            return $query->findOrFail($value);
        });

        Route::bind('empresa', function ($value) {
            if ((auth()->user()->perfil ?? '') !== 'admin') {
                abort(404);
            }

            return Empresa::findOrFail($value);
        });
    }
}
