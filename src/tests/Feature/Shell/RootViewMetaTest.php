<?php

use App\Support\Seo\PageMeta;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

it('renders default meta in the root view', function () {
    $this->get('/?utm=x')
        ->assertOk()
        ->assertSee('<meta name="description" content="'.e(config('platform.seo.default_description')).'">', false)
        ->assertSee('<link rel="canonical" href="'.url('/').'">', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertDontSee('noindex');
});

it('renders page meta and escapes JSON-LD', function () {
    Route::middleware('web')->get('/meta-probe', fn () => Inertia::render('Home/Index')->withViewData('meta', new PageMeta(
        title: 'Probe',
        description: 'A "quoted" description',
        canonical: '/meta-probe',
        image: '/og.png',
        jsonLd: ['@type' => 'Thing', 'name' => '</script><script>alert(1)</script>'],
        noindex: true,
    )));

    $this->get('/meta-probe')
        ->assertOk()
        ->assertSee('<title inertia>Probe · '.config('app.name').'</title>', false)
        ->assertSee('content="A &quot;quoted&quot; description"', false)
        ->assertSee('<meta property="og:image" content="'.url('/og.png').'">', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertDontSee('</script><script>alert(1)', false)
        ->assertSee(trim(json_encode('</script>', JSON_HEX_TAG), '"'), false);
});

it('includes the fan-content disclaimer on server-rendered pages via the layout', function () {
    // The footer is a Vue component; this guards the page renders through a layout that has it.
    expect(file_get_contents(resource_path('js/Components/shell/SiteFooter.vue')))
        ->toContain('This material is unofficial and is not endorsed by Supercell. For more information see')
        ->toContain("Supercell's Fan Content Policy");
});

it('passes the page title to the client so it survives hydration', function () {
    $this->get('/dev/layouts/app')->assertInertia(fn (Assert $page) => $page
        ->component('Dev/LayoutApp')
        ->where('meta.title', 'App layout'));
});
