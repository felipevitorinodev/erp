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
use App\Models\PerfisFiscais\PerfilFiscal;
use App\Models\Produtos\Produto;
use App\Models\UnidadesMedida\UnidadeMedida;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Services\Usuarios\PerfilService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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

            if ($raiz = config('app.url')) {
                URL::forceRootUrl($raiz);
            }
        }

        Event::listen(DiagnosingHealth::class, function () {
            if (! app()->environment('production')) {
                return;
            }

            $banco = DB::selectOne('select database() as nome');

            if (($banco->nome ?? null) !== 'erp_magnolias') {
                throw new RuntimeException('Banco de dados inesperado.');
            }
        });

        $this->registrarBindingsPorEmpresa();
        $this->registrarFiltrosDeData();

        Empresa::created(function (Empresa $empresa) {
            app(PerfilService::class)->garantir($empresa);
        });

        $esquecerPerfis = fn (Perfil $perfil) => User::esquecerPerfisEmCache((int) $perfil->empresa_id);
        Perfil::saved($esquecerPerfis);
        Perfil::deleted($esquecerPerfis);
    }

    /**
     * Filtros por dia que comparam a coluna direto e por isso usam índice (whereDate aplica DATE() em cada linha).
     * O limite final é exclusivo no dia seguinte: vale para colunas date, datetime e para datas gravadas com hora.
     */
    private function registrarFiltrosDeData(): void
    {
        $dia = fn ($valor) => rescue(fn () => Carbon::parse($valor)->startOfDay(), null, false);

        Builder::macro('whereDataDe', function (string $coluna, $data) use ($dia) {
            $inicio = $dia($data);

            return $inicio
                ? $this->where($coluna, '>=', $inicio->toDateString())
                : $this->whereDate($coluna, '>=', $data);
        });

        Builder::macro('whereDataAte', function (string $coluna, $data) use ($dia) {
            $fim = $dia($data);

            return $fim
                ? $this->where($coluna, '<', $fim->addDay()->toDateString())
                : $this->whereDate($coluna, '<=', $data);
        });

        Builder::macro('whereDataEntre', function (string $coluna, $inicio, $fim) {
            return $this->whereDataDe($coluna, $inicio)->whereDataAte($coluna, $fim);
        });
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
        Route::bind('perfilFiscal', $porEmpresa(PerfilFiscal::class));
        Route::bind('categoriaFinanceira', $porEmpresa(CategoriaFinanceira::class));

        Route::bind('perfilAcesso', function ($value) {
            return Perfil::where('empresa_id', auth()->user()->empresa_id ?? 0)->findOrFail($value);
        });

        Route::bind('usuario', function ($value) {
            $query = User::query();

            if ((auth()->user()->perfil ?? '') !== 'admin') {
                $query->where('empresa_id', auth()->user()->empresa_id ?? 0);
            }

            return $query->findOrFail($value);
        });

        Route::bind('empresa', function ($value) {
            $user = auth()->user();

            if (! $user->pode('configuracoes')) {
                abort(404);
            }

            if ((int) $value !== (int) ($user->empresa_id ?? 0)) {
                abort(404);
            }

            return Empresa::findOrFail($value);
        });
    }
}
