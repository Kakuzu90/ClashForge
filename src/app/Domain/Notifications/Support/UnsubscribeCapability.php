<?php

namespace App\Domain\Notifications\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\URL;

final readonly class UnsubscribeCapability
{
    public function __construct(public string $ulid, public string $hash, public string $url) {}

    public static function emailHash(User $user): string
    {
        return hash_hmac('sha256', strtolower($user->email), (string) config('app.key'));
    }

    public static function urlFor(User $user): string
    {
        return URL::temporarySignedRoute('notifications.unsubscribe.show', Date::now()->addDays((int) config('platform.notifications.unsubscribe_link_days')), [
            'ulid' => $user->ulid, 'hash' => self::emailHash($user),
        ]);
    }

    public function allows(User $user): bool
    {
        $request = Request::create($this->url);

        return $user->deleted_at === null
            && $user->ulid === strtolower($this->ulid)
            && hash_equals(self::emailHash($user), $this->hash)
            && $request->url() === route('notifications.unsubscribe.show', ['ulid' => $this->ulid, 'hash' => $this->hash])
            && URL::hasValidSignature($request);
    }
}
