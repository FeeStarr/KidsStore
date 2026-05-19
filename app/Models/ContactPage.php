<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactPage extends Model
{
    protected $table = 'contact_page';

    protected $fillable = [
        'hero_title',
        'hero_subtitle',
        'intro',
        'email',
        'phone',
        'address',
        'hours',
    ];

    public static function instance(): static
    {
        return static::firstOrCreate(['id' => 1], [
            'hero_title'    => 'Contact Us',
            'hero_subtitle' => 'We\'d love to hear from you!',
        ]);
    }
}
