<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null)
    {
        try {
            $row = static::where('key', $key)->first();
            return $row ? $row->value : $default;
        } catch (\Illuminate\Database\QueryException $e) {
            return $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    public static function set(string $key, $value): bool
    {
        try {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
            return true;
        } catch (\Illuminate\Database\QueryException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
