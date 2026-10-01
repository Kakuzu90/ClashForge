<?php

namespace App\Domain\Notifications\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Notifications\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property array<string, array{in_app?: bool, email?: bool}> $channel_prefs
 * @property bool $non_security_email_enabled
 * @property string $digest_frequency
 * @property CarbonImmutable|null $updated_at
 */
#[UseFactory(NotificationPreferenceFactory::class)]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    protected $table = 'notification_preferences';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public const CREATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['channel_prefs', 'non_security_email_enabled'];

    /** @var array<string, string|bool> */
    protected $attributes = ['channel_prefs' => '{}', 'non_security_email_enabled' => true, 'digest_frequency' => 'none'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'channel_prefs' => 'array', 'non_security_email_enabled' => 'boolean', 'updated_at' => 'immutable_datetime'];
    }
}
