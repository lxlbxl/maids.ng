<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

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
        $settings = self::getAllCached();
        return $settings[$key] ?? $default;
    }

    /**
     * Get all settings mapped by key => decrypted value
     */
    public static function getAllCached()
    {
        try {
            // Attempt to use cache
            return Cache::rememberForever('app_settings_cache', function () {
                return self::fetchAllFromDb();
            });
        } catch (\Throwable $e) {
            // Fallback: If cache table is missing or DB connection fails, fetch directly
            return self::fetchAllFromDb();
        }
    }

    /**
     * Internal helper to fetch all settings directly from DB
     */
    private static function fetchAllFromDb()
    {
        try {
            $settings = self::all();
            $mapped = [];
            foreach ($settings as $setting) {
                try {
                    $mapped[$setting->key] = $setting->is_encrypted 
                        ? Crypt::decryptString($setting->value) 
                        : $setting->value;
                } catch (\Exception $e) {
                    $mapped[$setting->key] = null;
                }
            }
            return $mapped;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Set setting value (encrypted if necessary)
     */
    public static function set($key, $value, $group = 'general', $shouldEncrypt = false)
    {
        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $shouldEncrypt ? Crypt::encryptString($value) : $value,
                'is_encrypted' => $shouldEncrypt,
                'group' => $group
            ]
        );

        Cache::forget('app_settings_cache');

        return $setting;
    }
}
