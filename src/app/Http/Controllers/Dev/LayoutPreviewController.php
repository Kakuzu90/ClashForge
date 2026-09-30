<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Support\Seo\PageMeta;
use Inertia\Response;

/**
 * Previews each persistent layout (public, app, admin) for review and click-through. Never served in production.
 */
class LayoutPreviewController extends Controller
{
    public function __invoke(string $layout): Response
    {
        abort_if(app()->isProduction(), 404);

        return PageMeta::page('Dev/Layout'.ucfirst($layout), meta: new PageMeta(title: ucfirst($layout).' layout', noindex: true));
    }
}
