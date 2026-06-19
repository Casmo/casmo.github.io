<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\Entry;
use Statamic\StaticSite\SSG;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Render content and expand any {{ palette ... }} snippets within it.
        Blade::directive('content', fn ($expression) => "<?php echo \App\Support\Palette::render((string) ($expression)); ?>");

        SSG::after(function () {
            // Get all public statamic pages
            $pages = Entry::query()
                ->where('published', true)
                ->get();
            if (! is_dir(config('statamic.ssg.output_path').'/assets/pages')) {
                mkdir(config('statamic.ssg.output_path').'/assets/pages', 0755, true);
            }

            $upscale = 1;
            $avatar = imagecreatefrompng(resource_path('img/casmo.png'));
            // Resize avatar to 200x200
            $avatarResized = imagecreatetruecolor(100 * $upscale, 100 * $upscale);
            imagealphablending($avatarResized, false);
            imagesavealpha($avatarResized, true);
            imagecopyresampled($avatarResized, $avatar, 0, 0, 0, 0, 100 * $upscale, 100 * $upscale, imagesx($avatar), imagesy($avatar));
            $background = imagecreatefrompng(resource_path('img/og-background.png'));
            // Resize background to 1200x630
            $backgroundResized = imagecreatetruecolor(1200 * $upscale, 630 * $upscale);
            imagealphablending($backgroundResized, false);
            imagesavealpha($backgroundResized, true);
            imagecopyresampled($backgroundResized, $background, 0, 0, 0, 0, 1200 * $upscale, 630 * $upscale, imagesx($background), imagesy($background));

            $fontPath = resource_path('fonts/SpaceMono-Regular.ttf');
            foreach ($pages as $page) {
                $image = imagecreatetruecolor(1200 * $upscale, 630 * $upscale);
                $bgColor = imagecolorallocate($image, 31, 35, 41);
                imagefill($image, 0, 0, $bgColor);
                imagecopy($image, $backgroundResized, 0, 0, 0, 0, imagesx($background), imagesy($background));
                imagecopy($image, $avatarResized, 50, 265 * $upscale, 0, 0, imagesx($avatarResized), imagesy($avatarResized));
                $textColor = imagecolorallocate($image, 255, 255, 255);

                $fontSize = 40;
                $maxSize = 25;
                $dateTop = 50;
                if (strlen($page->title ?? '') > 70) {
                    $fontSize = 20;
                    $maxSize = 50;
                    $dateTop = 30;
                } elseif (strlen($page->title ?? '') > 50) {
                    $fontSize = 30;
                    $maxSize = 40;
                    $dateTop = 40;
                }
                $text = $page->title ?? '';
                $top = 305;
                if (strlen($text) > $maxSize) {
                    $lines = explode(' ', $text);
                    $numberOfWords = count($lines);
                    $add = 0;
                    if ($numberOfWords % 2 === 0) {
                        $add = 1;
                    }
                    $line1 = implode(' ', array_slice($lines, 0, ceil((count($lines) + $add) / 2)));
                    $line2 = implode(' ', array_slice($lines, ceil((count($lines) + $add) / 2)));
                } else {
                    $line1 = $text;
                    $line2 = '';
                    $top = 335;
                }
                imagettftext($image, $fontSize * $upscale, 0, 160 * $upscale, $top * $upscale, $textColor, $fontPath, $line1);
                if ($line2) {
                    imagettftext($image, $fontSize * $upscale, 0, 160 * $upscale, ($top + 50) * $upscale, $textColor, $fontPath, $line2);
                }

                // Write date on top of title
                $date = $page->date ?? '';
                if ($date) {
                    $date = date('F j, Y', strtotime($date));
                    $textDateColor = imagecolorallocate($image, 159, 159, 169);
                    imagettftext($image, 15 * $upscale, 0, 160 * $upscale, ($top - $dateTop) * $upscale, $textDateColor, $fontPath, $date);
                }

                $tags = implode(', ', $page->get('categories') ?? []);
                if ($tags) {
                    $categoryTextColor = imagecolorallocate($image, 124, 207, 0);
                    imagettftext($image, 15 * $upscale, 0, 160 * $upscale, ($line2 ? $top + 80 : $top + 30) * $upscale, $categoryTextColor, $fontPath, $tags);
                }

                imagepng($image, config('statamic.ssg.output_path').'/assets/pages/'.$page->slug.'.png');
            }
        });
    }
}
