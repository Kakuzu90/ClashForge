<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Support\Seo\PageMeta;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return PageMeta::page('Home/Index');
    }
}
