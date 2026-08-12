<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromptLineTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'blog post' => ['/read/recover-deleted-uploadcare-files-in-laravel', '~/blog/laravel', 'recover-deleted-uploadcare-files-in-laravel.txt'],
            'review' => ['/reviews/forest-feeding-frenzy', '~/reviews', 'forest-feeding-frenzy.txt'],
            'blog index' => ['/blog', '~/blog', 'notes.txt'],
            'reviews index' => ['/reviews', '~/reviews', 'reviews.txt'],
            'resources' => ['/resources', '~/resources', 'useful-stuff.txt'],
            'home' => ['/', '~/about', 'introduction.txt'],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_page_prompt_names_its_own_path_and_file(string $url, string $path, string $file): void
    {
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('>:'.$path.'<', false);
        $response->assertSee('cat '.$file, false);
    }
}
