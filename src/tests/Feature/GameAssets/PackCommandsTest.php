<?php

use App\Domain\GameAssets\Services\ManifestBuilder;
use App\Domain\GameAssets\Support\PackManifest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\GameAssets\InteractsWithPacks;

uses(InteractsWithPacks::class);

beforeEach(fn () => $this->setUpPack());
afterEach(fn () => $this->tearDownPack());

describe('assets:make-manifest', function () {
    it('records checksum, size and dimensions from the files', function () {
        $manifest = PackManifest::fromFile("{$this->packDir}/manifest.json");
        $entry = $manifest->get('units/barbarian.png');

        expect(array_keys($manifest->entries()))->toBe(['heroes/archer-queen.png', 'leagues/29000022.png', 'townhalls/16.png', 'units/barbarian.png'])
            ->and($entry->sha256)->toBe(hash_file('sha256', "{$this->packDir}/units/barbarian.png"))
            ->and($entry->bytes)->toBe(filesize("{$this->packDir}/units/barbarian.png"))
            ->and([$entry->width, $entry->height])->toBe([64, 64])
            ->and($entry->ref)->toBe('Barbarian')
            ->and($manifest->get('townhalls/16.png')->displayName)->toBe('Town Hall 16');
    });

    it('keeps staff-edited fields and refreshes checksums on a rerun', function () {
        file_put_contents("{$this->packDir}/units/barbarian.png", self::png(80));

        $this->artisan('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();

        $entry = PackManifest::fromFile("{$this->packDir}/manifest.json")->get('units/barbarian.png');
        expect($entry->category->value)->toBe('troop')
            ->and($entry->source)->toBe('test fixture')
            ->and([$entry->width, $entry->bytes])->toBe([80, filesize("{$this->packDir}/units/barbarian.png")]);
    });

    it('lists what still needs filling in for new units', function () {
        file_put_contents("{$this->packDir}/units/wizard.png", self::png(64));

        $this->artisan('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '1'])
            ->expectsOutputToContain('units/wizard.png: source is required')
            ->assertFailed();
    });

    it('names a new spell the way the API does', function () {
        File::ensureDirectoryExists("{$this->packDir}/spells");
        file_put_contents("{$this->packDir}/spells/healing.png", self::png(64));

        app(ManifestBuilder::class)->build($this->packDir, '1');

        $assets = json_decode((string) file_get_contents("{$this->packDir}/manifest.json"), true)['assets'];
        $entry = collect($assets)->firstWhere('key', 'spells/healing.png');
        expect([$entry['ref'], $entry['display_name']])->toBe(['Healing Spell', 'Healing Spell']);
    });

    it('takes category and village from the folder', function () {
        File::ensureDirectoryExists("{$this->packDir}/townhalls/builder-base");
        File::ensureDirectoryExists("{$this->packDir}/pets");
        file_put_contents("{$this->packDir}/townhalls/builder-base/5.png", self::png(70));
        file_put_contents("{$this->packDir}/pets/unicorn.png", self::png(71));
        $this->editManifest(fn (array $asset): array => [...$asset, 'source' => 'test fixture']);
        $this->artisan('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '1']);
        $this->editManifest(fn (array $asset): array => [...$asset, 'source' => 'test fixture']);

        $this->artisan('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();

        $manifest = PackManifest::fromFile("{$this->packDir}/manifest.json");
        expect($manifest->get('townhalls/builder-base/5.png'))
            ->category->value->toBe('town_hall')
            ->village->value->toBe('builderBase')
            ->displayName->toBe('Builder Hall 5')
            ->and($manifest->get('pets/unicorn.png'))
            ->category->value->toBe('pet')
            ->village->value->toBe('home')
            ->and($manifest->get('townhalls/16.png')->village->value)->toBe('home')
            ->and($manifest->get('leagues/29000022.png')->village)->toBeNull();
    });

    it('lists files that cannot be packed as they are', function () {
        file_put_contents("{$this->packDir}/units/giant.png", self::webp(64));
        file_put_contents("{$this->packDir}/units/golem.png", self::png(64));
        config(['assets.max_bytes' => filesize("{$this->packDir}/units/golem.png") - 1]);

        // One bullet list is one write, so read the whole output rather than expecting each line.
        expect(Artisan::call('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '1']))->toBe(1)
            ->and(Artisan::output())
            ->toContain('units/giant.png: the file is image/webp, so it must be named .webp')
            ->toContain('units/golem.png: '.filesize("{$this->packDir}/units/golem.png").' bytes is over the');
    });

    it('skips the Zone.Identifier files Windows leaves next to downloads', function () {
        file_put_contents("{$this->packDir}/units/barbarian.png:Zone.Identifier", "[ZoneTransfer]\nZoneId=3\n");

        $this->artisan('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();
        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();

        expect(PackManifest::fromFile("{$this->packDir}/manifest.json")->entries())->toHaveCount(4)
            ->and($this->assetsDisk()->allFiles('game/1/units'))->toBe(['game/1/units/barbarian.png']);
    });

    it('refuses an unsafe version', function () {
        $this->artisan('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '../2'])->assertFailed();
    });
});

describe('assets:publish-pack', function () {
    it('uploads every file byte for byte, manifest last, and writes the repo copy', function () {
        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();

        $disk = $this->assetsDisk();

        foreach (['units/barbarian.png', 'heroes/archer-queen.png', 'townhalls/16.png', 'leagues/29000022.png'] as $key) {
            expect($disk->get("game/1/{$key}"))->toBe(file_get_contents("{$this->packDir}/{$key}"));
        }

        expect($disk->exists('game/1/manifest.json'))->toBeTrue()
            ->and(file_get_contents(str_replace('{version}', '1', config('assets.manifest_path'))))->toBe($disk->get('game/1/manifest.json'));
    });

    it('sets Content-Type from the file signature and immutable caching', function () {
        $disk = Mockery::mock($this->assetsDisk())->makePartial();
        Storage::set((string) config('assets.disk'), $disk);

        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();

        $disk->shouldHaveReceived('put')->withArgs(fn (string $path, $contents, array $options = []) => $path === 'game/1/units/barbarian.png'
            && $options === ['ContentType' => 'image/png', 'CacheControl' => config('assets.cache_control')]);
    });

    it('publishes a WebP file as image/webp', function () {
        unlink("{$this->packDir}/townhalls/16.png");
        file_put_contents("{$this->packDir}/townhalls/16.webp", self::webp(64));
        $this->artisan('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '1']);
        $this->editManifest(fn (array $asset): array => [...$asset, 'source' => 'test fixture']);
        $this->artisan('assets:make-manifest', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();

        $disk = Mockery::mock($this->assetsDisk())->makePartial();
        Storage::set((string) config('assets.disk'), $disk);

        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();

        $disk->shouldHaveReceived('put')->withArgs(fn (string $path, $contents, array $options = []) => $path === 'game/1/townhalls/16.webp'
            && ($options['ContentType'] ?? null) === 'image/webp');
    });

    it('logs a summary line when a pack is published', function () {
        Log::spy();

        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();

        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []) => $message === 'assets.pack_published' && $context['assets'] === 4);
    });

    it('rolls back an object whose upload threw after storing it', function () {
        $real = $this->assetsDisk();
        $disk = Mockery::mock($real)->makePartial();
        $disk->shouldReceive('put')->andReturnUsing(function (string $path, $contents, array $options = []) use ($real) {
            $real->put($path, $contents, $options);

            if (str_ends_with($path, 'townhalls/16.png')) {
                throw new RuntimeException('connection reset after write');
            }

            return true;
        });
        Storage::set((string) config('assets.disk'), $disk);

        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])
            ->expectsOutputToContain('Everything uploaded in this run was removed')
            ->assertFailed();

        expect($real->allFiles())->toBe([]);
    });

    it('refuses a second publish of the same version while one is running', function () {
        $lock = Cache::lock('assets:publish:1', 60);
        $lock->get();

        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])
            ->expectsOutputToContain('Another publish of version 1 is running')
            ->assertFailed();

        $lock->release();
        expect($this->assetsDisk()->allFiles())->toBe([]);
    });

    it('refuses to reuse a version whose committed manifest describes another pack', function () {
        $repoCopy = str_replace('{version}', '1', config('assets.manifest_path'));
        File::ensureDirectoryExists(dirname($repoCopy));
        File::put($repoCopy, '{"version":"1","assets":[]}');

        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])
            ->expectsOutputToContain('already describes a different pack')
            ->assertFailed();

        expect($this->assetsDisk()->allFiles())->toBe([])
            ->and(File::get($repoCopy))->toBe('{"version":"1","assets":[]}');
    });

    it('never overwrites a published version', function () {
        $this->assetsDisk()->put('game/1/units/old.png', 'x');

        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])
            ->expectsOutputToContain('publish under a new version')
            ->assertFailed();

        expect($this->assetsDisk()->exists('game/1/units/barbarian.png'))->toBeFalse();
    });

    it('uploads nothing when the folder and manifest disagree', function (string $case) {
        $dir = $this->packDir;
        match ($case) {
            'file changed after the manifest' => file_put_contents("{$dir}/townhalls/16.png", self::png(90)),
            'file not in the manifest' => file_put_contents("{$dir}/units/extra.png", self::png(64)),
            'manifest entry without a file' => unlink("{$dir}/leagues/29000022.png"),
            'not an allowed type' => file_put_contents("{$dir}/units/barbarian.png", 'GIF89a not an image'),
            'extension disagrees with the signature' => file_put_contents("{$dir}/townhalls/16.png", self::webp(66)),
            'over the size limit' => config(['assets.max_bytes' => filesize("{$dir}/units/barbarian.png") - 1]),
            'wrong version' => file_put_contents("{$dir}/manifest.json", str_replace('"version": "1"', '"version": "2"', (string) file_get_contents("{$dir}/manifest.json"))),
        };

        $this->artisan('assets:publish-pack', ['path' => $dir, '--pack-version' => '1'])
            ->expectsOutputToContain('Nothing was uploaded')
            ->assertFailed();

        expect($this->assetsDisk()->allFiles())->toBe([]);
    })->with(['file changed after the manifest', 'file not in the manifest', 'manifest entry without a file', 'not an allowed type', 'extension disagrees with the signature', 'over the size limit', 'wrong version']);

    it('removes everything it uploaded when a stored object does not verify', function () {
        // The store hands back different bytes for one object, as a transforming proxy would.
        $real = $this->assetsDisk();
        $disk = Mockery::mock($real)->makePartial();
        $disk->shouldReceive('get')->andReturnUsing(fn (string $path) => str_ends_with($path, 'townhalls/16.png') ? 'tampered' : $real->get($path));
        Storage::set((string) config('assets.disk'), $disk);

        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])
            ->expectsOutputToContain('does not match the manifest checksum')
            ->assertFailed();

        expect($real->allFiles())->toBe([])
            ->and(file_exists(str_replace('{version}', '1', config('assets.manifest_path'))))->toBeFalse();
    });
});

