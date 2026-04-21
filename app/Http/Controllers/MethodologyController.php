<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use Inertia\Inertia;
use Inertia\Response;

class MethodologyController extends Controller
{
    public function index(): Response
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.methodology.title'),
            description: __('seo.methodology.description'),
            canonical: url('/methodology'),
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Methodology'],
            ],
        ));

        return Inertia::render('Methodology/Index');
    }
}
