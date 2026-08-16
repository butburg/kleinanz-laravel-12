<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appendix extends Model
{
    /** @use HasFactory<\Database\Factories\AppendixFactory> */
    use HasFactory;

    protected $table = 'appendices';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'platform',
        'content',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
