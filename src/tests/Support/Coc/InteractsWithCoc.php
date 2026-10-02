<?php

namespace Tests\Support\Coc;

use App\Domain\CocIntegration\Services\CocKeyPool;
use App\Domain\CocIntegration\Support\FakeCocApiClient;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Switches the CoC client between the fake and a faked-HTTP real client, and reads the fixtures.
 */
trait InteractsWithCoc
{
    protected TestHandler $appLog;

    /**
     * @param  list<string>  $tokens
     */
    protected function useHttpCoc(array $tokens = ['test-key-one-secret', 'test-key-two-secret']): void
    {
        config(['coc.driver' => 'http', 'coc.tokens' => $tokens]);
        $this->app->forgetInstance(CocKeyPool::class);
        $this->app->forgetScopedInstances();
    }

    protected function fakeCoc(): FakeCocApiClient
    {
        return $this->app->make(FakeCocApiClient::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function cocFixture(string $path): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) file_get_contents($this->cocFixturePath($path)), true, flags: JSON_THROW_ON_ERROR);
    }

    public function cocFixtureBody(string $path): string
    {
        return (string) file_get_contents($this->cocFixturePath($path));
    }

    public function cocFixturePath(string $path): string
    {
        return config('coc.fake.fixtures_path').'/'.$path;
    }

    /**
     * Sends the default log channel to memory.
     */
    protected function captureAppLog(): void
    {
        config(['logging.channels.capture' => ['driver' => 'monolog', 'handler' => TestHandler::class], 'logging.default' => 'capture']);
        Log::forgetChannel('capture');

        /** @var Logger $logger */
        $logger = Log::channel('capture')->getLogger();
        /** @var TestHandler $handler */
        $handler = $logger->getHandlers()[0];
        $this->appLog = $handler;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    protected function appLogRecords(): array
    {
        return array_map(
            fn (LogRecord $record): array => ['level' => strtolower($record->level->getName()), 'message' => $record->message, 'context' => $record->context],
            $this->appLog->getRecords(),
        );
    }

    /**
     * @return list<string>
     */
    protected function appLogMessages(): array
    {
        return array_column($this->appLogRecords(), 'message');
    }

    protected function appLogText(): string
    {
        return (string) json_encode($this->appLogRecords());
    }
}
