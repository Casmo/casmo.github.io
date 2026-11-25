<?php
// Draws ai-journey.png, the social artwork for the AI-journey post.
// Run: php resources/img/artwork/ai-journey.php
$W = 2400; $H = 1260;
$im = imagecreatetruecolor($W, $H);
imagealphablending($im, true);
imageantialias($im, false); // antialiasing makes GD ignore alpha and thickness
$c = fn ($hex, $a = 0) => imagecolorallocatealpha($im, ...[...sscanf($hex, '#%02x%02x%02x'), $a]);
imagefill($im, 0, 0, $c('#1f2329'));

$love = '#84cc16'; $hate = '#ef4444';
$mid = 820; $up = 560; $down = 300; // asymmetric: the title band sits on the love side
// beats: [value -1..1]
$beats = [
  0.15,  // before
  0.55,  // ChatGPT 3.5
  0.80,  // Copilot
  0.10,  // Cursor
  -0.55, // early Claude Code
  0.70,  // Opus 4
  -0.45, // hate
  0.85,  // Opus 4.5 love
  -0.35, // hate
  -0.80, // overwhelmed
  0.70,  // AIHero skills
  0.55,  // superpowers
  0.85,  // own tools / human-loop
  1.25,  // sky high (above the frame)
];
$x0 = -40; $x1 = $W + 60; $n = count($beats);
$pts = [];
foreach ($beats as $i => $v) {
  $pts[] = [$x0 + ($x1 - $x0) * $i / ($n - 1), $mid - $v * ($v > 0 ? $up : $down)];
}

// Faint zones: love above the midline, hate below.
imagefilledrectangle($im, 0, 0, $W, $mid, $c($love, 122));
imagefilledrectangle($im, 0, $mid, $W, $H, $c($hate, 122));

// Area between the line and the midline, split where it crosses.
$segments = [];
for ($i = 0; $i < $n - 1; $i++) {
  [$a, $b] = [$pts[$i], $pts[$i + 1]];
  if (($a[1] - $mid) * ($b[1] - $mid) < 0) {
    $t = ($mid - $a[1]) / ($b[1] - $a[1]);
    $m = [$a[0] + ($b[0] - $a[0]) * $t, $mid];
    $segments[] = [$a, $m]; $segments[] = [$m, $b];
  } else {
    $segments[] = [$a, $b];
  }
}
// Area between line and midline, one column at a time so nothing overlaps.
$loveFill = $c($love, 100); $hateFill = $c($hate, 100);
for ($x = 0; $x < $W; $x++) {
  foreach ($segments as [$a, $b]) {
    if ($x >= $a[0] && $x < $b[0]) {
      $y = (int) round($a[1] + ($b[1] - $a[1]) * ($x - $a[0]) / ($b[0] - $a[0]));
      if ($y < $mid) imageline($im, $x, $y, $x, $mid - 1, $loveFill);
      elseif ($y > $mid) imageline($im, $x, $mid + 1, $x, $y, $hateFill);
      break;
    }
  }
}

// Midline, dashed like a terminal rule.
for ($x = 0; $x < $W; $x += 36) imagefilledrectangle($im, $x, $mid - 1, $x + 18, $mid + 1, $c('#6b7280', 40));

// Constellation web: each beat linked to a few satellite nodes.
mt_srand(42);
$web = $c('#84cc16', 85);
foreach ($pts as $i => $p) {
  $col = $p[1] < $mid ? $c($love, 85) : $c($hate, 85);
  $sats = [];
  for ($k = 0; $k < 3; $k++) {
    $sats[] = [$p[0] + mt_rand(-140, 140), $p[1] + mt_rand(-150, 150)];
  }
  foreach ($sats as $j => $s) {
    imagesetthickness($im, 2);
    imageline($im, (int)$p[0], (int)$p[1], (int)$s[0], (int)$s[1], $col);
    $t = $sats[($j + 1) % 3];
    imageline($im, (int)$s[0], (int)$s[1], (int)$t[0], (int)$t[1], $col);
    imagefilledellipse($im, (int)$s[0], (int)$s[1], 9, 9, $col);
  }
}

// The line itself, coloured by which side of the midline it is on.
foreach ($segments as [$a, $b]) {
  $col = (($a[1] + $b[1]) / 2 < $mid) ? $love : $hate;
  imagesetthickness($im, 10);
  imageline($im, (int)$a[0], (int)$a[1], (int)$b[0], (int)$b[1], $c($col, 90));
  imagesetthickness($im, 5);
  imageline($im, (int)$a[0], (int)$a[1], (int)$b[0], (int)$b[1], $c($col));
}
foreach ($pts as $p) {
  $col = $p[1] < $mid ? $love : $hate;
  imagefilledellipse($im, (int)$p[0], (int)$p[1], 34, 34, $c($col, 95));
  imagefilledellipse($im, (int)$p[0], (int)$p[1], 18, 18, $c($col));
}

// Quiet labels in the left margin, either side of the midline.
$font = __DIR__.'/../../fonts/IBMPlexMono-Bold.ttf';
imagettftext($im, 26, 0, 28, $mid - 22, $c($love, 20), $font, 'love');
imagettftext($im, 26, 0, 28, $mid + 56, $c($hate, 20), $font, 'hate');

imagepng($im, __DIR__.'/ai-journey.png');
