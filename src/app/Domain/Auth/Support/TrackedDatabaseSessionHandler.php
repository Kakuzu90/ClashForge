<?php

namespace App\Domain\Auth\Support;

use App\Domain\Auth\Services\SessionService;
use App\Support\Privacy\IpHash;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Date;

/**
 * The database session driver with the columns the session list needs (specs/07 `sessions`): a
 * hashed IP instead of the raw one (specs/11 §5), a device label, the CDN's country and the
 * time the row was created.
 */
class TrackedDatabaseSessionHandler extends DatabaseSessionHandler
{
    public function write($sessionId, $data): bool
    {
        $userId = $this->userId();
        if ($userId === null) {
            return parent::write($sessionId, $data);
        }

        // An in-flight response must not recreate a purged account's session and IP metadata.
        return app(SessionService::class)->persistFor((int) $userId, fn (): bool => parent::write($sessionId, $data));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return $this
     */
    protected function addRequestInformation(&$payload)
    {
        if ($this->container?->bound('request')) {
            $request = $this->container->make('request');
            $userAgent = $this->userAgent();

            $payload = array_merge($payload, [
                'ip_hash' => IpHash::of($request->ip()),
                'user_agent' => $userAgent,
                'device_label' => DeviceLabel::fromUserAgent($userAgent),
                'country_code' => CountryName::fromRequest($request),
            ]);
        }

        return $this;
    }

    /**
     * @param  string  $sessionId
     * @param  array<string, mixed>  $payload
     * @return bool|null
     */
    protected function performInsert($sessionId, $payload)
    {
        return parent::performInsert($sessionId, [...$payload, 'created_at' => Date::now()]);
    }
}
