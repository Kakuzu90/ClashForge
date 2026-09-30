<?php

namespace App\Domain\Media\Exceptions;

use RuntimeException;

/**
 * Object storage refused to delete a media row's objects; the row is kept so the job can retry.
 */
final class ObjectsNotDeleted extends RuntimeException {}
