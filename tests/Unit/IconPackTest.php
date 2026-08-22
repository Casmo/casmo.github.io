<?php

namespace Tests\Unit;

use App\Support\Files;
use App\Support\IconPack;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\RemovesDirectories;

class IconPackTest extends TestCase
{
    use RemovesDirectories;

    private string $directory;

    private string $staging;

    protected function setUp(): void
    {
        parent::setUp();

        // Not /tmp: the sandbox may block it. storage/framework/testing is gitignored.
        $this->directory = dirname(__DIR__, 2).'/storage/framework/testing/icon-pack';
        $this->staging = $this->directory.'/pack';

        $this->remove($this->directory);
        mkdir($this->directory, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);

        parent::tearDown();
    }

    private function pack(): IconPack
    {
        return new IconPack(new Files);
    }

    /** A 2x2 opaque white PNG at $name.png, returned as an absolute path. */
    private function png(string $name): string
    {
        $image = imagecreatetruecolor(2, 2);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 0));

        $path = $this->directory.'/'.$name.'.png';
        imagepng($image, $path);

        return $path;
    }

    /** @return list<array{path: string, title: string}> */
    private function icons(): array
    {
        return [
            ['path' => $this->png('alley-cat'), 'title' => 'Alley Cat has a blue-pink palette.'],
            ['path' => $this->png('volfied'), 'title' => 'Volfied had an escaping snake.'],
        ];
    }

    public function test_it_stages_the_icons_the_sheet_and_the_readme(): void
    {
        $sheet = $this->png('sheet');

        $count = $this->pack()->write($this->staging, $this->icons(), $sheet);

        $this->assertSame(2, $count);
        $this->assertFileExists($this->staging.'/icons/alley-cat.png');
        $this->assertFileExists($this->staging.'/icons/volfied.png');
        $this->assertFileExists($this->staging.'/trivia-tilesheet.png');
        $this->assertFileExists($this->staging.'/README.txt');
    }

    public function test_it_ships_the_icons_byte_identical_to_their_sources(): void
    {
        // The pack is the real pixels. The upscales exist for the page, and a
        // buyer dropping a 4x PNG into a tilemap editor would be getting a
        // lossy version of what they paid for.
        $icons = $this->icons();

        $this->pack()->write($this->staging, $icons, $this->png('sheet'));

        foreach ($icons as $icon) {
            $this->assertFileEquals(
                $icon['path'],
                $this->staging.'/icons/'.basename($icon['path']),
            );
        }
    }

    public function test_the_readme_pairs_each_filename_with_its_own_title(): void
    {
        $this->pack()->write($this->staging, $this->icons(), $this->png('sheet'));

        $readme = file_get_contents($this->staging.'/README.txt');

        $this->assertMatchesRegularExpression(
            '/alley-cat\.png\s+Alley Cat has a blue-pink palette\./',
            $readme,
        );
        $this->assertMatchesRegularExpression(
            '/volfied\.png\s+Volfied had an escaping snake\./',
            $readme,
        );
    }

    public function test_the_readme_lists_the_icons_in_the_order_given(): void
    {
        $this->pack()->write($this->staging, $this->icons(), $this->png('sheet'));

        $readme = file_get_contents($this->staging.'/README.txt');

        $this->assertLessThan(
            strpos($readme, 'volfied.png'),
            strpos($readme, 'alley-cat.png'),
            'the README should read in sheet order',
        );
    }

    public function test_it_writes_nothing_but_the_pack(): void
    {
        // Anything else in here ships to buyers. page.html in particular is
        // written beside the staging directory, never inside it.
        $this->pack()->write($this->staging, $this->icons(), $this->png('sheet'));

        $entries = array_values(array_diff(scandir($this->staging), ['.', '..']));
        sort($entries);

        $this->assertSame(['README.txt', 'icons', 'trivia-tilesheet.png'], $entries);
    }

    public function test_it_replaces_a_previous_staging_directory(): void
    {
        // A stale icon left from an earlier run would ship to buyers as part
        // of the download.
        mkdir($this->staging.'/icons', 0755, true);
        file_put_contents($this->staging.'/icons/removed-icon.png', 'stale');

        $this->pack()->write($this->staging, $this->icons(), $this->png('sheet'));

        $this->assertFileDoesNotExist($this->staging.'/icons/removed-icon.png');
    }

    public function test_it_throws_when_an_icon_cannot_be_read(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing\.png/');

        $this->pack()->write(
            $this->staging,
            [['path' => $this->directory.'/missing.png', 'title' => 'gone']],
            $this->png('sheet'),
        );
    }
}
