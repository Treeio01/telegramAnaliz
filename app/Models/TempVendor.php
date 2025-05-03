<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempVendor extends Model
{
    /** @use HasFactory<\Database\Factories\TempVendorFactory> */
    use HasFactory;



    protected $fillable = [
        'name',
        'upload_id',
        'del_user',
    ];


    protected $guarded = [];

    public function tempAccounts()
    {
        return $this->hasMany(TempAccount::class);
    }

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }
}
