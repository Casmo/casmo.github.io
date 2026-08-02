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
    <body class="bg-[#1f2329] leading-normal text-zinc-400">
        <div class="px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto overflow-x-clip">
            <div class="py-8">
                <statamic:nav:main>
                    <a class="text-lg text-lime-500 hover:text-lime-400" href="{{ $url }}">{{ $title }}</a>
                </statamic:nav:main>
            </div>

            @yield('body')

        </div>

        <div class="border-t border-[#432800] py-2 text-color-[#3f4650]">
            <statamic:collection:trivia as="entries">
                @foreach ($entries as $entry)
                    @php
                        $icon = $entry->icon;
                        $icon = ($icon instanceof \Statamic\Fields\Value) ? $icon->value() : $icon;
                        $icon = is_iterable($icon) ? collect($icon)->first() : $icon;
                    @endphp
                    <div class="text-center knowledge" data-trivia style="display: none;">
                        @if ($icon)
                            <img src="{{ $icon->url() }}" class="inline mr-2" height="{{ $icon->height() }}" width="{{ $icon->width() }}" style="image-rendering: pixelated;" />
                        @endif
                        {{ $entry->title }}
                    </div>
                @endforeach
            </statamic:collection:trivia>
        </div>


        @php
            /*
            | Dungeon footer
            |
            | public/assets/tilemap.png is a 20 x 20 grid of 16px tiles with 1px between them.
            | Every cell below is one tile, addressed as 'column,row' (zero based, from the top left
            | of the sheet). Optionally add a colour after a space to tint that tile: '0,12 #f87171'.
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
            */
            // Shorthands, so a repeated tile stays readable. A cell is just a string, so
            // array_fill(0, 20, $wall) is a quick way to fill a whole row.
            $wall = '15,18';
            $floor = '15,18 #4a525d';
            $rock = '15,18 #333a43';
            $hearthRed = '0,2 #f87171';
            $yellowKey = '16,4 #facc15';
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
                ['.', '.', '.', '.', $yellowKey, '.', '.', '.', '.', '.',   $monster1, '.', '.', '.', '.', '.', '.', '.',    '.', '.', ],
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
