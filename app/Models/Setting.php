<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'value',
    ];

    /**
     * Get a setting by group.key or (group, key).
     */
    public static function get(string $keyOrComposite, mixed $default = null): mixed
    {
        if (str_contains($keyOrComposite, '.')) {
            [$group, $key] = explode('.', $keyOrComposite, 2);
        } else {
            $group = 'general';
            $key = $keyOrComposite;
        }

        $setting = static::where('group', $group)->where('key', $key)->first();
        if (!$setting || $setting->value === null) {
            return $default;
        }

        $decoded = json_decode($setting->value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $setting->value;
    }

    /**
     * Set a setting by group.key or (group, key).
     */
    public static function set(string $groupOrComposite, mixed $keyOrValue, mixed $value = null): static
    {
        if ($value === null && str_contains($groupOrComposite, '.')) {
            [$group, $key] = explode('.', $groupOrComposite, 2);
            $val = $keyOrValue;
        } else {
            $group = $groupOrComposite;
            $key = (string) $keyOrValue;
            $val = $value;
        }

        $encodedVal = is_array($val) || is_object($val) ? json_encode($val) : (string) $val;

        return static::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $encodedVal]
        );
    }
}
