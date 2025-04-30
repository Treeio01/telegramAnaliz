<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoPreset extends Model
{
    protected $fillable = [
        'name',
        'geos'
    ];

    protected $casts = [
        'geos' => 'array'
    ];
}
