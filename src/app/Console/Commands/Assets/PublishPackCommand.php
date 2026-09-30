<?php

namespace App\Console\Commands\Assets;

use App\Domain\GameAssets\Exceptions\InvalidManifest;
use App\Domain\GameAssets\Exceptions\PackPublishFailed;
use App\Domain\GameAssets\Services\GameAssetPolicy;
use App\Domain\GameAssets\Services\PackPublisher;
use Illuminate\Console\Command;

class PublishPackCommand extends Command
{
    protected $signature = 'assets:publish-pack {path : Pack folder with its manifest.json} {--pack-version= : New, unused pack version}';

    protected $description = 'Upload a curated game-asset pack byte-for-byte to game/{version}/ and verify every object';

    public function handle(PackPublisher $publisher, GameAssetPolicy $policy): int
    {
        $version = (string) $this->option('pack-version');

        if (! GameAssetPolicy::isValidVersion($version)) {
            $this->components->error('Pass --pack-version= with letters, digits, dots, dashes or underscores.');

            return self::FAILURE;
        }

        try {
            $manifest = $publisher->publish((string) $this->argument('path'), $version, fn (string $key) => $this->line("  verified {$key}", verbosity: 'v'));
        } catch (InvalidManifest $e) {
            $this->components->error('The pack folder and its manifest do not agree. Nothing was uploaded.');
            $this->components->bulletList($e->problems);

            return self::FAILURE;
        } catch (PackPublishFailed $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $count = count($manifest->entries());
        $this->components->info("Published {$count} assets to {$policy->prefix($version)} and wrote {$policy->manifestPath($version)}.");
        $this->line("Commit the manifest, then activate the pack with ASSETS_PACK_VERSION={$version}.");

        return self::SUCCESS;
    }
}
