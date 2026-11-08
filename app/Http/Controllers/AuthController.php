<?php

namespace App\Http\Controllers;

use App\Models\Usuarios\User;
use App\Support\RecuperacaoSenha;
use App\Support\SessaoLogin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect('/');
        }

        if ($request->cookie(SessaoLogin::MARCADOR) === '1') {
            SessaoLogin::esquecer();
            $request->session()->now('warning', 'Sua sessão expirou. Entre novamente para continuar.');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');
        $chaveLimite = 'login|'.Str::lower((string) $request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chaveLimite, 5)) {
            return back()->withErrors([
                'login' => 'Muitas tentativas. Aguarde '.RateLimiter::availableIn($chaveLimite).' segundos e tente novamente.',
            ])->withInput($request->only('email'));
        }

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($chaveLimite);

            if (! Auth::user()->ativo) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                SessaoLogin::esquecer();

                return back()->withErrors([
                    'login' => 'Usuário inativo. Entre em contato com o administrador.',
                ])->withInput($request->only('email'));
            }

            $request->session()->regenerate();
            SessaoLogin::marcar();

            return SessaoLogin::redirecionar();
        }

        RateLimiter::hit($chaveLimite, 60);

        return back()->withErrors([
            'login' => 'E-mail ou senha inválidos.',
        ])->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        SessaoLogin::esquecer();

        return redirect()->route('login');
    }

    public function showForgotPassword()
    {
        if (! RecuperacaoSenha::disponivel()) {
            return redirect()->route('login')->with('warning', RecuperacaoSenha::MENSAGEM_INDISPONIVEL);
        }

        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        if (! RecuperacaoSenha::disponivel()) {
            return redirect()->route('login')->with('warning', RecuperacaoSenha::MENSAGEM_INDISPONIVEL);
        }

        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if ($user && $user->ativo) {
            Password::sendResetLink($request->only('email'));
        }

        return back()->with('success', 'Se o e-mail estiver cadastrado e ativo, você receberá o link para redefinir a senha.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ], [], [
            'email' => 'e-mail',
            'password' => 'senha',
        ]);

        $user = User::where('email', $request->email)->first();
        $token = (string) $request->token;

        if ($user && ! $user->ativo && Password::broker()->tokenExists($user, $token)) {
            return back()->withErrors([
                'email' => 'Usuário inativo. Entre em contato com o administrador.',
            ])->withInput($request->only('email'));
        }

        $sessaoAtual = $request->session()->getId();

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($sessaoAtual) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $sessaoAtual)
                    ->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => 'Este token de redefinição de senha é inválido.',
            ])->withInput($request->only('email'));
        }

        if ($user && (int) Auth::id() === (int) $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            SessaoLogin::esquecer();
        }

        return redirect()->route('login')->with('success', 'Senha redefinida com sucesso. Faça login.');
    }
}
