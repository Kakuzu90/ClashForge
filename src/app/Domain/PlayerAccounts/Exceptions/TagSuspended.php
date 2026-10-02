<?php

namespace App\Domain\PlayerAccounts\Exceptions;

use RuntimeException;

/**
 * Thrown inside the verification transaction when the locked rows show the tag was suspended
 * after the pre-check: everything rolls back and the attempt is refused (specs/13 §2).
 */
final class TagSuspended extends RuntimeException {}
