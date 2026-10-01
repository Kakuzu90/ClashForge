<?php

namespace App\Domain\Users\Models;

use App\Domain\Users\Enums\ProfileVisibility;
use App\Models\User;
use Database\Factories\Users\PrivacySettingsFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's privacy choices (specs/07 `privacy_settings`), 1:1 with `users`. Internal to Users;
 * other modules read it through PrivacyPolicyResolver.
 *
 * @property int $user_id
 * @property ProfileVisibility $profile_visibility
 * @property bool $show_coc_accounts
 * @property bool $show_clan
 * @property bool $show_activity
 * @property bool $allow_recruitment_contact
 * @property bool $allow_marketplace_contact
 * @property bool $searchable
 */
#[UseFactory(PrivacySettingsFactory::class)]
class PrivacySettings extends Model
{
    /** @use HasFactory<PrivacySettingsFactory> */
    use HasFactory;

    protected $table = 'privacy_settings';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * The owner is set by ProfileService, never from a form.
     *
     * @var list<string>
     */
    protected $fillable = [
        'profile_visibility',
        'show_coc_accounts',
        'show_clan',
        'show_activity',
        'allow_recruitment_contact',
        'allow_marketplace_contact',
        'searchable',
    ];

    /**
     * Matches the column defaults (owner decision, P1-04), so a new model is complete before it is
     * refreshed.
     *
     * @var array<string, string|bool>
     */
    protected $attributes = [
        'profile_visibility' => 'public',
        'show_coc_accounts' => true,
        'show_clan' => true,
        'show_activity' => true,
        'allow_recruitment_contact' => true,
        'allow_marketplace_contact' => false,
        'searchable' => true,
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
            'profile_visibility' => ProfileVisibility::class,
            'show_coc_accounts' => 'boolean',
            'show_clan' => 'boolean',
            'show_activity' => 'boolean',
            'allow_recruitment_contact' => 'boolean',
            'allow_marketplace_contact' => 'boolean',
            'searchable' => 'boolean',
        ];
    }
}
