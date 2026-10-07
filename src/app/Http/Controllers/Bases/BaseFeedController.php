<?php

namespace App\Http\Controllers\Bases;

use App\Domain\Bases\Queries\BaseFeedQuery;
use App\Domain\Bases\Services\FeedDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bases\BaseFeedRequest;
use App\Support\Seo\PageMeta;
use Inertia\Response;

/**
 * `/bases`: every feed filter and sort (FR-BASE-13). Public and server-rendered.
 */
class BaseFeedController extends Controller
{
    public function __invoke(BaseFeedRequest $request, BaseFeedQuery $feed, FeedDefaults $defaults): Response
    {
        return PageMeta::page('Bases/Index', FeedProps::for($request, $feed, $defaults, defaultTh: false), new PageMeta(
            title: 'Base layouts',
            description: 'Clash of Clans base layouts shared by verified players. Filter by Town Hall, category and tag, and copy a layout straight into the game.',
        ));
    }
}
