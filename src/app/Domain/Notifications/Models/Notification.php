<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\NotificationType;
use Carbon\CarbonImmutable;
use Database\Factories\Notifications\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One in-app notification (specs/07 `notifications`). `type` holds a NotificationType value; an
 * unknown value (a type since removed) stays readable as a string and renders as unavailable.
 *
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property array<string, mixed> $data
 * @property CarbonImmutable|null $read_at
 * @property string|null $group_key
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[UseFactory(NotificationFactory::class)]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'group_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifiable_id' => 'integer',
            'data' => 'array',
            'read_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function notificationType(): ?NotificationType
    {
        return NotificationType::tryFrom($this->type);
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        $params = $this->data['params'] ?? [];

        return is_array($params) ? $params : [];
    }
}
