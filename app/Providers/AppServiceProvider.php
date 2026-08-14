<?php

namespace App\Providers;

use App\Support\SocialImage;
use App\Support\SocialImageGenerator;
use App\Support\Tilesheet;
use App\Support\TilesheetGenerator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
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

        // Every published, routed entry gets its Open Graph image after the
        // static build, keyed by slug to match the og:image tag in default.blade.php.
        SSG::after(function () {
            (new SocialImageGenerator(
                new SocialImage(
                    titleFont: resource_path('fonts/IBMPlexMono-Bold.ttf'),
                    chromeFont: resource_path('fonts/IBMPlexMono-Regular.ttf'),
                    artwork: resource_path('img/og-background.png'),
                    avatar: resource_path('img/casmo.png'),
                ),
                config('statamic.ssg.output_path'),
            ))->generate();

            // One sheet of every trivia icon, published as an asset and copied
            // into the build, since copyFiles() has already run by now.
            (new TilesheetGenerator(
                new Tilesheet,
                public_path(),
                config('statamic.ssg.output_path'),
            ))->generate();
        });
    }
}
