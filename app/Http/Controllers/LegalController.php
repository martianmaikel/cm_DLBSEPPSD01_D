<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\SeoMeta;
use Inertia\Inertia;

class LegalController extends Controller
{
    public function impressum()
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.impressum.title'),
            description: __('seo.impressum.description'),
            canonical: url('/impressum'),
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Impressum'],
            ],
        ));

        return Inertia::render('Legal/Impressum');
    }

    public function privacy()
    {
        request()->attributes->set('seo', SeoMeta::make(
            title: __('seo.privacy.title'),
            description: __('seo.privacy.description'),
            canonical: url('/datenschutz'),
            breadcrumbs: [
                ['name' => 'ClashMonitor', 'url' => url('/')],
                ['name' => 'Datenschutz'],
            ],
        ));

        return Inertia::render('Legal/Privacy');
    }
}
