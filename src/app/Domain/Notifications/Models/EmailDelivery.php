<?php

namespace App\Domain\Notifications\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Notifications\EmailDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property string $type
 * @property string $event_key
 * @property CarbonImmutable $sent_at
 */
#[UseFactory(EmailDeliveryFactory::class)]
class EmailDelivery extends Model
{
    /** @use HasFactory<EmailDeliveryFactory> */
    use HasFactory;

    protected $table = 'notification_email_deliveries';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['type', 'event_key', 'sent_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'sent_at' => 'immutable_datetime'];
    }
}
