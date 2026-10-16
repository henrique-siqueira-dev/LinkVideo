<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Configurações do sistema (chave → valor). */
class Setting extends Model
{
    public const DEFAULTS = [
        'finance_minutes' => '15',
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = null;

    protected $fillable = ['key', 'value'];

    /** @var array<string, ?string>|null */
    private static ?array $cache = null;

    public static function get(string $key): ?string
    {
        static::$cache ??= static::query()->pluck('value', 'key')->all();

        return static::$cache[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache = null;
    }

    public static function flushCache(): void
    {
        static::$cache = null;
    }
}
