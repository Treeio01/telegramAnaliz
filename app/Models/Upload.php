<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Upload extends Model
{
    /** @use HasFactory<\Database\Factories\UploadFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }
}
