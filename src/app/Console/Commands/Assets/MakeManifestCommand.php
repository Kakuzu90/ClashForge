<?php

namespace App\Console\Commands\Assets;

use App\Domain\GameAssets\Services\GameAssetPolicy;
use App\Domain\GameAssets\Services\ManifestBuilder;
use Illuminate\Console\Command;

class MakeManifestCommand extends Command
{
    protected $signature = 'assets:make-manifest {path : Folder holding units/, townhalls/ and leagues/} {--pack-version= : Pack version the manifest describes}';

    protected $description = 'Write or refresh a game-asset pack manifest from its files, keeping the fields staff filled in';

    public function handle(ManifestBuilder $builder): int
    {
        $path = (string) $this->argument('path');
        $version = (string) $this->option('pack-version');

        if (! is_dir($path)) {
            $this->components->error("{$path} is not a folder.");

            return self::FAILURE;
        }

        if (! GameAssetPolicy::isValidVersion($version)) {
            $this->components->error('Pass --pack-version= with letters, digits, dots, dashes or underscores.');

            return self::FAILURE;
        }

        $todo = $builder->build($path, $version);
        $this->components->info("Wrote {$path}/manifest.json for version {$version}.");

        if ($todo !== []) {
            $this->components->warn('Fill these in before publishing:');
            $this->components->bulletList($todo);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
