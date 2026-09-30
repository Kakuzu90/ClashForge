<?php

namespace Tests\Support\Auth;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Points the `security` channel at an in-memory handler so tests can read what was logged.
 */
trait CapturesSecurityLog
{
    protected TestHandler $securityLog;

    protected function captureSecurityLog(): void
    {
        config(['logging.channels.security' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
        Log::forgetChannel('security');

        /** @var Logger $logger */
        $logger = Log::channel('security')->getLogger();
        /** @var TestHandler $handler */
        $handler = $logger->getHandlers()[0];
        $this->securityLog = $handler;
    }

    /**
     * @return list<array{message: string, context: array<string, mixed>}>
     */
    protected function securityEvents(): array
    {
        return array_map(
            fn (LogRecord $record): array => ['message' => $record->message, 'context' => $record->context],
            $this->securityLog->getRecords(),
        );
    }
}
