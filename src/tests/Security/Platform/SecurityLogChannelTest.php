<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

// specs/11 §3: security events go to a dedicated, structured channel.

it('writes security events as one JSON object per line with the request context', function () {
    $path = sys_get_temp_dir().'/clashcommons-security-'.getmypid().'.log';
    config(['logging.channels.security.driver' => 'single', 'logging.channels.security.path' => $path]);
    Context::add('request_id', '01j00000000000000000000000');

    Log::channel('security')->info('auth.login_failed', ['reason' => 'bad_password']);

    $line = json_decode(trim((string) file_get_contents($path)), true);
    unlink($path);

    expect($line['message'])->toBe('auth.login_failed')
        ->and($line['context'])->toBe(['reason' => 'bad_password'])
        ->and($line['extra']['request_id'])->toBe('01j00000000000000000000000');
});
