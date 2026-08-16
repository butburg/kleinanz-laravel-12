<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Throwable;

class User extends Authenticatable implements MustVerifyEmail
{
    public bool $pendingRegistrationVerification = false;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'openai_api_key',
        'use_test_mode',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'is_admin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'openai_api_key' => 'encrypted',
            'use_test_mode' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Ad, $this>
     */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }

    /**
     * @return HasManyThrough<AdImage, Ad, $this>
     */
    public function images(): HasManyThrough
    {
        return $this->hasManyThrough(AdImage::class, Ad::class);
    }

    public function isAdmin(): bool
    {
        $adminMail = config('app.admin_mail');

        return is_string($adminMail)
            && $adminMail !== ''
            && Str::lower($this->email) === Str::lower($adminMail);
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->isAdmin();
    }

    /**
     * @return HasMany<Appendix, $this>
     */
    public function appendices(): HasMany
    {
        return $this->hasMany(Appendix::class);
    }

    /**
     * Send verification mail and remove registrations when delivery fails.
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            parent::sendEmailVerificationNotification();
            $this->pendingRegistrationVerification = false;
        } catch (Throwable $exception) {
            if ($this->pendingRegistrationVerification) {
                $this->delete();
            }

            throw $exception;
        }
    }
}
