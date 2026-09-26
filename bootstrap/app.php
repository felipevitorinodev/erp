<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/api_interna.php',
            __DIR__.'/../routes/relatorios.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'perfil' => \App\Http\Middleware\CheckPerfil::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Handler global para violação de constraint única
        $exceptions->render(function (UniqueConstraintViolationException $e, Request $request) {
            $message = 'Registro duplicado. Já existe um registro com estes dados no sistema.';
            
            // Identifica qual campo está duplicado baseado na mensagem de erro
            $sqlMessage = $e->getMessage();
            
            if (str_contains($sqlMessage, 'cpf')) {
                $message = 'CPF já cadastrado no sistema.';
            } elseif (str_contains($sqlMessage, 'cnpj')) {
                $message = 'CNPJ já cadastrado no sistema.';
            } elseif (str_contains($sqlMessage, 'email')) {
                $message = 'E-mail já cadastrado no sistema.';
            } elseif (str_contains($sqlMessage, 'codigo')) {
                $message = 'Código já cadastrado no sistema.';
            } elseif (str_contains($sqlMessage, 'chave_acesso') || str_contains($sqlMessage, 'chave')) {
                $message = 'Esta NF-e já foi importada anteriormente.';
            }

            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 422);
            }

            return back()->withInput()->with('error', $message);
        });

        // Handler para erros de query genéricos (foreign key, etc)
        $exceptions->render(function (QueryException $e, Request $request) {
            // Só trata se não for UniqueConstraintViolationException (já tratado acima)
            if ($e instanceof UniqueConstraintViolationException) {
                return null;
            }

            $code = $e->getCode();
            $message = null;

            // 1451 = Cannot delete or update a parent row (FK constraint)
            if ($code == 1451 || str_contains($e->getMessage(), 'foreign key constraint')) {
                $message = 'Não é possível excluir este registro pois ele está sendo utilizado em outras partes do sistema.';
            }
            // 1452 = Cannot add or update a child row (FK constraint)
            elseif ($code == 1452) {
                $message = 'Registro relacionado não encontrado. Verifique os dados e tente novamente.';
            }

            if ($message) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => $message], 422);
                }
                return back()->withInput()->with('error', $message);
            }

            return null; // Deixa o handler padrão tratar outros erros
        });
    })->create();
