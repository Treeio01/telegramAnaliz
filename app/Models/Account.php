<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory;

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vendor_id',
        'upload_id',
        'phone',
        'geo',
        'session_created_at',
        'last_connect_at',
        'spamblock',
        'has_profile_pic',
        'stats_spam_count',
        'stats_invites_count',
        'is_premium',
        'price',
        'type',
        
    ];

    /**
     * Атрибуты, которые должны быть приведены к типам.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'has_profile_pic' => 'boolean',
        'is_premium' => 'boolean',
        'session_created_at' => 'datetime',
        'last_connect_at' => 'datetime',
    ];

    /**
     * Получить поставщика, связанного с аккаунтом.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Получить загрузку, связанную с аккаунтом.
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(AccountList::class, 'account_list_account');
    }
}
