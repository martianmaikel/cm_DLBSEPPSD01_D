<?php

namespace App\DataTransferObjects;

use Illuminate\Support\Str;

class SeoMeta
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public string $ogType = 'website',
        public string $twitterCard = 'summary',
        public ?string $robots = null,
        public ?string $publishedAt = null,
        public ?string $modifiedAt = null,
        public ?array $jsonLd = null,
        public ?string $prevUrl = null,
        public ?string $nextUrl = null,
        public ?array $breadcrumbs = null,
    ) {}

    public static function make(
        ?string $title = null,
        ?string $description = null,
        ?string $canonical = null,
        ?string $ogTitle = null,
        ?string $ogDescription = null,
        ?string $ogImage = null,
        string $ogType = 'website',
        string $twitterCard = 'summary',
        ?string $robots = null,
        ?string $publishedAt = null,
        ?string $modifiedAt = null,
        ?array $jsonLd = null,
        ?string $prevUrl = null,
        ?string $nextUrl = null,
        ?array $breadcrumbs = null,
    ): self {
        return new self(
            title: $title,
            description: $description ? Str::limit($description, 155, '...') : null,
            canonical: $canonical ?? url()->current(),
            ogTitle: $ogTitle,
            ogDescription: $ogDescription ? Str::limit($ogDescription, 200, '...') : null,
            ogImage: $ogImage,
            ogType: $ogType,
            twitterCard: $twitterCard,
            robots: $robots,
            publishedAt: $publishedAt,
            modifiedAt: $modifiedAt,
            jsonLd: $jsonLd,
            prevUrl: $prevUrl,
            nextUrl: $nextUrl,
            breadcrumbs: $breadcrumbs,
        );
    }

    public function toArray(): array
    {
        // Auto-generate hreflang alternates from canonical URL
        $alternateLocales = null;
        if ($this->canonical && ! $this->robots) {
            $baseUrl = strtok($this->canonical, '?');
            $alternateLocales = [
                ['locale' => 'en', 'url' => $baseUrl . '?lang=en'],
                ['locale' => 'de', 'url' => $baseUrl . '?lang=de'],
                ['locale' => 'x-default', 'url' => $baseUrl],
            ];
        }

        $locale = app()->getLocale();

        return array_filter([
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'ogTitle' => $this->ogTitle ?? $this->title,
            'ogDescription' => $this->ogDescription ?? $this->description,
            'ogImage' => $this->ogImage,
            'ogType' => $this->ogType,
            'ogLocale' => $locale === 'de' ? 'de_DE' : 'en_US',
            'twitterCard' => $this->twitterCard,
            'robots' => $this->robots,
            'publishedAt' => $this->publishedAt,
            'modifiedAt' => $this->modifiedAt,
            'jsonLd' => $this->jsonLd,
            'prevUrl' => $this->prevUrl,
            'nextUrl' => $this->nextUrl,
            'breadcrumbs' => $this->breadcrumbs,
            'alternateLocales' => $alternateLocales,
        ], fn ($v) => $v !== null);
    }
}
