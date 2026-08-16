<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ad extends Model
{
    /** @use HasFactory<\Database\Factories\AdFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'title',
        'description',
        'price',
        'condition',
        'shipping',
        'platform',
        'status',
        'last_online_at',
        'prompt_text',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_online_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Ad $ad) {
            // Auto-generate UUID for new records
            if (! $ad->id) {
                $ad->id = Str::uuid()->toString();
            }
        });

        static::updating(function (Ad $ad) {
            // Auto-set last_online_at when status changes to "Online"
            if ($ad->isDirty('status') && $ad->status === 'Online') {
                $ad->last_online_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AdImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(AdImage::class)->orderBy('created_at');
    }
}
