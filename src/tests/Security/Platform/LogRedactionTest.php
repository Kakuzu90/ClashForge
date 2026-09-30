<?php

use Illuminate\Database\QueryException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Log;

it('keeps bound values out of logged query errors', function () {
    $path = sys_get_temp_dir().'/clashcommons-json-'.getmypid().'.log';
    config(['logging.channels.json.handler_with.stream' => $path]);
    $e = new QueryException('pgsql', 'insert into users (email) values (?)', ['player@example.com'], new PDOException('SQLSTATE[23505]: Key (email)=(player@example.com) already exists'));

    Log::channel('json')->error($e->getMessage(), ['exception' => $e]);

    $line = (string) file_get_contents($path);
    unlink($path);

    expect($line)->not->toContain('player@example.com')->toContain('insert into users (email) values (?)');
});

it('trusts only the configured proxies for the client IP', function () {
    TrustProxies::at(['10.0.0.1']);
    config(['platform.health.rate_limit_per_minute' => 1]);

    // Through the trusted proxy, two different clients each get their own budget.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get('/health', ['X-Forwarded-For' => '198.51.100.1'])->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->get('/health', ['X-Forwarded-For' => '198.51.100.2'])->assertOk();

    // From an untrusted address the header is ignored, so a spoofed value does not reset the budget.
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->get('/health', ['X-Forwarded-For' => '198.51.100.3']);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])->get('/health', ['X-Forwarded-For' => '198.51.100.4'])->assertTooManyRequests();

    TrustProxies::flushState();
});
