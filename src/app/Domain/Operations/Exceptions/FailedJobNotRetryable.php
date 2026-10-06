<?php

namespace App\Domain\Operations\Exceptions;

use RuntimeException;

/**
 * A failed job whose payload no longer loads, so `queue:retry` cannot put it back. Thrown inside
 * the job's transaction so nothing it may have pushed survives; the job stays for deletion.
 */
final class FailedJobNotRetryable extends RuntimeException {}
