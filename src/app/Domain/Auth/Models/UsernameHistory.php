<?php

namespace App\Domain\Auth\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Auth\UsernameHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $user_id
 * @property string $username
 * @property CarbonImmutable $released_at
 * @property bool $reserved_forever
 */
#[UseFactory(UsernameHistoryFactory::class)]
class UsernameHistory extends Model
{
    /** @use HasFactory<UsernameHistoryFactory> */
    use HasFactory;

    protected $table = 'username_history';

    /** @var list<string> */
    protected $fillable = ['user_id', 'username', 'released_at', 'reserved_forever'];

    protected function casts(): array
    {
        return ['released_at' => 'immutable_datetime', 'reserved_forever' => 'boolean'];
    }
}
