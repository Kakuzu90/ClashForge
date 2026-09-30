<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Support\Seo\PageMeta;
use Inertia\Response;

/**
 * Living gallery of every Ui* variant and state (specs/18 §9). Never served in production.
 */
class ComponentGalleryController extends Controller
{
    public function __invoke(): Response
    {
        abort_if(app()->isProduction(), 404);

        return PageMeta::page('Dev/Components', meta: new PageMeta(title: 'Components', noindex: true));
    }
}
