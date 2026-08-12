<?php

namespace Tests\Feature;

use App\Support\SocialImage;
use App\Support\SocialImageGenerator;
use Statamic\Facades\Entry;
use Tests\TestCase;

class SocialImageGeneratorTest extends TestCase
{
    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = storage_path('framework/testing/ssg-output');
        $this->deleteOutput();
    }

    protected function tearDown(): void
    {
        $this->deleteOutput();

        parent::tearDown();
    }

    private function deleteOutput(): void
    {
        $directory = $this->output.'/assets/pages';

        if (is_dir($directory)) {
            foreach (glob($directory.'/*.png') as $file) {
                unlink($file);
            }
        }
    }

    private function generator(): SocialImageGenerator
    {
        return new SocialImageGenerator(
            new SocialImage(
                titleFont: resource_path('fonts/IBMPlexMono-Bold.ttf'),
                chromeFont: resource_path('fonts/IBMPlexMono-Regular.ttf'),
                artwork: resource_path('img/og-background.png'),
                avatar: resource_path('img/casmo.png'),
            ),
            $this->output,
        );
    }

    public function test_it_writes_one_image_per_routed_entry(): void
    {
        $expected = Entry::query()->where('published', true)->get()
            ->filter(fn ($entry) => filled($entry->url()))
            ->count();

        $written = $this->generator()->generate();

        $this->assertSame($expected, $written);
        $this->assertCount($written, glob($this->output.'/assets/pages/*.png'));
    }

    public function test_it_names_each_image_after_its_entry_slug(): void
    {
        $this->generator()->generate();

        // Two entries with stable slugs: a post and the home page.
        $this->assertFileExists($this->output.'/assets/pages/nostalgia.png');
        $this->assertFileExists($this->output.'/assets/pages/home.png');

        [$width, $height] = getimagesize($this->output.'/assets/pages/home.png');
        $this->assertSame(1200, $width);
        $this->assertSame(630, $height);
    }

    public function test_it_skips_entries_that_have_no_url(): void
    {
        $unrouted = Entry::query()->where('collection', 'trivia')->get();

        $this->assertNotEmpty($unrouted, 'expected the trivia collection to still hold entries');

        $this->generator()->generate();

        foreach ($unrouted as $entry) {
            $this->assertFileDoesNotExist($this->output.'/assets/pages/'.$entry->slug().'.png');
        }
    }
}
