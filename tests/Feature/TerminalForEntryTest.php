<?php

namespace Tests\Feature;

use App\Support\Terminal;
use Statamic\Facades\Entry;
use Tests\TestCase;

class TerminalForEntryTest extends TestCase
{
    /** Entries are made, never saved, so nothing is written to content/. */
    private function entry(string $collection, string $slug, array $data)
    {
        return Entry::make()->collection($collection)->slug($slug)->data($data);
    }

    public function test_a_blog_entry_sits_in_its_category_directory(): void
    {
        $entry = $this->entry('blog', 'recover-deleted-uploadcare-files-in-laravel', [
            'title' => 'Recover deleted Uploadcare files in Laravel',
            'categories' => ['laravel'],
        ]);

        $this->assertSame([
            'path' => '~/blog/laravel',
            'file' => 'recover-deleted-uploadcare-files-in-laravel.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_a_blog_entry_without_a_category_falls_back_to_the_blog_directory(): void
    {
        $entry = $this->entry('blog', 'nostalgia', ['title' => 'Nostalgia']);

        $this->assertSame([
            'path' => '~/blog',
            'file' => 'nostalgia.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_a_review_sits_in_the_reviews_directory(): void
    {
        $entry = $this->entry('games', 'forest-feeding-frenzy', ['title' => 'Forest Feeding Frenzy']);

        $this->assertSame([
            'path' => '~/reviews',
            'file' => 'forest-feeding-frenzy.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_a_page_takes_its_directory_from_its_title(): void
    {
        $entry = $this->entry('pages', 'home', ['title' => 'About']);

        $this->assertSame([
            'path' => '~/about',
            'file' => 'home.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_the_overrides_win(): void
    {
        $entry = $this->entry('pages', 'home', [
            'title' => 'About',
            'terminal_path' => '~/somewhere/else',
            'terminal_file' => 'introduction.txt',
        ]);

        $this->assertSame([
            'path' => '~/somewhere/else',
            'file' => 'introduction.txt',
        ], Terminal::forEntry($entry));
    }

    public function test_a_file_override_without_an_extension_still_gets_one(): void
    {
        $entry = $this->entry('pages', 'resources', [
            'title' => 'Resources',
            'terminal_file' => 'useful-stuff',
        ]);

        $this->assertSame('useful-stuff.txt', Terminal::forEntry($entry)['file']);
    }
}
