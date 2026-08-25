<?php

namespace Tests\Feature;

use App\Support\Files;
use App\Support\ItchAssetsGenerator;
use App\Support\Upscale;
use Tests\Concerns\RemovesDirectories;
use Tests\TestCase;

class ItchAssetsGeneratorTest extends TestCase
{
    use RemovesDirectories;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = storage_path('framework/testing/itch-output');

        $this->remove($this->output);
    }

    protected function tearDown(): void
    {
        $this->remove($this->output);

        parent::tearDown();
    }

    private function generator(?string $output = null): ItchAssetsGenerator
    {
        return new ItchAssetsGenerator(
            new Upscale,
            new Files,
            base_path('public/assets/trivia-tilesheet.png'),
            $output ?? $this->output,
        );
    }

    public function test_it_writes_an_upscale_of_the_sheet(): void
    {
        $written = $this->generator()->generate();

        // The committed sheet is 169x29 with 12 icons (2026-08-25). Update
        // alongside TilesheetGeneratorTest when an icon is added.
        $this->assertSame(['width' => 169 * 4, 'height' => 29 * 4], $written);

        $this->assertFileExists($this->output.'/itch/trivia-tilesheet-4x.png');

        $size = getimagesize($this->output.'/itch/trivia-tilesheet-4x.png');

        $this->assertSame(169 * 4, $size[0]);
        $this->assertSame(29 * 4, $size[1]);
    }

    public function test_it_derives_nothing_per_icon(): void
    {
        // The page links the icons where the site already serves them, at the
        // size they were drawn, so there is nothing per-icon to publish. A
        // stray icons/ directory here would mean the upscaling came back.
        $this->generator()->generate();

        $this->assertDirectoryDoesNotExist($this->output.'/itch/icons');

        $this->assertSame(
            ['/itch/trivia-tilesheet-4x.png'],
            $this->snapshot($this->output),
        );
    }

    public function test_it_writes_nothing_into_public(): void
    {
        // This image is never shown on the site and public/assets is a
        // Statamic container -- anything landing there would want a .meta
        // yaml committed beside it. It exists only for itch.io to hotlink.
        $before = $this->snapshot(base_path('public/assets'));

        $this->generator()->generate();

        $this->assertSame($before, $this->snapshot(base_path('public/assets')));
        $this->assertDirectoryDoesNotExist(base_path('public/itch'));
    }

    /** @return list<string> every file under $root, relative and sorted */
    private function snapshot(string $root): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $files[] = substr($file->getPathname(), strlen($root));
        }

        sort($files);

        return $files;
    }

    public function test_filename_appends_the_factor_suffix(): void
    {
        // ItchPage builds the hero's URL from this, so a drift here is a
        // broken image at the top of the page.
        $this->assertSame(
            'volfied-1bit-dos-game-4x.png',
            ItchAssetsGenerator::filename('/anywhere/volfied-1bit-dos-game.png'),
        );
    }

    public function test_it_throws_when_the_output_cannot_be_written(): void
    {
        // A file where the directory needs to be: mkdir cannot succeed.
        $blocked = storage_path('framework/testing/itch-blocked');
        @mkdir(dirname($blocked), 0755, true);
        file_put_contents($blocked, 'not a directory');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($blocked, '/').'/');

        try {
            $this->generator($blocked)->generate();
        } finally {
            unlink($blocked);
        }
    }
}
