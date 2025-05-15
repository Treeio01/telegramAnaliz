<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteAccount extends Model
{
    /** @use HasFactory<\Database\Factories\InviteAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'invite_vendor_id',
        'upload_id',
        'phone',
        'geo',
        'session_created_at',
        'last_connect_at',
        'spamblock',
        'stats_invites_count',
        'price',
        'type',
        'del_user',
    ];

    protected $casts = [
        'session_created_at' => 'datetime',
        'last_connect_at' => 'datetime',
    ];

    public function inviteVendor(): BelongsTo
    {
        return $this->belongsTo(InviteVendor::class);
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
