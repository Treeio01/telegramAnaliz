<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempAccount extends Model
{

    protected $fillable = [
        'temp_vendor_id',
        'phone',
        'geo',
        'price',
        'spamblock',
        'type',
        'session_created_date',
        'last_connect_date',
        'stats_invites_count',
        'upload_id',
    ];



    public function tempVendor()
    {
        return $this->belongsTo(TempVendor::class);
    }
}