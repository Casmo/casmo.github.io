<?php

namespace App\Providers;

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
        SSG::after(function () {
            // Get all public statamic pages
            $pages = Entry::query()
                ->where('published', true)
                ->get();
                if (!is_dir(base_path('storage/static/assets/pages'))) {
                    mkdir(base_path('storage/static/assets/pages'), 0755, true);
                }
                $avatar = imagecreatefrompng(resource_path('img/casmo.png'));
                    $fontPath = resource_path('fonts/SpaceMono-Regular.ttf');

                foreach ($pages as $page) {
                    $image = imagecreatetruecolor(1200, 627);
                    $bgColor = imagecolorallocate($image, 31, 35, 41);
                    imagefill($image, 0, 0, $bgColor);
                    $textColor = imagecolorallocate($image, 255, 255, 255);
                    
                    $fontSize = 40;
                    $maxSize = 25;
                    $dateTop = 50;
                    if (strlen($page->title ?? '') > 70) {
                        $fontSize = 20;
                        $maxSize = 50;
                        $dateTop = 30;
                    }
                    else if (strlen($page->title ?? '') > 50) {
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
                    imagettftext($image, $fontSize, 0, 160, $top, $textColor, $fontPath, $line1);
                    if ($line2) {
                        imagettftext($image, $fontSize, 0, 160, $top+50, $textColor, $fontPath, $line2);
                    }

                    // Write date on top of title
                    $date = $page->date ?? '';
                    if ($date) {
                        $date = date('F j, Y', strtotime($date));
                        $textDateColor = imagecolorallocate($image, 159, 159, 169);
                        imagettftext($image, 15, 0, 160, $top-$dateTop, $textDateColor, $fontPath, $date);
                    }

                    $tags = join(', ', $page->get('categories') ?? []);
                    if ($tags) {
                        $categoryTextColor = imagecolorallocate($image, 124, 207, 0);
                        imagettftext($image, 15, 0, 160, ($line2 ? $top+80 : $top+30), $categoryTextColor, $fontPath, $tags);
                    }

                    imagecopy($image, $avatar, 50, 265, 0, 0, imagesx($avatar), imagesy($avatar));
                    imagejpeg($image, base_path('storage/static/assets/pages/' . $page->slug . '.jpg'), 100);
                }
        });
    }
}
