<?php

namespace App\Console\Commands\Assets;

use App\Domain\GameAssets\Exceptions\InvalidManifest;
use App\Domain\GameAssets\Services\GameAssetPolicy;
use App\Domain\GameAssets\Services\PackVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyPackCommand extends Command
{
    protected $signature = 'assets:verify-pack {--pack-version= : Pack version to check (default: the active one)}';

    protected $description = 'Check that the bucket copy of a game-asset pack still matches its committed manifest';

    public function handle(PackVerifier $verifier, GameAssetPolicy $policy): int
    {
        $version = $this->option('pack-version') ?: $policy->activeVersion();

        if (! is_string($version) || ! GameAssetPolicy::isValidVersion($version)) {
            if ($this->option('pack-version') || $policy->versionConfigured()) {
                Log::error('assets.verify_failed', ['version' => $this->option('pack-version') ?: config('assets.pack_version'), 'problems' => ['invalid pack version']]);
                $this->components->error('The pack version is not valid: use letters, digits, dots, dashes or underscores.');

                return self::FAILURE;
            }

            $this->components->info('No active asset pack; nothing to verify.');

            return self::SUCCESS;
        }

        try {
            $report = $verifier->verify($version);
        } catch (InvalidManifest $e) {
            Log::error('assets.verify_failed', ['version' => $version, 'problems' => $e->problems]);
            $this->components->error("The committed manifest for {$version} cannot be read.");
            $this->components->bulletList($e->problems);

            return self::FAILURE;
        }

        if ($report->isClean()) {
            Log::info('assets.pack_verified', ['version' => $version]);
            $this->components->info("Pack {$version} matches its manifest.");

            return self::SUCCESS;
        }

        Log::error('assets.pack_mismatch', [
            'version' => $version,
            'missing' => $report->missing,
            'extra' => $report->extra,
            'altered' => $report->altered,
        ]);

        foreach (['missing' => $report->missing, 'extra' => $report->extra, 'altered' => $report->altered] as $label => $keys) {
            if ($keys !== []) {
                $this->components->error(ucfirst($label).': '.count($keys));
                $this->components->bulletList($keys);
            }
        }

        return self::FAILURE;
    }
}
