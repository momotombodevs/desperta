<?php

use Dom\HTMLDocument;

it('publishes canonical and social URLs on the official domain', function (string $page, string $url) {
    $document = HTMLDocument::createFromString(file_get_contents(base_path('docs/'.$page)));

    expect($document->querySelector('link[rel="canonical"]')->getAttribute('href'))->toBe($url);
    expect($document->querySelector('meta[property="og:url"]')->getAttribute('content'))->toBe($url);

    foreach (['meta[property="og:image"]', 'meta[name="twitter:image"]'] as $selector) {
        expect($document->querySelector($selector)->getAttribute('content'))
            ->toBe('https://desperta.momotombo.dev/assets/desperta-social.png');
    }

    $metadata = json_decode($document->querySelector('script[type="application/ld+json"]')->textContent, true, flags: JSON_THROW_ON_ERROR);
    $webPage = array_find($metadata['@graph'], fn (array $item): bool => $item['@type'] === 'WebPage');

    expect($webPage['url'])->toBe($url);
    expect($webPage['publisher']['@id'])->toBe('https://desperta.momotombo.dev/#organization');
})->with([
    ['index.html', 'https://desperta.momotombo.dev/'],
    ['privacy.html', 'https://desperta.momotombo.dev/privacy.html'],
]);

it('links the accessible Google Play badge and application metadata to the Android listing', function () {
    $document = HTMLDocument::createFromString(file_get_contents(base_path('docs/index.html')));
    $link = $document->querySelector('a[href="https://play.google.com/store/apps/details?id=dev.momotombo.desperta"]');

    expect($link)->not->toBeNull();
    expect($link->getAttribute('target'))->toBe('_blank');
    expect($link->getAttribute('rel'))->toContain('noopener', 'noreferrer');

    $badge = $link->querySelector('img');

    expect($badge->getAttribute('alt'))->toBe('Descargá Despertá en Google Play');
    expect($badge->getAttribute('src'))->toBe('assets/google-play-2.svg');
    expect(is_file(base_path('docs/'.$badge->getAttribute('src'))))->toBeTrue();

    $metadata = json_decode($document->querySelector('script[type="application/ld+json"]')->textContent, true, flags: JSON_THROW_ON_ERROR);
    $application = array_find($metadata['@graph'], fn (array $item): bool => $item['@type'] === 'SoftwareApplication');

    expect($application['downloadUrl'])->toBe($link->getAttribute('href'));
});

it('keeps local page and asset references relative and resolvable', function (string $page) {
    $document = HTMLDocument::createFromString(file_get_contents(base_path('docs/'.$page)));

    foreach ($document->querySelectorAll('[href], [src]') as $element) {
        $reference = $element->getAttribute($element->hasAttribute('href') ? 'href' : 'src');

        if (str_starts_with($reference, '#') || parse_url($reference, PHP_URL_SCHEME) !== null) {
            continue;
        }

        expect($reference)->not->toStartWith('/');
        expect(is_file(base_path('docs/'.parse_url($reference, PHP_URL_PATH))))->toBeTrue();
    }
})->with(['index.html', 'privacy.html']);

it('lists only the official website pages in the sitemap', function () {
    $sitemap = simplexml_load_file(base_path('docs/sitemap.xml'));
    $urls = array_map(fn (SimpleXMLElement $url): string => (string) $url->loc, iterator_to_array($sitemap->url, false));

    expect($urls)->toBe(['https://desperta.momotombo.dev/', 'https://desperta.momotombo.dev/privacy.html']);
    expect(file_get_contents(base_path('docs/robots.txt')))->toContain('Sitemap: https://desperta.momotombo.dev/sitemap.xml');
});

it('resolves manifest icons relative to the published site', function () {
    $manifest = json_decode(file_get_contents(base_path('docs/site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    foreach ($manifest['icons'] as $icon) {
        expect($icon['src'])->not->toStartWith('/');
        expect(is_file(base_path('docs/'.$icon['src'])))->toBeTrue();
    }
});
