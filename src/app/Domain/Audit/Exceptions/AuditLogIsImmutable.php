<?php

namespace App\Domain\Audit\Exceptions;

use LogicException;

/**
 * Audit entries are never edited or deleted (specs/12 §9).
 */
final class AuditLogIsImmutable extends LogicException {}
