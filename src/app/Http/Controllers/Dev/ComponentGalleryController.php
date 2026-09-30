<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Living gallery of every Ui* variant and state (specs/18 §9). Never served in production.
 */
class ComponentGalleryController extends Controller
{
    public function __invoke(): Response
    {
        abort_if(app()->isProduction(), 404);

        return Inertia::render('Dev/Components');
    }
}
