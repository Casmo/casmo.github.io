<?php

namespace Tests\Unit;

use App\Support\ItchPage;
use PHPUnit\Framework\TestCase;

class ItchPageTest extends TestCase
{
    /** @return list<array{path: string, title: string}> */
    private function icons(): array
    {
        return [
            ['path' => '/x/alley-cat-dos-game-1bit.png', 'title' => 'Alley Cat & its palette'],
            ['path' => '/x/globliiins-1bit-ms-dos-game.png', 'title' => 'The three "i" are goblins'],
        ];
    }

    private function render(string $base = 'https://example.test'): string
    {
        return (new ItchPage)->render(
            $this->icons(),
            $base,
            '/x/trivia-tilesheet.png',
        );
    }

    public function test_it_leads_with_the_tilesheet(): void
    {
        $html = $this->render();

        $sheet = strpos($html, 'trivia-tilesheet-4x.png');
        $list = strpos($html, '<ul>');

        $this->assertNotFalse($sheet);
        $this->assertNotFalse($list);
        $this->assertLessThan($list, $sheet, 'the sheet should precede the list');
    }

    public function test_it_builds_absolute_urls_from_the_base(): void
    {
        $html = $this->render('https://mathieuderuiter.nl');

        $this->assertStringContainsString(
            'src="https://mathieuderuiter.nl/itch/trivia-tilesheet-4x.png"',
            $html,
        );
        $this->assertStringContainsString(
            'src="https://mathieuderuiter.nl/itch/icons/alley-cat-dos-game-1bit-4x.png"',
            $html,
        );
    }

    public function test_it_trims_a_trailing_slash_from_the_base(): void
    {
        $html = $this->render('https://example.test/');

        $this->assertStringContainsString('https://example.test/itch/', $html);
        $this->assertStringNotContainsString('https://example.test//itch/', $html);
    }

    public function test_it_lists_one_item_per_icon_paired_with_its_title(): void
    {
        $html = $this->render();

        $this->assertSame(2, substr_count($html, '<li>'));

        // The pairing, not merely the presence of both: an off-by-one zip
        // would put the wrong fact beside an icon.
        $this->assertMatchesRegularExpression(
            '#<li><img src="[^"]*alley-cat-dos-game-1bit-4x\.png" alt="" />\s*Alley Cat &amp; its palette</li>#',
            $html,
        );
    }

    public function test_it_escapes_titles(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('Alley Cat &amp; its palette', $html);
        $this->assertStringContainsString('The three &quot;i&quot; are goblins', $html);
        $this->assertStringNotContainsString('"i" are goblins', $html);
    }

    public function test_it_emits_nothing_the_itch_sanitiser_can_strip(): void
    {
        // itch.io scrubs description HTML. Anything whose loss would change
        // the layout must not be load-bearing, so none of it is emitted at
        // all -- the images are already the size they should be.
        $html = $this->render();

        foreach (['class=', 'style=', '<style', 'width=', 'height=', '<script'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_the_list_icons_carry_no_duplicate_alt_text(): void
    {
        // The fact beside it is the label; repeating it is noise to a screen
        // reader. The hero sheet does get a real alt.
        $html = $this->render();

        $this->assertSame(2, substr_count($html, 'alt=""'));
        $this->assertMatchesRegularExpression('/trivia-tilesheet-4x\.png" alt="[^"]+"/', $html);
    }

    public function test_it_renders_an_empty_set_without_a_list(): void
    {
        $html = (new ItchPage)->render([], 'https://example.test', '/x/trivia-tilesheet.png');

        $this->assertStringNotContainsString('<ul>', $html);
        $this->assertStringContainsString('trivia-tilesheet-4x.png', $html);
    }
}
