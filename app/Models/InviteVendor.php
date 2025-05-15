<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InviteVendor extends Model
{
    /** @use HasFactory<\Database\Factories\InviteVendorFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function inviteAccounts()
    {
        return $this->hasMany(InviteAccount::class);
    }

    public function aliveInviteAccountsCount()
    {
        return $this->inviteAccounts()->where('spamblock', 'free')->count();
    }

    public function totalInviteAccountsCount()
    {
        return $this->inviteAccounts()->count();
    }
}
