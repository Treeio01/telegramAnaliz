<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    /** @use HasFactory<\Database\Factories\VendorFactory> */
    use HasFactory;

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'del_user'
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function aliveAccountsCount()
    {
        return $this->accounts()->where('spamblock', 'free')->count();
    }

    public function totalAccountsCount()
    {
        return $this->accounts()->count();
    }
}
