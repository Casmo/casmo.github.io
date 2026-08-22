<?php

namespace App\Console\Commands;

use App\Support\Files;
use App\Support\IconPack;
use App\Support\ItchPage;
use App\Support\Tilesheet;
use App\Support\TilesheetGenerator;
use App\Support\TriviaIcons;
use Illuminate\Console\Command;

/**
 * Builds everything the itch.io release needs: the directory butler pushes,
 * and the description HTML to paste into the page.
 *
 * The sheet is regenerated here rather than copied from public/assets, because
 * that committed file is only refreshed by an SSG build and has been three
 * icons stale before. The pack depends on the collection, never on the
 * artefact.
 */
class BuildIconPack extends Command
{
    protected $signature = 'trivia:pack
                            {--out=build/itch : where to write the pack and the page}
                            {--base-url= : where the upscales are published, defaults to app.url}';

    protected $description = 'Build the itch.io icon pack and its page description';

    public function handle(
        TriviaIcons $icons,
        Tilesheet $tilesheet,
        IconPack $pack,
        ItchPage $page,
        Files $files,
    ): int {
        $out = $this->option('out');
        $out = str_starts_with($out, '/') ? $out : base_path($out);

        $baseUrl = $this->option('base-url') ?: (string) config('app.url');

        $resolved = $icons->all();

        if ($resolved === []) {
            $this->error('No trivia entry has an icon, so there is nothing to pack.');

            return self::FAILURE;
        }

        $staging = $out.'/pack';

        // $out, not $staging: the sheet is written here before IconPack runs,
        // and IconPack clears $staging as its first act.
        $files->directory($out);

        // Written outside the staging directory: anything inside it ships to
        // buyers as part of the download.
        $sheet = $out.'/'.TilesheetGenerator::FILENAME;

        $written = $tilesheet->write($sheet, array_column($resolved, 'path'));

        $count = $pack->write($staging, $resolved, $sheet);

        $files->put($out.'/page.html', $page->render($resolved, $baseUrl, $sheet));

        // Only the copy inside pack/ is shipped; this one was scratch.
        unlink($sheet);

        $this->info("Packed {$count} icons and a {$written['width']}x{$written['height']} sheet into {$staging}.");
        $this->line("Page description written to {$out}/page.html");

        return self::SUCCESS;
    }
}