describe('assets:verify-pack', function () {
    beforeEach(function () {
        $this->artisan('assets:publish-pack', ['path' => $this->packDir, '--pack-version' => '1'])->assertSuccessful();
        config(['assets.pack_version' => '1']);
    });

    it('passes when the bucket matches the committed manifest', function () {
        $this->artisan('assets:verify-pack')->expectsOutputToContain('matches its manifest')->assertSuccessful();
    });

    it('reports missing, extra and altered objects', function () {
        Log::spy();
        $disk = $this->assetsDisk();
        $disk->delete('game/1/leagues/29000022.png');
        $disk->put('game/1/units/stowaway.png', 'x');
        $disk->put('game/1/units/barbarian.png', self::png(99));

        $this->artisan('assets:verify-pack')
            ->expectsOutputToContain('leagues/29000022.png')
            ->expectsOutputToContain('units/stowaway.png')
            ->expectsOutputToContain('units/barbarian.png')
            ->assertFailed();

        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => $message === 'assets.pack_mismatch'
            && $context['missing'] === ['leagues/29000022.png']
            && $context['extra'] === ['units/stowaway.png']
            && $context['altered'] === ['units/barbarian.png']);
    });

    it('flags a bucket manifest that differs from the committed one', function (Closure $swap) {
        $disk = $this->assetsDisk();
        $disk->put('game/1/manifest.json', $swap((string) $disk->get('game/1/manifest.json')));

        $this->artisan('assets:verify-pack')->expectsOutputToContain('manifest.json')->assertFailed();
    })->with([
        'emptied' => [fn (string $json) => '{"version":"1","assets":[]}'],
        // Same meaning after parsing, different bytes: provenance records must not be rewritten.
        'extra field' => [fn (string $json) => str_replace('"version": "1",', '"version": "1", "license": "CC0",', $json)],
        'reformatted' => [fn (string $json) => json_encode(json_decode($json, true))],
    ]);

    it('fails loudly on a malformed pack version instead of skipping', function () {
        Log::spy();
        config(['assets.pack_version' => '../1']);

        $this->artisan('assets:verify-pack')->expectsOutputToContain('pack version is not valid')->assertFailed();

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'assets.pack_version_invalid');
    });

    it('logs a summary line when the pack is clean', function () {
        Log::spy();

        $this->artisan('assets:verify-pack')->assertSuccessful();

        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []) => $message === 'assets.pack_verified' && $context['version'] === '1');
    });

    it('does nothing without an active pack', function () {
        config(['assets.pack_version' => null]);

        $this->artisan('assets:verify-pack')->expectsOutputToContain('No active asset pack')->assertSuccessful();
    });

    it('runs weekly on Sunday at 05:15, one server, no overlap', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'assets:verify-pack'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('15 5 * * 0')
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->onOneServer)->toBeTrue()
            ->and($event->runInBackground)->toBeTrue();
    });
});
