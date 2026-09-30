<?php

namespace App\Support\Observability;

use Illuminate\Database\QueryException;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * QueryException messages interpolate bound values (and Postgres adds "Key (email)=(...)"), so the
 * message is replaced by the SQLSTATE and the placeholder SQL wherever errors leave the process.
 */
final class QueryExceptionRedactor implements ProcessorInterface
{
    public static function describe(QueryException $e): string
    {
        $state = is_array($e->errorInfo) && isset($e->errorInfo[0]) ? (string) $e->errorInfo[0] : (string) $e->getCode();

        return "SQLSTATE {$state} while running: {$e->getSql()}";
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;

        if (! $exception instanceof QueryException) {
            return $record;
        }

        $context = $record->context;
        $context['exception'] = ['class' => $exception::class, 'message' => self::describe($exception), 'file' => $exception->getFile().':'.$exception->getLine()];

        return $record->with(message: str_contains($record->message, $exception->getMessage()) ? self::describe($exception) : $record->message, context: $context);
    }
}
