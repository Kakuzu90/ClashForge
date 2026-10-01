<?php

namespace App\Domain\Auth\Data;

/**
 * A registration that passed validation. The password is plain text until RegistrationService
 * hashes it, so this never becomes a prop or a generated type.
 */
final readonly class RegistrationData
{
    public function __construct(
        public string $email,
        public string $username,
        #[\SensitiveParameter]
        public string $password,
    ) {}
}
