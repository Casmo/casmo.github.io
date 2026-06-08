<!doctype html>
<html lang="{{ $site->short_locale }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $page->title ?? $site->title }}</title>
        @vite(['resources/css/site.css', 'resources/js/site.js'])
        <meta property="og:title" content="{{ $page->title ?? $site->title }}">
        <meta property="og:description" content="{{ $page->description ?? $site->description ?? preg_replace('/\s+/', ' ', strip_tags($page->content)) ?? '' }}">
        <meta property="og:image" content="{{ $page->image ?? $site->image ?? 'https://mathieuderuiter.nl/assets/pages/' . ($page->slug ?? $site->slug) . '.png' }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:url" content="https://mathieuderuiter.nl{{ $page->url ?? $site->url }}">
        <meta name="author" content="Mathieu de Ruiter">
    </head>
    <body class="bg-[#1f2329] leading-normal dark:text-zinc-400 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="py-8">
            <statamic:nav:main>
                <a class="text-lg text-lime-500 hover:text-lime-400" href="{{ $url }}">{{ $title }}</a>
            </statamic:nav:main>
        </div>

        @yield('body')
    </body>
</html>
