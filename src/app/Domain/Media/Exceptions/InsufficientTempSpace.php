<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * The worker's temp volume is too full to download the original; the job goes back on the queue.
 */
final class InsufficientTempSpace extends RuntimeException {}
