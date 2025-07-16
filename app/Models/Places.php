<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Places extends Model
{
    protected $guarded = [];
    protected $casts = [
        'emails' => 'array',
        'social_links' => 'array',
    ];
}
