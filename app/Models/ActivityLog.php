<?php

namespace App\Models;

use App\Support\UserAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Registro de atividades dos membros: quem fez o quê, quando, de qual IP e aparelho.
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'action', 'description', 'subject_type', 'subject_id',
        'properties', 'ip', 'user_agent', 'created_at',
    ];

    /** Grupos de ação (prefixo do campo action), para o filtro da página. */
    public const GROUPS = [
        'auth' => 'Acessos',
        'video' => 'Vídeos',
        'member' => 'Usuários',
        'finance' => 'Valores',
        'settings' => 'Configurações',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?User $user = null): self
    {
        $request = request();
        $user ??= $request->user();

        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? ($properties['email'] ?? null),
            'action' => $action,
            'description' => Str::limit($description, 497),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'created_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): string
    {
        return Str::before($this->action, '.');
    }

    public function groupLabel(): string
    {
        // "client": registros antigos, de quando existiam os links por cliente.
        return in_array($this->action, ['auth.failed', 'finance.failed'], true)
            ? 'Senha errada'
            : (self::GROUPS[$this->group()] ?? ['client' => 'Clientes'][$this->group()] ?? 'Outros');
    }

    public function badgeClasses(): string
    {
        return match (true) {
            in_array($this->action, ['auth.failed', 'finance.failed'], true), str_ends_with($this->action, '.deleted') => 'bg-brand-600/15 text-brand-300',
            $this->group() === 'auth' => 'bg-sky-500/15 text-sky-300',
            $this->group() === 'video' => 'bg-violet-500/15 text-violet-300',
            $this->group() === 'client' => 'bg-emerald-500/15 text-emerald-300',
            $this->group() === 'member' => 'bg-amber-500/15 text-amber-300',
            $this->group() === 'finance' => 'bg-teal-500/15 text-teal-300',
            default => 'bg-ink-700 text-zinc-300',
        };
    }

    public function deviceLabel(): ?string
    {
        return UserAgent::label($this->user_agent);
    }
}
