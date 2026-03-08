<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdImage extends Model
{
    /** @use HasFactory<\Database\Factories\AdImageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ad_id',
        'large_path',
        'large_thumb_path',
        'cropped_path',
        'cropped_thumb_path',
        'use_cropped',
        'original_name',
        'is_title',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_title' => 'boolean',
            'use_cropped' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Ad, $this>
     */
    public function ad(): BelongsTo
    {
        return $this->belongsTo(Ad::class);
    }
}
