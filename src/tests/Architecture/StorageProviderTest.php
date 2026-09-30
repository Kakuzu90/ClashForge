<?php

// specs/10 §2.1: moving between local storage and production storage is an .env change, so no
// code outside config/filesystems.php may name a provider.

it('names no storage provider outside config/filesystems.php', function () {
    $root = dirname(__DIR__, 2);
    $pattern = '/\b(r2|minio|cloudflare|backblaze|wasabi)\b/i';
    $offenders = [];

    foreach (['app', 'bootstrap', 'config', 'database', 'routes', 'resources/js', 'resources/views'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen($root) + 1);

            if ($path === 'config/filesystems.php' || str_starts_with($path, 'bootstrap/cache/') || ! preg_match('/\.(php|ts|vue)$/', $path)) {
                continue;
            }

            if (preg_match($pattern, (string) file_get_contents($file->getPathname()), $match)) {
                $offenders[] = "{$path} ({$match[0]})";
            }
        }
    }

    expect($offenders)->toBe([]);
});
