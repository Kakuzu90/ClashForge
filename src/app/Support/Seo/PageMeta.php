<?php

namespace App\Support\Seo;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Server-rendered head metadata for the root view (specs/06 §2). Emitted by Blade, so share cards
 * work even when the SSR renderer is down. Controllers pass it with ->withViewData('meta', ...).
 */
final readonly class PageMeta
{
    /**
     * @param  array<string, mixed>|null  $jsonLd
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $image = null,
        public string $type = 'website',
        public ?array $jsonLd = null,
        public bool $noindex = false,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    /**
     * Render an Inertia page with this meta: the root view gets the full meta (server-rendered for
     * crawlers), and the page gets `meta.title` so the client-side title matches after hydration.
     *
     * @param  array<string, mixed>  $props
     */
    public static function page(string $component, array $props = [], ?self $meta = null): Response
    {
        $meta ??= self::defaults();

        return Inertia::render($component, [...$props, 'meta' => ['title' => $meta->title]])
            ->withViewData('meta', $meta);
    }

    public function fullTitle(): string
    {
        $app = (string) config('app.name');

        return $this->title ? "{$this->title} · {$app}" : $app;
    }

    public function resolvedDescription(): string
    {
        return $this->description ?? (string) config('platform.seo.default_description');
    }

    /**
     * Absolute canonical URL without the query string, so filter permutations never compete.
     */
    public function resolvedCanonical(): string
    {
        return $this->canonical !== null ? url($this->canonical) : url()->current();
    }

    public function resolvedImage(): ?string
    {
        $image = $this->image ?? config('platform.seo.default_og_image');

        return is_string($image) && $image !== '' ? url($image) : null;
    }
}
