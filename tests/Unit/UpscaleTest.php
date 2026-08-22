<?php

namespace Tests\Unit;

use App\Support\Upscale;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

class UpscaleTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        // Not /tmp: the sandbox may block it. storage/framework/testing is gitignored.
        $this->directory = dirname(__DIR__, 2).'/storage/framework/testing/upscale';

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*.png') as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    /**
     * A source PNG whose pixels are given as [x, y] => [r, g, b, alpha],
     * alpha in GD's 0 (opaque) to 127 (transparent) range.
     *
     * @param  array<int, array{int, int, int, int, int}>  $pixels  [x, y, r, g, b, a]
     */
    private function source(string $name, int $width, int $height, array $pixels): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

        foreach ($pixels as [$x, $y, $r, $g, $b, $a]) {
            imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $a));
        }

        $path = $this->directory.'/'.$name.'.png';
        imagepng($image, $path);

        return $path;
    }

    /** @return array{int, int, int, int} [r, g, b, alpha] at that pixel */
    private function pixel(string $path, int $x, int $y): array
    {
        $colour = imagecolorat(imagecreatefrompng($path), $x, $y);

        return [
            ($colour >> 16) & 0xFF,
            ($colour >> 8) & 0xFF,
            $colour & 0xFF,
            ($colour >> 24) & 0x7F,
        ];
    }

    public function test_it_scales_the_dimensions_by_the_factor(): void
    {
        $source = $this->source('dims', 3, 5, [[0, 0, 255, 255, 255, 0]]);
        $destination = $this->directory.'/dims-out.png';

        $written = (new Upscale)->write($destination, $source, 4);

        $this->assertSame(['width' => 12, 'height' => 20], $written);
        $this->assertFileExists($destination);
    }

    public function test_it_turns_every_source_pixel_into_a_solid_block(): void
    {
        // Dimensions alone would pass with interpolation, which is the failure
        // this class exists to avoid. Assert the far corner of one block and
        // the near corner of its neighbour: under interpolation the boundary
        // pixels would blend toward each other instead of stepping.
        $source = $this->source('blocks', 2, 1, [
            [0, 0, 255, 255, 255, 0],
            [1, 0, 255, 0, 0, 0],
        ]);
        $destination = $this->directory.'/blocks-out.png';

        (new Upscale)->write($destination, $source, 4);

        $this->assertSame([255, 255, 255, 0], $this->pixel($destination, 0, 0));
        $this->assertSame([255, 255, 255, 0], $this->pixel($destination, 3, 3));
        $this->assertSame([255, 0, 0, 0], $this->pixel($destination, 4, 0));
        $this->assertSame([255, 0, 0, 0], $this->pixel($destination, 7, 3));
    }

    public function test_it_preserves_alpha_verbatim(): void
    {
        $source = $this->source('alpha', 2, 1, [
            [0, 0, 0, 0, 0, 127],
            [1, 0, 0, 255, 0, 63],
        ]);
        $destination = $this->directory.'/alpha-out.png';

        (new Upscale)->write($destination, $source, 4);

        // Transparent stays fully transparent across its whole block.
        $this->assertSame(127, $this->pixel($destination, 0, 0)[3]);
        $this->assertSame(127, $this->pixel($destination, 3, 3)[3]);

        // Partial alpha is neither flattened to opaque nor blended.
        $this->assertSame([0, 255, 0, 63], $this->pixel($destination, 4, 0));
        $this->assertSame([0, 255, 0, 63], $this->pixel($destination, 7, 3));
    }

    public function test_a_factor_of_one_copies_the_image(): void
    {
        $source = $this->source('one', 2, 2, [[0, 0, 255, 255, 255, 0]]);
        $destination = $this->directory.'/one-out.png';

        $written = (new Upscale)->write($destination, $source, 1);

        $this->assertSame(['width' => 2, 'height' => 2], $written);
        $this->assertSame([255, 255, 255, 0], $this->pixel($destination, 0, 0));
    }

    public function test_it_rejects_a_factor_below_one(): void
    {
        $source = $this->source('bad', 2, 2, [[0, 0, 255, 255, 255, 0]]);

        foreach ([0, -1] as $factor) {
            try {
                (new Upscale)->write($this->directory.'/bad-out.png', $source, $factor);
                $this->fail("expected factor {$factor} to be rejected");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString((string) $factor, $e->getMessage());
            }
        }
    }

    #[WithoutErrorHandler]
    public function test_it_throws_when_the_source_cannot_be_read(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/missing\.png/');

        (new Upscale)->write(
            $this->directory.'/out.png',
            $this->directory.'/missing.png',
            4,
        );
    }
}
