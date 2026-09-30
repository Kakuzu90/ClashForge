<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * Object storage cannot issue an upload URL; the upload UI shows its "unavailable" state (specs/10 §10).
 */
final class StorageUnavailable extends RuntimeException {}
