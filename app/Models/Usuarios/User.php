<?php

namespace App\Models\Usuarios;

use App\Models\Empresas\Empresa;
use App\Support\CatalogoPermissoes;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'empresa_id', 'perfil', 'ativo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use SoftDeletes;

    /** @var list<string>|null */
    protected ?array $chavesPermissao = null;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'ativo'             => 'boolean',
        ];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function isAdmin(): bool
    {
        return $this->perfil === 'admin';
    }

    public function ehVendedor(): bool
    {
        return in_array($this->perfil, ['operador', 'vendedor'], true);
    }

    public function pode(string $permissao): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $chaves = $this->chavesPermissao();
        if ($permissao === 'clientes.balcao' && in_array('clientes', $chaves, true)) {
            return true;
        }

        return in_array($permissao, $chaves, true);
    }

    /**
     * @return list<string>
     */
    public function chavesPermissao(): array
    {
        if ($this->chavesPermissao !== null) {
            return $this->chavesPermissao;
        }

        if ($this->isAdmin()) {
            return $this->chavesPermissao = CatalogoPermissoes::chaves();
        }

        $slug = $this->perfil === 'operador' ? 'vendedor' : (string) $this->perfil;
        $perfil = Perfil::query()
            ->where('empresa_id', $this->empresa_id)
            ->where('slug', $slug)
            ->first();

        $chaves = is_array($perfil?->permissoes) ? $perfil->permissoes : CatalogoPermissoes::padrao($slug);

        return $this->chavesPermissao = array_values($chaves);
    }

    public function nomeDoPerfil(): string
    {
        $slug = $this->perfil === 'operador' ? 'vendedor' : (string) $this->perfil;
        $nome = Perfil::query()
            ->where('empresa_id', $this->empresa_id)
            ->where('slug', $slug)
            ->value('nome');

        return $nome ?: match ($slug) {
            'admin' => 'Administrador',
            'financeiro' => 'Financeiro',
            'vendedor' => 'Vendedor',
            default => ucfirst($slug),
        };
    }
}
