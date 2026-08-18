<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutPage extends Model
{
    protected $table = 'about_page';

    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'story',
        'mission',
        'email',
        'phone',
        'address',
    ];

    /**
     * Always return the single row, creating defaults if missing.
     */
    public static function instance(): static
    {
        return static::firstOrCreate(['id' => 1], [
            'hero_title'    => 'About KidsFlairr',
            'hero_subtitle' => 'Where little dreams come to play!',
        ]);
    }
}
