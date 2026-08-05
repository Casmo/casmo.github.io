<!doctype html>
<html lang="{{ $site->short_locale }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $page->title ?? $site->title }}</title>
        @vite(['resources/css/site.css', 'resources/js/site.js'])
        <meta property="og:title" content="{{ $page->title ?? $site->title }}">
        <meta property="og:description" content="{{ $page->description ?? $site->description ?? preg_replace('/\s+/', ' ', strip_tags(\App\Support\Palette::render((string) $page->content))) ?? '' }}">
        <meta property="og:image" content="{{ $page->image ?? $site->image ?? 'https://mathieuderuiter.nl/assets/pages/' . ($page->slug ?? $site->slug) . '.png' }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:url" content="https://mathieuderuiter.nl{{ $page->url ?? $site->url }}">
        <meta name="author" content="Mathieu de Ruiter">
    </head>
    <body class="bg-[#1f2329] text-zinc-400">
        {{--
        | Nav as `ls` output. Together with the page's own `cat` prompt and the
        | `fortune` at the bottom, the three prompts read as one session down the page
        | rather than three separate jokes.
        |
        | The nav and the page header share one continuous chrome panel, so the page has
        | a single transition into the prose instead of a scanlined strip per prompt.
        | Child views supply their header via @section('terminal-header'), not 'body'.
        |
        | Panels run edge to edge; .shell keeps their contents on the prose column.
        --}}
        <div class="panel scanlines">
            <div class="shell">
                <nav>
                    <x-terminal.prompt command="ls" />

                    {{--
                    | Titles are stored path-styled ("/About"), so they are trimmed and
                    | lowercased into directory names here.
                    |
                    | Keep this body to plain markup and {{ }} expressions only. Statamic's
                    | paired-tag compiler mis-matches the closing tag when the body holds
                    | @directives, and silently swallows the rest of the file.
                    --}}
                    <ul class="listing">
                        <statamic:nav:main>
                            <li class="listing__item"><a href="{{ $url }}" aria-current="{{ \App\Support\Terminal::inSection($url) ? 'page' : 'false' }}">{{ Str::lower(trim($title, '/')) }}/</a></li>
                        </statamic:nav:main>
                    </ul>
                </nav>

                @yield('terminal-header')
            </div>
        </div>

        <div class="shell overflow-x-clip">
            @yield('body')
        </div>

        {{-- Fortune: the Unix program that printed a random quip as you logged out.
             The second chrome panel, so it is scanlined as a whole rather than per line. --}}
        <div class="fortune scanlines" style="border-top: 1px solid var(--rule);">
            <div class="shell">
                <x-terminal.prompt command="fortune" />

                <statamic:collection:trivia as="entries">
                    @foreach ($entries as $entry)
                        @php
                            $icon = $entry->icon;
                            $icon = ($icon instanceof \Statamic\Fields\Value) ? $icon->value() : $icon;
                            $icon = is_iterable($icon) ? collect($icon)->first() : $icon;
                        @endphp
                        <p class="fortune__line" data-trivia style="display: none;">
                            @if ($icon)
                                {{-- Native size, untinted: these are artefacts of the games
                                     they came from, not palette decoration. --}}
                                <img class="fortune__icon" src="{{ $icon->url() }}" width="{{ $icon->width() }}" height="{{ $icon->height() }}" alt="" />
                            @endif
                            <span>{{ $entry->title }}</span>
                        </p>
                    @endforeach
                </statamic:collection:trivia>
            </div>
        </div>


        {{--
        | The dungeon is temporarily hidden. Flip this to @if (true), or drop the
        | @if/@endif pair, to bring it back -- everything inside is intact.
        |
        | Disabled with @if rather than by wrapping it in a Blade comment because the
        | block already contains a Blade comment of its own, and those cannot nest.
        --}}
        @if (false)
        @php
            /*
            | Dungeon footer
            |
            | public/assets/tilemap.png is a 20 x 20 grid of 16px tiles with 1px between them.
            | Every cell below is one tile, addressed as 'column,row' (zero based, from the top left
            | of the sheet). Optionally add a colour after a space to tint that tile: '0,12 #5a7a2e'.
            | Use '.' (or null) for an empty cell.
            |
            | Rows may be any length; shorter rows are padded with empty cells. Any number of rows
            | works. Tiles are always the same size (32px, see --dungeon-scale in site.css), so the
            | grid is centered and simply cropped on screens that are too narrow for it.
            |
            | To keep it full width on any screen, the first and last tile of every row are repeated
            | outwards to the edges. So the outer tile of a row is also its "fill" tile: here that is
            | solid rock next to the room and the floor slabs underneath. Set $dungeonEdge to 0 to
            | leave the sides empty instead.
            |
            | The room is artwork, not interface, but it still answers to the site's one accent: the
            | only tint brighter than the stonework is $accent, a dimmed lime matching --accent-dim.
            | Untinted tiles fall back to --dungeon-color, which is the rule grey.
            */
            // Shorthands, so a repeated tile stays readable. A cell is just a string, so
            // array_fill(0, 20, $wall) is a quick way to fill a whole row.
            $accent = '#5a7a2e';

            $wall = '15,18';
            $floor = '15,18 #4a525d';
            $rock = '15,18 #333a43';
            $hearth = '0,2 '.$accent;
            $key = '16,4 '.$accent;
            $monster1 = '3,14';
            $monsterFlying1 = '1,19';
            $keyBox = '7,0';
            $door = '16,2';
            $stairBottom = '0,6';
            $stair = '0,4';

            // Stone
            $sWallLeft = '15,10';
            $sWallRight = '17,10';
            $sWallBottomLeft = '15,11';
            $sWallBottom = '16,11';
            $sWallBottomRight = '17,11';
            $sWallInnerTopLeft = '19,10';
            $sWallInnerTopRight = '19,11';
            $sWallTop = '16,9';
            $sWallTopLeft = '15,9';
            $sWallTopRight = '17,9';

            // dirt (-5, +4)
            $dWallLeft = '10,14';
            $dWallRight = '12,14';
            $dWallBottomLeft = '10,15';
            $dWallBottom = '11,15';
            $dWallBottomRight = '12,15';
            $dWallInnerTopLeft = '14,14';
            $dWallInnerTopRight = '14,15';
            $dWallInnerBottomLeft = '14,12';
            $dWallTop = '11,13';
            $dWallTopLeft = '10,13';
            $dWallTopRight = '12,13';


            $dungeon = [
                ['.', $sWallTopLeft, $sWallTop, $sWallTop,   $sWallTop, $sWallTop, $sWallTop, $sWallTop, $sWallTop,   $sWallTop, $sWallTop,  $sWallTop, $sWallTop,  $sWallTop, $sWallTopRight, '.', '.',    '.', '.', '.', '.', '.'],
                ['.', $sWallLeft, '.', '.',   '.', '.', $sWallInnerTopLeft, $sWallBottom, $sWallBottom,   $sWallInnerTopRight, '.', '.', '.', '.', $sWallRight, '.', '.',    $sWallTopLeft, $sWallTop, $sWallTopRight, '.', ],
                ['.', $sWallLeft, '.', '.',   '.', '.', $sWallRight, '.', '.',   $sWallLeft, $sWallInnerTopLeft,  $sWallBottom, $sWallBottom,  $sWallBottom, $sWallBottomRight, '.', '.',    $sWallLeft, '.', $sWallRight, '.'],
                ['.', $sWallLeft, '.', '.',   '.', '.', $sWallRight, '.', '.',   $sWallBottomLeft, $sWallBottomRight, '.', '.', '.', '.', '.', '.',    $sWallBottomLeft, $sWallBottom, $sWallBottomRight, '.', ],
                ['.', $sWallLeft, '.', '.',   '.', '.', $sWallRight, '.', '.',   '.', '.', '.', '.', '.', '.', '.', '.',    '.', '.', ],
                ['.', $sWallBottomLeft, $sWallBottom, $sWallBottom, $sWallBottom, $sWallBottom, $sWallBottomRight, '.', '.',   '.', '.', '.', '.', '.', '.', '.', '.',    '.', '.', ],
                ['.', '.', '.', '.', '.', '.', '.', '.', '.',   '.', '.', '.', '.', '.', '.', '.', $monsterFlying1,    '.', '.', ],
                ['.', '.', '.', '.', $key, '.', '.', '.', '.', '.',   $monster1, '.', '.', '.', '.', '.', '.', '.',    '.', '.', ],
                ['.', '.', '.', '.', '.', '.', '.', '.', '',   $dWallTopLeft, $dWallTop, $dWallTop, $dWallTop, $dWallTop, $dWallTop, $dWallTop, $dWallTop, $dWallTopRight, '.', $door, '.', ],
                [$dWallTop, $dWallTop, $dWallTop, $dWallTopRight, '.', $dWallTopLeft, $dWallTop, $dWallTop, $dWallTop, $dWallLeft, '.', '.', '.', '.', '.', '.', '.', $dWallRight, $dWallTop, $dWallTop, $dWallTop, $dWallTop, $dWallTop],

            ];

            // Tiles added left and right of the design. 48 x 32px covers 1536px per side,
            // so together with the design above this fills screens up to ~3700px wide.
            $dungeonEdge = 48;

            $dungeonColumns = max(array_map('count', $dungeon));

            // Turn the design into one flat list of tiles, left to right, top to bottom.
            // Every tile becomes the custom properties the .dungeon__tile class reads.
            $dungeonTiles = [];

            foreach ($dungeon as $row) {
                $row = array_pad($row, $dungeonColumns, '.');
                $row = array_merge(
                    array_fill(0, $dungeonEdge, $row[0]),
                    $row,
                    array_fill(0, $dungeonEdge, $row[$dungeonColumns - 1]),
                );

                foreach ($row as $tile) {
                    $tile = trim((string) $tile);

                    if ($tile === '' || $tile === '.') {
                        $dungeonTiles[] = null;

                        continue;
                    }

                    [$position, $color] = array_pad(preg_split('/\s+/', $tile, 2), 2, null);
                    [$tileX, $tileY] = array_pad(explode(',', $position), 2, 0);

                    $dungeonTiles[] = '--tile-x: ' . (int) $tileX . '; --tile-y: ' . (int) $tileY . ';'
                        . ($color ? ' --tile-color: ' . $color . ';' : '');
                }
            }

            $dungeonColumns += $dungeonEdge * 2;
        @endphp

        <footer class="dungeon">
            {{-- Rendered on one line on purpose: a full width dungeon is a few thousand tiles. --}}
            <div class="dungeon__grid" style="--dungeon-columns: {{ $dungeonColumns }};" aria-hidden="true">@foreach ($dungeonTiles as $tile)@if ($tile)<span class="dungeon__tile" style="{{ $tile }}"></span>@else<span></span>@endif @endforeach</div>
        </footer>
        @endif

        <script>
            (function () {
                var items = document.querySelectorAll('[data-trivia]');
                if (items.length) {
                    items[Math.floor(Math.random() * items.length)].style.display = '';
                }
            })();
        </script>
    </body>
</html>
