<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function all_values(): array
    {
        return Cache::rememberForever('settings.all', function () {
            try {
                return static::query()->pluck('value', 'key')->all();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    public static function defaultFor(string $key)
    {
        foreach (config('ticket.groups') as $group) {
            if (isset($group['fields'][$key])) {
                return $group['fields'][$key]['default'] ?? null;
            }
        }
        return null;
    }

    public static function get(string $key, $default = null)
    {
        $all = static::all_values();
        if (array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '') {
            return $all[$key];
        }
        return $default ?? static::defaultFor($key);
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
