<?php

namespace App\Domain\Users\Models;

use App\Models\User;
use App\Support\Casts\AsStringList;
use Carbon\CarbonImmutable;
use Database\Factories\Users\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's public presentation (specs/07 `profiles`), 1:1 with `users`. Internal to Users.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $display_name
 * @property string|null $bio
 * @property int|null $avatar_media_id
 * @property string|null $country_code
 * @property list<string> $languages
 * @property string|null $timezone
 * @property array<string, string> $socials
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(ProfileFactory::class)]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * The owner and the avatar are set by ProfileService, never from a form.
     *
     * @var list<string>
     */
    protected $fillable = [
        'display_name',
        'bio',
        'country_code',
        'languages',
        'timezone',
        'socials',
    ];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'socials' => '{}',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'languages' => AsStringList::class,
            'socials' => 'array',
        ];
    }
}
