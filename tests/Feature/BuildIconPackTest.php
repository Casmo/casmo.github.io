<?php

namespace Tests\Feature;

use App\Support\Files;
use App\Support\IconPack;
use App\Support\ItchAssetsGenerator;
use App\Support\TilesheetGenerator;
use App\Support\TriviaIcons;
use App\Support\Upscale;
use Tests\Concerns\RemovesDirectories;
use Tests\TestCase;

class BuildIconPackTest extends TestCase
{
    use RemovesDirectories;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        $this->out = storage_path('framework/testing/itch-pack');

        $this->remove($this->out);
    }

    protected function tearDown(): void
    {
        $this->remove($this->out);

        parent::tearDown();
    }

    private function runCommand(): void
    {
        $this->artisan('trivia:pack', [
            '--out' => $this->out,
            '--base-url' => 'https://example.test',
        ])->assertSuccessful();
    }

    public function test_it_writes_the_pack_and_the_page(): void
    {
        $this->runCommand();

        $this->assertDirectoryExists($this->out.'/pack/icons');
        $this->assertFileExists($this->out.'/pack/trivia-tilesheet.png');
        $this->assertFileExists($this->out.'/pack/README.txt');
        $this->assertFileExists($this->out.'/page.html');
        $this->assertCount(11, glob($this->out.'/pack/icons/*.png'));
    }

    public function test_the_page_is_not_inside_the_pack(): void
    {
        // Anything under pack/ ships to buyers.
        $this->runCommand();

        $this->assertFileDoesNotExist($this->out.'/pack/page.html');

        $entries = array_values(array_diff(scandir($this->out.'/pack'), ['.', '..']));
        sort($entries);

        $this->assertSame(['README.txt', 'icons', 'trivia-tilesheet.png'], $entries);
    }

    public function test_it_regenerates_the_sheet_rather_than_copying_the_committed_one(): void
    {
        // The committed asset went three icons stale once already. The pack
        // depends on the collection, never on that file, so a stale committed
        // sheet cannot reach a buyer.
        //
        // Proven, not just asserted: the committed asset is moved out of the
        // way before the command runs. An implementation that copied it
        // instead of regenerating it would fail outright with the source
        // gone, where getimagesize() dimensions alone would pass unchanged
        // either way (the committed file happens to already be 169x29).
        $committed = base_path('public/assets/trivia-tilesheet.png');
        $displaced = storage_path('framework/testing/trivia-tilesheet.png.displaced-for-test');

        rename($committed, $displaced);

        try {
            $this->runCommand();
        } finally {
            rename($displaced, $committed);
        }

        $this->assertFileExists($committed, 'the committed asset must be restored regardless of the outcome above');

        $packed = getimagesize($this->out.'/pack/trivia-tilesheet.png');

        // 11 icons, cell 16x14, ten columns: 169x29.
        $this->assertSame(169, $packed[0]);
        $this->assertSame(29, $packed[1]);
        $this->assertCount(11, glob($this->out.'/pack/icons/*.png'));
    }

    public function test_every_image_the_page_references_is_one_the_generator_writes(): void
    {
        // The only test that crosses ItchPage and ItchAssetsGenerator, and so
        // the only one that catches a drift in the -4x naming between them.
        $this->runCommand();

        $html = file_get_contents($this->out.'/page.html');

        preg_match_all('/src="([^"]+)"/', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        $output = storage_path('framework/testing/itch-pack-assets');
        $this->remove($output);

        (new ItchAssetsGenerator(
            new Upscale,
            new TriviaIcons,
            new Files,
            base_path('public/assets/trivia-tilesheet.png'),
            $output,
        ))->generate();

        foreach ($matches[1] as $url) {
            $relative = parse_url($url, PHP_URL_PATH);

            $this->assertFileExists(
                $output.$relative,
                "the page references [{$url}] but nothing publishes it",
            );
        }

        $this->remove($output);
    }

    public function test_the_page_uses_the_base_url_it_was_given(): void
    {
        $this->runCommand();

        $html = file_get_contents($this->out.'/page.html');

        $this->assertStringContainsString(
            'https://example.test/'.ItchAssetsGenerator::DIRECTORY.'/',
            $html,
        );
        $this->assertStringContainsString(IconPack::ICONS.'/', $html);
    }

    public function test_the_pack_and_the_generator_agree_on_the_sheet_filename(): void
    {
        // Task 4's review flagged this: both constants are today the string
        // 'trivia-tilesheet.png', but nothing pins them equal. If FILENAME
        // were renamed, ItchPage (which derives the published name from the
        // path it is handed) would follow the rename while IconPack (a fixed
        // constant) would silently keep stamping the old name into the
        // shipped pack. A rename of one must update both.
        $this->assertSame(TilesheetGenerator::FILENAME, IconPack::TILESHEET);
    }
}
