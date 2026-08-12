<?php

namespace Tests\Unit;

use App\Support\SocialImage;
use PHPUnit\Framework\TestCase;

class SocialImageWriteTest extends TestCase
{
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        // Not /tmp: the sandbox may block it. storage/framework/testing is gitignored.
        $directory = dirname(__DIR__, 2).'/storage/framework/testing/social-images';

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->destination = $directory.'/card.png';
    }

    protected function tearDown(): void
    {
        if (is_file($this->destination)) {
            unlink($this->destination);
        }

        parent::tearDown();
    }

    private function image(): SocialImage
    {
        $resources = dirname(__DIR__, 2).'/resources';

        return new SocialImage(
            titleFont: $resources.'/fonts/IBMPlexMono-Bold.ttf',
            chromeFont: $resources.'/fonts/IBMPlexMono-Regular.ttf',
            artwork: $resources.'/img/og-background.png',
            avatar: $resources.'/img/casmo.png',
        );
    }

    /** @return array<int, int> colour => pixel count */
    private function colourCounts(string $path): array
    {
        $image = imagecreatefrompng($path);
        $counts = [];

        for ($y = 0; $y < SocialImage::HEIGHT; $y++) {
            for ($x = 0; $x < SocialImage::WIDTH; $x++) {
                $colour = imagecolorat($image, $x, $y);
                $counts[$colour] = ($counts[$colour] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function test_it_writes_an_open_graph_sized_png(): void
    {
        $this->image()->write(
            $this->destination,
            'The interface of the future',
            'mathieu@laptop:~/blog/design$ cat the-interface-of-the-future.txt',
            'mathieuderuiter.nl',
            'August 1, 2026',
        );

        $this->assertFileExists($this->destination);

        [$width, $height, $type] = getimagesize($this->destination);

        $this->assertSame(SocialImage::WIDTH, $width);
        $this->assertSame(SocialImage::HEIGHT, $height);
        $this->assertSame(IMAGETYPE_PNG, $type);
    }

    public function test_it_paints_every_band_in_its_own_colour(): void
    {
        $this->image()->write(
            $this->destination,
            'The interface of the future',
            'mathieu@laptop:~/blog/design$ cat the-interface-of-the-future.txt',
            'mathieuderuiter.nl',
            'August 1, 2026',
        );

        $counts = $this->colourCounts($this->destination);

        // Glyph interiors land on the exact allocated colour; antialiased edges do not.
        $this->assertGreaterThan(10000, $counts[0xE4E4E7] ?? 0, 'title');
        $this->assertGreaterThan(1000, $counts[0x84CC16] ?? 0, 'prompt line');
        $this->assertGreaterThan(500, $counts[0x6B7280] ?? 0, 'identity line');
        $this->assertGreaterThan(500000, $counts[0x1F2329] ?? 0, 'background');
    }

    public function test_it_writes_a_card_without_a_date(): void
    {
        $this->image()->write(
            $this->destination,
            'Nostalgia',
            'mathieu@laptop:~/blog$ cat nostalgia.txt',
            'mathieuderuiter.nl',
            null,
        );

        [$width, $height] = getimagesize($this->destination);

        $this->assertSame(SocialImage::WIDTH, $width);
        $this->assertSame(SocialImage::HEIGHT, $height);
    }

    public function test_a_short_prompt_is_left_alone(): void
    {
        $this->assertSame(
            'mathieu@laptop:~/reviews$ cat volfied.txt',
            $this->image()->prompt('mathieu@laptop', '~/reviews', 'volfied.txt'),
        );
    }

    public function test_a_long_filename_is_elided_to_fit(): void
    {
        $this->assertSame(
            'mathieu@laptop:~/blog/php$ cat composer-update-hanging-on….txt',
            $this->image()->prompt(
                'mathieu@laptop',
                '~/blog/php',
                'composer-update-hanging-on-loading-composer-repositories-with-package-information.txt',
            ),
        );
    }

    public function test_a_deep_path_that_already_fits_is_left_alone(): void
    {
        $this->assertSame(
            'mathieu@laptop:~/blog/a/b/c/d/e/f$ cat x.txt',
            $this->image()->prompt('mathieu@laptop', '~/blog/a/b/c/d/e/f', 'x.txt'),
        );
    }

    public function test_a_long_path_collapses_to_its_last_segment(): void
    {
        $this->assertSame(
            'mathieu@laptop:~/…/nested-directory$ cat x.txt',
            $this->image()->prompt(
                'mathieu@laptop',
                '~/blog/'.str_repeat('nested-directory/', 6),
                'x.txt',
            ),
        );
    }

    public function test_an_unshrinkable_line_is_truncated_to_the_measure(): void
    {
        $prompt = $this->image()->prompt(
            'mathieu@laptop',
            '~/'.str_repeat('z', 200),
            'x.txt',
        );

        $this->assertStringEndsWith('…', $prompt);

        $resources = dirname(__DIR__, 2).'/resources';
        $box = imagettfbbox(22, 0, $resources.'/fonts/IBMPlexMono-Regular.ttf', $prompt);
        $width = abs($box[2] - $box[0]);

        $this->assertLessThanOrEqual(1056, $width);
    }

    public function test_the_cursor_is_drawn_when_the_last_row_leaves_room(): void
    {
        $image = $this->image();
        $fit = $image->fit('Nostalgia');

        $this->assertTrue($image->cursorFits($fit['size'], end($fit['lines'])));
    }

    public function test_the_cursor_is_dropped_when_it_would_cross_the_margin(): void
    {
        $image = $this->image();
        $fit = $image->fit('The interface of the future');

        $this->assertFalse($image->cursorFits($fit['size'], end($fit['lines'])));
    }

    public function test_a_missing_asset_fails_loudly(): void
    {
        $resources = dirname(__DIR__, 2).'/resources';

        $image = new SocialImage(
            titleFont: $resources.'/fonts/IBMPlexMono-Bold.ttf',
            chromeFont: $resources.'/fonts/IBMPlexMono-Regular.ttf',
            artwork: $resources.'/img/does-not-exist.png',
            avatar: $resources.'/img/casmo.png',
        );

        $this->expectException(\RuntimeException::class);

        $image->write($this->destination, 'Nostalgia', 'mathieu@laptop:~$ cat nostalgia.txt', 'mathieuderuiter.nl', null);
    }
}
