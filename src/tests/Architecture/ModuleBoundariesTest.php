<?php

use App\Support\Enums\Contracts\HasLabelAndColor;

// Per-module rules from specs/19 §2, applied to every folder under app/Domain (new modules are
// covered automatically). Layer-level rules (Http → Domain public surface) live in deptrac.yaml.

$domainPath = dirname(__DIR__, 2).'/app/Domain';

$modules = collect(glob($domainPath.'/*', GLOB_ONLYDIR) ?: [])
    ->map(fn (string $path): string => basename($path))
    ->values()
    ->all();

// Edge modules and the Audit leaf may not depend on any other module.
$isolated = ['CocIntegration', 'Media', 'GameAssets', 'Audit'];

// Another module may only reference Contracts, Services, Data, Events and Enums (specs/19 §2),
// so everything else is off-limits across module lines.
$internal = ['Actions', 'Queries', 'Models', 'Exceptions', 'Jobs', 'Listeners', 'Notifications', 'Policies', 'Support'];

it('discovers the modules listed in specs/19', function () use ($modules) {
    expect($modules)->toContain('Auth', 'Bases', 'CocIntegration', 'Media', 'GameAssets', 'Audit', 'Search');
});

foreach ($modules as $module) {
    $others = array_values(array_diff($modules, [$module]));

    if ($others === []) {
        continue;
    }

    arch("{$module} only touches other modules through their public surface")
        ->expect("App\\Domain\\{$module}")
        ->not->toUse(array_merge(...array_map(
            fn (string $other): array => array_map(fn (string $layer): string => "App\\Domain\\{$other}\\{$layer}", $internal),
            $others,
        )));

    if (in_array($module, $isolated, true)) {
        arch("{$module} is isolated from every other module")
            ->expect("App\\Domain\\{$module}")
            ->not->toUse(array_map(fn (string $other): string => "App\\Domain\\{$other}", $others));
    }
}

arch('the domain never depends on the HTTP layer')
    ->expect('App\Domain')
    ->not->toUse('App\Http');

arch('support never depends on the domain')
    ->expect('App\Support')
    ->not->toUse('App\Domain');

arch('domain enums are string-backed')
    ->expect('App\Domain')
    ->enums()
    ->toBeStringBackedEnums();

arch('domain enums expose label and colour')
    ->expect('App\Domain')
    ->enums()
    ->toImplement(HasLabelAndColor::class);
