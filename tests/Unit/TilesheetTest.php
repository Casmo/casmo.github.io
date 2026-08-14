<?php

namespace Tests\Unit;

use App\Support\Tilesheet;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

class TilesheetTest extends TestCase
{
    private string $directory;

    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        // Not /tmp: the sandbox may block it. storage/framework/testing is gitignored.
        $this->directory = dirname(__DIR__, 2).'/storage/framework/testing/tilesheet';

        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }

        $this->destination = $this->directory.'/sheet.png';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*.png') as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    /**
     * A source PNG of the given size, filled with opaque white ink except for
     * the pixels named in $blank, which stay transparent.
     *
     * @param  list<array{int, int}>  $blank
     */
    private function source(string $name, int $width, int $height, array $blank = []): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $ink = imagecolorallocatealpha($image, 255, 255, 255, 0);
        $void = imagecolorallocatealpha($image, 0, 0, 0, 127);

        imagefill($image, 0, 0, $ink);

        foreach ($blank as [$x, $y]) {
            imagesetpixel($image, $x, $y, $void);
        }

        $path = $this->directory.'/'.$name.'.png';
        imagepng($image, $path);

        return $path;
    }

    /**
     * The written sheet as one string per row: '#' opaque white, '.' fully
     * transparent, '?' anything else.
     *
     * @return list<string>
     */
    private function map(string $path): array
    {
        $image = imagecreatefrompng($path);
        $rows = [];

        for ($y = 0; $y < imagesy($image); $y++) {
            $row = '';

            for ($x = 0; $x < imagesx($image); $x++) {
                $colour = imagecolorat($image, $x, $y);
                $alpha = ($colour >> 24) & 0x7F;

                $row .= match (true) {
                    $alpha === 127 => '.',
                    $alpha === 0 && ($colour & 0xFFFFFF) === 0xFFFFFF => '#',
                    default => '?',
                };
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function test_it_takes_the_cell_from_the_largest_source_in_both_axes(): void
    {
        // Widest is 3, tallest is 3, so the cell is 3x3 even though neither
        // source is that shape. Two cells plus one gutter is 7 wide.
        $written = (new Tilesheet)->write($this->destination, [
            $this->source('wide', 3, 2),
            $this->source('tall', 2, 3),
        ]);

        $this->assertSame(['width' => 7, 'height' => 3], $written);

        [$width, $height] = getimagesize($this->destination);
        $this->assertSame(7, $width);
        $this->assertSame(3, $height);
    }

    public function test_it_biases_an_odd_leftover_left_and_down(): void
    {
        // The 3x3 source is only there to set the cell size: a 2x2 source on
        // its own would give a 2x2 cell and nothing to bias. In a 3x3 cell the
        // 2x2 tile leaves one row and one column spare -- the empty column
        // goes right, the empty row goes on top.
        (new Tilesheet)->write($this->destination, [
            $this->source('small', 2, 2),
            $this->source('big', 3, 3),
        ]);

        $this->assertSame([
            '....###',
            '##..###',
            '##..###',
        ], $this->map($this->destination));
    }

    public function test_it_centres_an_even_leftover(): void
    {
        // A 2x2 source in a 4x4 cell leaves two spare rows and columns: one
        // each side, no bias to apply.
        (new Tilesheet)->write($this->destination, [
            $this->source('small', 2, 2),
            $this->source('big', 4, 4),
        ]);

        $this->assertSame([
            '.....####',
            '.##..####',
            '.##..####',
            '.....####',
        ], $this->map($this->destination));
    }

    public function test_it_wraps_after_ten_columns(): void
    {
        // 12 sources of 2x2: ten in the first row, two in the second.
        $sources = [];

        for ($i = 0; $i < 12; $i++) {
            $sources[] = $this->source('tile-'.$i, 2, 2);
        }

        $written = (new Tilesheet)->write($this->destination, $sources);

        // 10 * (2 + 1) - 1 = 29 wide, 2 * (2 + 1) - 1 = 5 tall.
        $this->assertSame(['width' => 29, 'height' => 5], $written);

        // The gutter row between the two rows of tiles is empty, and the
        // second row holds exactly two tiles.
        $map = $this->map($this->destination);
        $this->assertSame(str_repeat('.', 29), $map[2]);
        $this->assertSame('##.##'.str_repeat('.', 24), $map[3]);
    }

    public function test_it_copies_ink_and_transparency_verbatim(): void
    {
        // One transparent pixel inside an otherwise inked source has to
        // survive the copy, and the ink has to stay exactly opaque white.
        (new Tilesheet)->write($this->destination, [
            $this->source('holed', 3, 3, blank: [[1, 1]]),
        ]);

        $this->assertSame([
            '###',
            '#.#',
            '###',
        ], $this->map($this->destination));

        $image = imagecreatefrompng($this->destination);
        $ink = imagecolorat($image, 0, 0);

        $this->assertSame(0, ($ink >> 24) & 0x7F, 'ink should be fully opaque');
        $this->assertSame(0xFFFFFF, $ink & 0xFFFFFF, 'ink should be pure white');
    }

    public function test_it_writes_nothing_when_there_are_no_sources(): void
    {
        $this->assertNull((new Tilesheet)->write($this->destination, []));
        $this->assertFileDoesNotExist($this->destination);
    }

    #[WithoutErrorHandler]
    public function test_it_throws_when_a_source_cannot_be_read(): void
    {
        $missing = $this->directory.'/absent.png';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($missing);

        (new Tilesheet)->write($this->destination, [$missing]);
    }

    #[WithoutErrorHandler]
    public function test_it_throws_when_the_sheet_cannot_be_written(): void
    {
        $destination = $this->directory.'/no-such-directory/sheet.png';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($destination);

        (new Tilesheet)->write($destination, [$this->source('small', 2, 2)]);
    }
}
