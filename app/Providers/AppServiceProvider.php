<?php

namespace App\Providers;

use App\Support\Files;
use App\Support\ItchAssetsGenerator;
use App\Support\SocialImage;
use App\Support\SocialImageGenerator;
use App\Support\Tilesheet;
use App\Support\TilesheetGenerator;
use App\Support\TriviaIcons;
use App\Support\Upscale;
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
            $tilesheet = (new TilesheetGenerator(
                new Tilesheet,
                new TriviaIcons,
                public_path(),
                config('statamic.ssg.output_path'),
            ))->generate();

            // The upscaled sheet the itch.io page hotlinks as its hero. Output
            // only: it is never shown on the site, and public/assets is an
            // asset container that would want a .meta yaml beside it. The
            // individual icons need nothing here -- the page links them where
            // the site already serves them. Guarded on the tilesheet having
            // actually been written: generate() writes nothing when no entry
            // has an icon, and upscaling the stale committed sheet in that
            // case would publish it as though it were current.
            if ($tilesheet !== null) {
                (new ItchAssetsGenerator(
                    new Upscale,
                    new Files,
                    public_path('assets/'.TilesheetGenerator::FILENAME),
                    config('statamic.ssg.output_path'),
                ))->generate();
            }
        });
    }
}
