<?php

namespace App\Domain\Moderation\Exceptions;

use LogicException;

/**
 * Moderation actions are never edited or deleted (specs/07 `moderation_actions`).
 */
final class ModerationActionIsImmutable extends LogicException {}
