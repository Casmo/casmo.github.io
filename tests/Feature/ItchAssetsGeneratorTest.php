<?php

namespace Tests\Feature;

use App\Support\Files;
use App\Support\ItchAssetsGenerator;
use App\Support\TriviaIcons;
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
            new TriviaIcons,
            new Files,
            base_path('public/assets/trivia-tilesheet.png'),
            $output ?? $this->output,
        );
    }

    public function test_it_writes_an_upscale_of_the_sheet_and_of_every_icon(): void
    {
        $written = $this->generator()->generate();

        // 11 icons plus the sheet.
        $this->assertSame(12, $written);

        $this->assertFileExists($this->output.'/itch/trivia-tilesheet-4x.png');
        $this->assertFileExists($this->output.'/itch/icons/volfied-1bit-dos-game-4x.png');
        $this->assertCount(11, glob($this->output.'/itch/icons/*.png'));
    }

    public function test_it_scales_the_sheet_by_four(): void
    {
        // The committed sheet is 169x29 with 11 icons (2026-08-22). Update
        // alongside TilesheetGeneratorTest when an icon is added.
        $this->generator()->generate();

        $size = getimagesize($this->output.'/itch/trivia-tilesheet-4x.png');

        $this->assertSame(169 * 4, $size[0]);
        $this->assertSame(29 * 4, $size[1]);
    }

    public function test_it_scales_each_icon_by_four(): void
    {
        $this->generator()->generate();

        $source = getimagesize(base_path('public/assets/trivia/volfied-1bit-dos-game.png'));
        $scaled = getimagesize($this->output.'/itch/icons/volfied-1bit-dos-game-4x.png');

        $this->assertSame($source[0] * 4, $scaled[0]);
        $this->assertSame($source[1] * 4, $scaled[1]);
    }

    public function test_it_writes_nothing_into_public(): void
    {
        // These images are never shown on the site and public/assets is a
        // Statamic container -- anything landing there would want a .meta
        // yaml committed beside it. They exist only for itch.io to hotlink.
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
        // Task 6 builds page URLs from this, so a drift here is a page of
        // broken images.
        $this->assertSame(
            'volfied-1bit-dos-game-4x.png',
            ItchAssetsGenerator::filename('/anywhere/volfied-1bit-dos-game.png'),
        );
    }

    public function test_it_throws_when_the_output_cannot_be_written(): void
    {
        $this->expectException(\RuntimeException::class);

        // A file where the directory needs to be: mkdir cannot succeed.
        $blocked = storage_path('framework/testing/itch-blocked');
        @mkdir(dirname($blocked), 0755, true);
        file_put_contents($blocked, 'not a directory');

        try {
            $this->generator($blocked)->generate();
        } finally {
            unlink($blocked);
        }
    }
}
