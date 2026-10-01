<?php

namespace App\Models;

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Notifications\ResetPasswordNotification;
use App\Domain\Auth\Notifications\VerifyEmailNotification;
use App\Domain\Users\Models\Profile;
use App\Support\Auth\HasAccountStanding;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The shared authenticatable identity (specs/07 `users`). It stays in App\Models because every
 * module's policies type-hint it; writes go through Domain/Auth services (specs/19 §1).
 * A verified email gates content writes: publishing, commenting, attaching CoC accounts and
 * uploads (FR-AUTH-4); account and settings writes stay open.
 *
 * @property int $id
 * @property string $ulid
 * @property string $username
 * @property string $email
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $pending_email
 * @property CarbonImmutable|null $pending_email_requested_at
 * @property string|null $password
 * @property string|null $remember_token
 * @property Role $role
 * @property UserStatus $status
 * @property string|null $status_reason
 * @property CarbonImmutable|null $status_expires_at
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $last_login_ip_hash
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $deletion_requested_at
 * @property UserStatus|null $deletion_previous_status
 * @property CarbonImmutable|null $username_changed_at
 */
class User extends Authenticatable implements HasAccountStanding, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, SoftDeletes;

    /**
     * Matches the column defaults, so a model is complete before it is refreshed.
     *
     * @var array<string, string|null>
     */
    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
        'status_reason' => null,
        'status_expires_at' => null,
        'pending_email' => null,
        'pending_email_requested_at' => null,
        'username_changed_at' => null,
        'deleted_at' => null,
        'deletion_requested_at' => null,
        'deletion_previous_status' => null,
    ];

    /**
     * Verification, a pending email change, login tracking, the remember token, role and status
     * are set by Auth services only (specs/11 "Mass assignment").
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'last_login_ip_hash',
        'pending_email',
    ];

    /**
     * The ULID is the public identifier; the bigint id stays internal.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * The stored status, with a passed `status_expires_at` counting as active (specs/23 §7).
     */
    public function effectiveStatus(): UserStatus
    {
        return $this->status->effective($this->status_expires_at);
    }

    public function allowsAccountWrites(): bool
    {
        return $this->effectiveStatus()->allowsAccountWrites();
    }

    public function allowsContentWrites(): bool
    {
        return $this->effectiveStatus()->allowsContentWrites();
    }

    /**
     * Queued on `high`, with our copy (specs/16 §1, specs/20 §1).
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Our signed link (ULID, not the id) and copy, queued on `high` (FR-AUTH-3).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'pending_email_requested_at' => 'immutable_datetime',
            'deletion_requested_at' => 'immutable_datetime',
            'username_changed_at' => 'immutable_datetime',
            'deletion_previous_status' => UserStatus::class,
            'password' => 'hashed',
            'role' => Role::class,
            'status' => UserStatus::class,
            'status_expires_at' => 'immutable_datetime',
        ];
    }
}
