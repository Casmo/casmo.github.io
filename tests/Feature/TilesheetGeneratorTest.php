<?php

namespace Tests\Feature;

use App\Support\Tilesheet;
use App\Support\TilesheetGenerator;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Facades\YAML;
use Tests\TestCase;

class TilesheetGeneratorTest extends TestCase
{
    private string $public;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        // A throwaway stand-in for public/, so the test never overwrites the
        // committed asset, and for the SSG output directory.
        $this->public = storage_path('framework/testing/tilesheet-public');
        $this->output = storage_path('framework/testing/tilesheet-output');

        $this->deleteDirectories();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectories();

        parent::tearDown();
    }

    private function deleteDirectories(): void
    {
        foreach ([$this->public, $this->output] as $root) {
            foreach ([$root.'/assets/.meta', $root.'/assets', $root] as $directory) {
                if (! is_dir($directory)) {
                    continue;
                }

                foreach (glob($directory.'/*.{png,yaml}', GLOB_BRACE) as $file) {
                    unlink($file);
                }

                @rmdir($directory);
            }
        }
    }

    private function generator(): TilesheetGenerator
    {
        return new TilesheetGenerator(new Tilesheet, $this->public, $this->output);
    }

    private function sheet(): string
    {
        return $this->public.'/assets/trivia-tilesheet.png';
    }

    /** An entry-shaped stub: sources() only ever reads ->icon. */
    private function entryWithIcon(?string $path): object
    {
        return new class($path === null ? null : Asset::find('assets::'.$path))
        {
            public function __construct(public $icon) {}
        };
    }

    public function test_it_writes_the_sheet_for_every_trivia_icon(): void
    {
        // Pinned rather than measured from the icons the generator itself
        // picked, so a broken query can't move both sides of the assertion.
        // 8 icons today (2026-08-14), widest 15px (scorched-earth), tallest
        // 14px (commander-keen): 8 * (15 + 1) - 1 = 127. Update when an icon
        // is added or one of those two extremes changes.
        $written = $this->generator()->generate();

        $this->assertSame(['width' => 127, 'height' => 14], $written);
        $this->assertFileExists($this->sheet());
    }

    public function test_it_writes_the_build_copy_as_well_as_the_asset(): void
    {
        // copyFiles() runs before SSG::after, so the sheet has to be put in
        // the output directory too or it would ship one deploy late.
        $this->generator()->generate();

        $this->assertFileExists($this->sheet());
        $this->assertFileExists($this->output.'/assets/trivia-tilesheet.png');
        $this->assertFileEquals($this->sheet(), $this->output.'/assets/trivia-tilesheet.png');
    }

    public function test_it_writes_asset_meta_describing_the_file_on_disk(): void
    {
        $this->generator()->generate();

        $path = $this->public.'/assets/.meta/trivia-tilesheet.png.yaml';
        $this->assertFileExists($path);

        $meta = YAML::parse(file_get_contents($path));

        $this->assertSame([], $meta['data']);
        $this->assertSame(127, $meta['width']);
        $this->assertSame(14, $meta['height']);
        $this->assertSame('image/png', $meta['mime_type']);
        $this->assertNull($meta['duration']);
        $this->assertSame(filesize($this->sheet()), $meta['size']);
        $this->assertSame(filemtime($this->sheet()), $meta['last_modified']);
    }

    public function test_it_orders_the_tiles_the_way_the_fortunes_are_ordered(): void
    {
        // Trivia is neither dated nor orderable, so the collection sorts by
        // title ascending -- bio-menace first, volfied last. A bare query
        // would return stache order instead, so this pins the first and last
        // tile pixel for pixel against its source.
        $this->generator()->generate();

        $this->assertTileMatches(0, 'public/assets/trivia/1-bit-bio-menace-msdos-game.png');
        $this->assertTileMatches(7, 'public/assets/trivia/volfied-1bit-dos-game.png');
    }

    /** Every pixel of the source appears at tile $index's computed offset. */
    private function assertTileMatches(int $index, string $source): void
    {
        $cellWidth = 15;
        $cellHeight = 14;

        $sheet = imagecreatefrompng($this->sheet());
        $icon = imagecreatefrompng(base_path($source));

        $width = imagesx($icon);
        $height = imagesy($icon);

        $left = $index * ($cellWidth + 1) + intdiv($cellWidth - $width, 2);
        $top = (int) ceil(($cellHeight - $height) / 2);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $this->assertSame(
                    imagecolorat($icon, $x, $y),
                    imagecolorat($sheet, $left + $x, $top + $y),
                    "tile {$index} differs at {$x},{$y}",
                );
            }
        }
    }

    public function test_it_resolves_one_absolute_path_per_icon(): void
    {
        $paths = $this->generator()->sources(
            Entry::query()->where('collection', 'trivia')->get()
        );

        $this->assertCount(8, $paths);

        foreach ($paths as $path) {
            $this->assertFileExists($path);
            $this->assertStringStartsWith(base_path('public/assets/trivia/'), $path);
        }
    }

    public function test_it_skips_entries_that_have_no_icon(): void
    {
        // Pages carry no icon field at all.
        $pages = Entry::query()->where('collection', 'pages')->get();

        $this->assertNotEmpty($pages, 'expected the pages collection to still hold entries');

        $this->assertSame([], $this->generator()->sources($pages));
    }

    public function test_it_collapses_two_entries_sharing_one_icon(): void
    {
        $paths = $this->generator()->sources([
            $this->entryWithIcon('trivia/volfied-1bit-dos-game.png'),
            $this->entryWithIcon('trivia/quake-1-pixel-logo.png'),
            $this->entryWithIcon('trivia/volfied-1bit-dos-game.png'),
            $this->entryWithIcon(null),
        ]);

        $this->assertSame([
            base_path('public/assets/trivia/volfied-1bit-dos-game.png'),
            base_path('public/assets/trivia/quake-1-pixel-logo.png'),
        ], $paths);
    }
}
