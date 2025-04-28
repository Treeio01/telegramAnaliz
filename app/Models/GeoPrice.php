<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeoPrice extends Model
{
    /** @use HasFactory<\Database\Factories\GeoPriceFactory> */
    use HasFactory;

    protected $fillable = ['geo', 'price'];

    
}
