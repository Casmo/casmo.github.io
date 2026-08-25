<?php

namespace Tests\Unit;

use App\Support\ItchPage;
use PHPUnit\Framework\TestCase;

class ItchPageTest extends TestCase
{
    private const PUBLIC = '/x/public';

    /** @return list<array{path: string, title: string}> */
    private function icons(): array
    {
        return [
            [
                'path' => self::PUBLIC.'/assets/trivia/alley-cat-dos-game-1bit.png',
                'title' => 'Alley Cat & its palette',
            ],
            [
                'path' => self::PUBLIC.'/assets/trivia/globliiins-1bit-ms-dos-game.png',
                'title' => 'The three "i" are goblins',
            ],
        ];
    }

    private function render(string $base = 'https://example.test'): string
    {
        return (new ItchPage)->render(
            $this->icons(),
            $base,
            self::PUBLIC.'/assets/trivia-tilesheet.png',
            self::PUBLIC,
        );
    }

    public function test_it_leads_with_the_tilesheet(): void
    {
        $html = $this->render();

        $sheet = strpos($html, 'trivia-tilesheet-4x.png');
        $list = strpos($html, '<h2>Every icon</h2>');

        $this->assertNotFalse($sheet);
        $this->assertNotFalse($list);
        $this->assertLessThan($list, $sheet, 'the sheet should precede the list');
    }

    public function test_the_hero_points_at_the_upscaled_sheet(): void
    {
        $html = $this->render('https://mathieuderuiter.nl');

        $this->assertStringContainsString(
            'src="https://mathieuderuiter.nl/itch/trivia-tilesheet-4x.png"',
            $html,
        );
    }

    public function test_the_list_points_at_the_icons_the_site_already_serves(): void
    {
        // Not a derived copy: the icons are linked where they live, at the
        // size they were drawn, so nothing has to be generated for them.
        $html = $this->render('https://mathieuderuiter.nl');

        $this->assertStringContainsString(
            'src="https://mathieuderuiter.nl/assets/trivia/alley-cat-dos-game-1bit.png"',
            $html,
        );
        $this->assertStringContainsString(
            'src="https://mathieuderuiter.nl/assets/trivia/globliiins-1bit-ms-dos-game.png"',
            $html,
        );

        // The only -4x image on the page is the hero.
        $this->assertSame(1, substr_count($html, '-4x.png'));
        $this->assertStringNotContainsString('/itch/icons/', $html);
    }

    public function test_two_icons_sharing_a_filename_get_distinct_urls(): void
    {
        // Keeping the whole path rather than the basename is what stops two
        // same-named icons in different folders collapsing onto one URL and
        // putting the wrong picture beside a fact.
        $html = (new ItchPage)->render(
            [
                ['path' => self::PUBLIC.'/assets/trivia/quake.png', 'title' => 'first'],
                ['path' => self::PUBLIC.'/assets/pages/quake.png', 'title' => 'second'],
            ],
            'https://example.test',
            self::PUBLIC.'/assets/trivia-tilesheet.png',
            self::PUBLIC,
        );

        $this->assertStringContainsString('https://example.test/assets/trivia/quake.png', $html);
        $this->assertStringContainsString('https://example.test/assets/pages/quake.png', $html);
    }

    public function test_it_rejects_an_icon_outside_the_document_root(): void
    {
        // Such an icon cannot be served at all, so emitting a URL for it would
        // guarantee a 404 on a published page.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('#/elsewhere/stray\.png#');

        (new ItchPage)->render(
            [['path' => '/elsewhere/stray.png', 'title' => 'nowhere']],
            'https://example.test',
            self::PUBLIC.'/assets/trivia-tilesheet.png',
            self::PUBLIC,
        );
    }

    public function test_it_trims_a_trailing_slash_from_the_base(): void
    {
        $html = $this->render('https://example.test/');

        $this->assertStringContainsString('https://example.test/itch/', $html);
        $this->assertStringNotContainsString('https://example.test//', $html);
    }

    public function test_it_lists_one_item_per_icon_paired_with_its_title(): void
    {
        $html = $this->render();

        // One <br /> per row, and no list markup: itch's own list styling puts
        // bullets and spacing around each item, which reads wrong when the
        // icon is already the bullet.
        $this->assertSame(2, substr_count($html, '<br />'));
        $this->assertStringNotContainsString('<ul>', $html);
        $this->assertStringNotContainsString('<li>', $html);

        // The pairing, not merely the presence of both: an off-by-one zip
        // would put the wrong fact beside an icon.
        $this->assertMatchesRegularExpression(
            '#<img src="[^"]*/assets/trivia/alley-cat-dos-game-1bit\.png" alt="" />\s*Alley Cat &amp; its palette<br />#',
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
        // all -- the icons are served at their authored size and the hero is
        // already scaled.
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
        $html = (new ItchPage)->render(
            [],
            'https://example.test',
            self::PUBLIC.'/assets/trivia-tilesheet.png',
            self::PUBLIC,
        );

        $this->assertStringNotContainsString('<h2>', $html);
        $this->assertStringNotContainsString('<br />', $html);
        $this->assertStringContainsString('trivia-tilesheet-4x.png', $html);
    }
}
