<?php
/**
 * resize.php — shrink site images to <=1232px wide and under 400KB, in place.
 *
 * Keeps the original file extension and format (png stays png, jpg stays jpg,
 * webp stays webp). Levers, in order:
 *   1. downscale width to MAX_WIDTH (never upscales),
 *   2. for jpeg/webp: step quality down until under MAX_BYTES,
 *   3. for png: reduce the colour palette until under MAX_BYTES.
 *
 * Usage:
 *   php resize.php [--dry-run] <file> [<file> ...]
 *
 * Exit code 0 on success, 1 if any file could not be processed.
 */

const MAX_WIDTH = 1232;
const MAX_BYTES = 400 * 1024;
const JPEG_QUALITY = [85, 80, 75, 70, 65, 60, 55, 50, 45, 40, 35];
const WEBP_QUALITY = [85, 80, 75, 70, 65, 60, 55, 50, 45, 40, 35];
const PNG_COLORS   = [256, 192, 128, 96, 64, 48, 32];

$args = array_slice($argv, 1);
$dryRun = false;
$files = [];
foreach ($args as $a) {
    if ($a === '--dry-run') { $dryRun = true; continue; }
    $files[] = $a;
}

if (!$files) {
    fwrite(STDERR, "usage: php resize.php [--dry-run] <file> [<file> ...]\n");
    exit(1);
}

$hadError = false;
foreach ($files as $path) {
    try {
        process($path, $dryRun);
    } catch (Throwable $e) {
        fwrite(STDERR, "ERROR  {$path}: {$e->getMessage()}\n");
        $hadError = true;
    }
}
exit($hadError ? 1 : 0);

function process(string $path, bool $dryRun): void
{
    if (!is_file($path)) {
        throw new RuntimeException('not a file');
    }
    $origBytes = filesize($path);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $fmt = normalizeFormat($ext);
    if ($fmt === null) {
        throw new RuntimeException("unsupported extension .{$ext}");
    }

    [$img, $w, $h] = loadImage($path, $fmt);

    // Already compliant (narrow enough and small enough)? Leave it untouched —
    // re-encoding only loses quality for no benefit.
    if ($w <= MAX_WIDTH && $origBytes <= MAX_BYTES) {
        imagedestroy($img);
        printf("skip   %s  (%s, %dpx, already under budget)\n", $path, human($origBytes), $w);
        return;
    }

    // 1. Downscale width only. Never upscale.
    $resized = false;
    if ($w > MAX_WIDTH) {
        $newW = MAX_WIDTH;
        $newH = (int) round($h * (MAX_WIDTH / $w));
        $img = scale($img, $w, $h, $newW, $newH, $fmt);
        $w = $newW; $h = $newH;
        $resized = true;
    }

    // 2/3. Encode under budget, keeping the format.
    [$blob, $note] = encodeUnderBudget($img, $fmt);
    imagedestroy($img);

    $newBytes = strlen($blob);

    // Never make an already-under-budget file bigger. Some inputs (e.g. retro
    // indexed-palette PNGs barely over 1232px) re-encode larger as truecolor;
    // a marginal width overage beats a multi-x size bloat, so keep the original.
    if ($newBytes >= $origBytes && $origBytes <= MAX_BYTES) {
        printf("skip   %s  (%s, %dpx — re-encode (%s) would be larger, kept original)\n",
            $path, human($origBytes), $w, human($newBytes));
        return;
    }

    $flag = $newBytes > MAX_BYTES ? ' OVER-BUDGET' : '';
    printf("%-6s %s  %s -> %s  %s%s%s\n",
        $dryRun ? 'dry' : 'ok',
        $path,
        human($origBytes),
        human($newBytes),
        "{$w}px",
        $note ? " [{$note}]" : '',
        $flag);

    if (!$dryRun) {
        if (file_put_contents($path, $blob) === false) {
            throw new RuntimeException('failed to write file');
        }
    }
}

function normalizeFormat(string $ext): ?string
{
    return match ($ext) {
        'jpg', 'jpeg' => 'jpeg',
        'png'         => 'png',
        'webp'        => 'webp',
        default       => null,
    };
}

/** @return array{0: \GdImage, 1: int, 2: int} */
function loadImage(string $path, string $fmt): array
{
    $img = match ($fmt) {
        'jpeg' => imagecreatefromjpeg($path),
        'png'  => imagecreatefrompng($path),
        'webp' => imagecreatefromwebp($path),
    };
    if (!$img) {
        throw new RuntimeException('could not decode image');
    }
    return [$img, imagesx($img), imagesy($img)];
}

function scale(\GdImage $src, int $w, int $h, int $newW, int $newH, string $fmt): \GdImage
{
    $dst = imagecreatetruecolor($newW, $newH);
    if ($fmt === 'png' || $fmt === 'webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
    imagedestroy($src);
    return $dst;
}

/** @return array{0: string, 1: string} blob and a human note */
function encodeUnderBudget(\GdImage $img, string $fmt): array
{
    if ($fmt === 'jpeg') {
        return stepQuality($img, JPEG_QUALITY, fn($i, $q) => capture(fn() => imagejpeg($i, null, $q)), 'q');
    }
    if ($fmt === 'webp') {
        return stepQuality($img, WEBP_QUALITY, fn($i, $q) => capture(fn() => imagewebp($i, null, $q)), 'q');
    }

    // png: lossless first; if still over budget, quantise the palette.
    $blob = capture(fn() => imagepng($img, null, 9));
    if (strlen($blob) <= MAX_BYTES) {
        return [$blob, ''];
    }
    foreach (PNG_COLORS as $colors) {
        $copy = cloneImage($img);
        imagetruecolortopalette($copy, true, $colors);
        $blob = capture(fn() => imagepng($copy, null, 9));
        imagedestroy($copy);
        if (strlen($blob) <= MAX_BYTES) {
            return [$blob, "{$colors} colors"];
        }
    }
    return [$blob, 'min palette'];
}

/**
 * @param int[] $levels
 * @return array{0: string, 1: string}
 */
function stepQuality(\GdImage $img, array $levels, callable $encode, string $label): array
{
    $blob = '';
    foreach ($levels as $q) {
        $blob = $encode($img, $q);
        if (strlen($blob) <= MAX_BYTES) {
            return [$blob, "{$label}{$q}"];
        }
    }
    return [$blob, "{$label}" . end($levels)];
}

function cloneImage(\GdImage $img): \GdImage
{
    $w = imagesx($img); $h = imagesy($img);
    $copy = imagecreatetruecolor($w, $h);
    imagealphablending($copy, false);
    imagesavealpha($copy, true);
    imagecopy($copy, $img, 0, 0, 0, 0, $w, $h);
    return $copy;
}

function capture(callable $emit): string
{
    ob_start();
    $emit();
    return ob_get_clean();
}

function human(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return sprintf('%.1fMB', $bytes / 1024 / 1024);
    }
    return sprintf('%dKB', (int) round($bytes / 1024));
}
