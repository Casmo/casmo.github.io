<?php

namespace Tests\Unit;

use App\Support\SocialImage;
use PHPUnit\Framework\TestCase;

class SocialImageFitTest extends TestCase
{
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

    public function test_a_one_word_title_takes_the_top_of_the_ladder(): void
    {
        $this->assertSame(
            ['size' => 118, 'lines' => ['Nostalgia']],
            $this->image()->fit('Nostalgia'),
        );
    }

    public function test_a_short_title_stays_at_the_top_of_the_ladder_over_two_rows(): void
    {
        $this->assertSame(
            ['size' => 118, 'lines' => ['Game flow', 'of Kabonk!']],
            $this->image()->fit('Game flow of Kabonk!'),
        );
    }

    public function test_a_medium_title_drops_one_step(): void
    {
        $this->assertSame(
            ['size' => 96, 'lines' => ['The interface', 'of the future']],
            $this->image()->fit('The interface of the future'),
        );
    }

    public function test_a_long_title_drops_further(): void
    {
        $this->assertSame(
            ['size' => 64, 'lines' => ['Build your Statamic', 'static website via', 'GitHub actions']],
            $this->image()->fit('Build your Statamic static website via GitHub actions'),
        );
    }

    public function test_the_longest_real_title_still_clears_the_floor(): void
    {
        $this->assertSame(
            [
                'size' => 54,
                'lines' => [
                    'Generate default Open',
                    'Graph images for each',
                    'Entry in your your',
                    'Statamic site',
                ],
            ],
            $this->image()->fit('Generate default Open Graph images for each Entry in your your Statamic site'),
        );
    }

    public function test_an_absurd_title_is_truncated_at_the_floor(): void
    {
        $result = $this->image()->fit(trim(str_repeat('lorem ipsum dolor sit amet ', 12)));

        $this->assertSame(40, $result['size']);
        $this->assertCount(7, $result['lines']);
        $this->assertSame('ipsum dolor sit amet lorem ipsum…', end($result['lines']));
    }

    public function test_a_word_wider_than_the_measure_is_hard_broken(): void
    {
        $result = $this->image()->fit(str_repeat('a', 200));

        $this->assertSame(40, $result['size']);
        $this->assertCount(7, $result['lines']);
        $this->assertSame(str_repeat('a', 33), $result['lines'][0]);
        $this->assertSame('aa', end($result['lines']));
    }

    /**
     * A single word too wide to fit a rung disqualifies that rung rather than
     * being hard-broken mid-word -- the hard break only happens at the 40pt
     * floor. Before this rule, `Accessibility` broke into `Accessibili` /
     * `ty` at 118pt instead of dropping to 96pt.
     */
    public function test_a_single_long_word_drops_a_rung_instead_of_breaking_mid_word(): void
    {
        $this->assertSame(
            ['size' => 96, 'lines' => ['Accessibility']],
            $this->image()->fit('Accessibility'),
        );
    }

    public function test_a_single_longer_word_drops_further_still(): void
    {
        $this->assertSame(
            ['size' => 78, 'lines' => ['Cinematography']],
            $this->image()->fit('Cinematography'),
        );

        $this->assertSame(
            ['size' => 78, 'lines' => ['Photogrammetry']],
            $this->image()->fit('Photogrammetry'),
        );
    }
}
