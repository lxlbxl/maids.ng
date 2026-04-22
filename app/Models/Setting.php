<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'is_encrypted', 'group'];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get setting value (decrypted if necessary)
     */
    public static function get($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) return $default;

        return $setting->is_encrypted ? \Illuminate\Support\Facades\Crypt::decryptString($setting->value) : $setting->value;
    }

    /**
     * Set setting value (encrypted if necessary)
     */
    public static function set($key, $value, $group = 'general', $shouldEncrypt = false)
    {
        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $shouldEncrypt ? \Illuminate\Support\Facades\Crypt::encryptString($value) : $value,
                'is_encrypted' => $shouldEncrypt,
                'group' => $group
            ]
        );
    }
}
