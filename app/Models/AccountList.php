<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountList extends Model
{
    /** @use HasFactory<\Database\Factories\AccountListFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name'];

    public function accounts()
    {
        return $this->belongsToMany(Account::class, 'account_list_account');
    }

    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = auth()->id();
    }
}
