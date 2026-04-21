import { Head, usePage } from '@inertiajs/react';

export default function SeoHead() {
    const { seo } = usePage().props;
    if (!seo) return null;

    const title = seo.title || null;
    const description = seo.description;
    const canonical = seo.canonical;
    const ogTitle = seo.ogTitle || seo.title;
    const ogDescription = seo.ogDescription || seo.description;
    const ogImage = seo.ogImage;
    const ogType = seo.ogType || 'website';
    const twitterCard = seo.twitterCard || 'summary';
    const robots = seo.robots;
    const publishedAt = seo.publishedAt;
    const modifiedAt = seo.modifiedAt;
    const prevUrl = seo.prevUrl;
    const nextUrl = seo.nextUrl;

    return (
        <Head title={title}>
            {description && <meta name="description" content={description} />}
            {canonical && <link rel="canonical" href={canonical} />}
            {robots && <meta name="robots" content={robots} />}

            {/* Open Graph */}
            {ogTitle && <meta property="og:title" content={ogTitle} />}
            {ogDescription && <meta property="og:description" content={ogDescription} />}
            <meta property="og:type" content={ogType} />
            {canonical && <meta property="og:url" content={canonical} />}
            {ogImage && <meta property="og:image" content={ogImage} />}
            <meta property="og:site_name" content="ClashMonitor" />
            {seo.ogLocale && <meta property="og:locale" content={seo.ogLocale} />}

            {/* Twitter Card */}
            <meta name="twitter:card" content={twitterCard} />
            {ogTitle && <meta name="twitter:title" content={ogTitle} />}
            {ogDescription && <meta name="twitter:description" content={ogDescription} />}
            {ogImage && <meta name="twitter:image" content={ogImage} />}

            {/* Article metadata */}
            {publishedAt && <meta property="article:published_time" content={publishedAt} />}
            {modifiedAt && <meta property="article:modified_time" content={modifiedAt} />}

            {/* hreflang alternates */}
            {seo.alternateLocales?.map(alt => (
                <link key={alt.locale} rel="alternate" hrefLang={alt.locale} href={alt.url} />
            ))}

            {/* Pagination */}
            {prevUrl && <link rel="prev" href={prevUrl} />}
            {nextUrl && <link rel="next" href={nextUrl} />}
        </Head>
    );
}
