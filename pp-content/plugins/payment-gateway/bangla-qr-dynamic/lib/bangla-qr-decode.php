<?php
/**
 * QR Code reader for uploaded merchant QR images.
 *
 * Pipeline: grayscale -> Otsu binarisation -> finder pattern detection ->
 * affine grid sampling -> format info -> unmask -> codeword extraction ->
 * de-interleave -> Reed-Solomon syndrome check -> segment decoding.
 *
 * The reader targets clean, machine-generated QR images such as the ones banks
 * export from their merchant apps. It tolerates scale, rotation and quiet-zone
 * differences; it does not attempt perspective correction of photographed codes.
 */

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

/**
 * Otsu's method: the threshold that best separates a bimodal histogram.
 *
 * @param array{w:int, h:int, pixels:array<int, array<int, int>>} $lum
 * @return int
 */
function bqr_otsu_threshold(array $lum)
{
    $histogram = array_fill(0, 256, 0);
    foreach ($lum['pixels'] as $row) {
        foreach ($row as $value) {
            $histogram[$value]++;
        }
    }

    $total = $lum['w'] * $lum['h'];
    if ($total < 1) {
        return 128;
    }

    $sum = 0;
    for ($i = 0; $i < 256; $i++) {
        $sum += $i * $histogram[$i];
    }

    $sumBackground = 0;
    $weightBackground = 0;
    $best = 0;
    $bestVariance = -1;

    for ($t = 0; $t < 256; $t++) {
        $weightBackground += $histogram[$t];
        if ($weightBackground === 0) {
            continue;
        }
        $weightForeground = $total - $weightBackground;
        if ($weightForeground === 0) {
            break;
        }

        $sumBackground += $t * $histogram[$t];
        $meanBackground = $sumBackground / $weightBackground;
        $meanForeground = ($sum - $sumBackground) / $weightForeground;
        $variance = $weightBackground * $weightForeground * ($meanBackground - $meanForeground) ** 2;

        if ($variance > $bestVariance) {
            $bestVariance = $variance;
            $best = $t;
        }
    }

    return $best;
}

/**
 * A threshold midway between the darkest and lightest pixel.
 *
 * For a crisp black-on-white QR this is exact, and it stays sensible when Otsu
 * collapses to an extreme value on a two-tone image.
 *
 * @param array $lum
 * @return int
 */
function bqr_midpoint_threshold(array $lum)
{
    $min = 255;
    $max = 0;
    foreach ($lum['pixels'] as $row) {
        foreach ($row as $value) {
            if ($value < $min) {
                $min = $value;
            }
            if ($value > $max) {
                $max = $value;
            }
        }
    }

    if ($max - $min < 8) {
        return 128; // effectively flat: nothing to separate
    }

    return (int) (($min + $max) / 2);
}

/**
 * Read the luminance value at a pixel, clamped to the image bounds.
 *
 * @param array $lum
 * @param int $x
 * @param int $y
 * @return int
 */
function bqr_lum_at(array $lum, $x, $y)
{
    $x = (int) round($x);
    $y = (int) round($y);
    if ($x < 0) {
        $x = 0;
    }
    if ($y < 0) {
        $y = 0;
    }
    if ($x >= $lum['w']) {
        $x = $lum['w'] - 1;
    }
    if ($y >= $lum['h']) {
        $y = $lum['h'] - 1;
    }

    return $lum['pixels'][$y][$x];
}

/**
 * True when the run lengths look like the 1:1:3:1:1 finder ratio.
 *
 * @param array<int,int> $lengths Five run lengths.
 * @return bool
 */
function bqr_looks_like_finder(array $lengths)
{
    $total = array_sum($lengths);
    if ($total < 7) {
        return false;
    }

    $module = $total / 7.0;
    $tolerance = $module * 0.55;

    foreach ($lengths as $index => $length) {
        $expected = $index === 2 ? $module * 3 : $module;
        if (abs($length - $expected) > $tolerance + 0.5) {
            return false;
        }
    }

    return true;
}

/**
 * Find the three finder pattern centres by scanning rows and confirming columns.
 *
 * @param array $lum
 * @param int   $threshold
 * @return array<int, array{x:float, y:float, m:float, hits:int}>
 */
function bqr_find_finders(array $lum, $threshold)
{
    $width = $lum['w'];
    $height = $lum['h'];
    $candidates = array();

    for ($y = 0; $y < $height; $y++) {
        // Build the horizontal run-length encoding of this row.
        $runs = array();
        $colour = $lum['pixels'][$y][0] <= $threshold ? 1 : 0;
        $start = 0;
        for ($x = 1; $x <= $width; $x++) {
            $next = $x < $width ? ($lum['pixels'][$y][$x] <= $threshold ? 1 : 0) : -1;
            if ($next !== $colour) {
                $runs[] = array('colour' => $colour, 'start' => $start, 'length' => $x - $start);
                $colour = $next;
                $start = $x;
            }
        }

        $runCount = count($runs);
        for ($i = 0; $i + 4 < $runCount; $i++) {
            if ($runs[$i]['colour'] !== 1 || $runs[$i + 1]['colour'] !== 0
                || $runs[$i + 2]['colour'] !== 1 || $runs[$i + 3]['colour'] !== 0
                || $runs[$i + 4]['colour'] !== 1) {
                continue;
            }

            $lengths = array($runs[$i]['length'], $runs[$i + 1]['length'], $runs[$i + 2]['length'], $runs[$i + 3]['length'], $runs[$i + 4]['length']);
            if (!bqr_looks_like_finder($lengths)) {
                continue;
            }

            $centerX = $runs[$i + 2]['start'] + $runs[$i + 2]['length'] / 2.0;
            $horizontalModule = array_sum($lengths) / 7.0;

            // Confirm vertically through the candidate centre: a real finder
            // also reads 1:1:3:1:1 down its middle, at the same module size.
            $column = bqr_scan_column($lum, $threshold, $centerX, $y, $horizontalModule);
            if ($column === null) {
                continue;
            }
            if (abs($column['y'] - $y) > $horizontalModule * 2.0
                || abs($column['m'] - $horizontalModule) > $horizontalModule * 0.6) {
                continue;
            }

            $candidates[] = array(
                'x' => $centerX,
                'y' => $column['y'],
                'm' => ($column['m'] + $horizontalModule) / 2.0,
                'hits' => 1,
            );
        }
    }

    if (count($candidates) < 3) {
        return array();
    }

    // Merge candidates that describe the same finder pattern.
    $merged = array();
    foreach ($candidates as $candidate) {
        $found = false;
        foreach ($merged as $index => $existing) {
            $distance = sqrt(($existing['x'] - $candidate['x']) ** 2 + ($existing['y'] - $candidate['y']) ** 2);
            if ($distance <= max(2.0, $existing['m'] * 1.2)) {
                $merged[$index]['x'] = ($existing['x'] * $existing['hits'] + $candidate['x']) / ($existing['hits'] + 1);
                $merged[$index]['y'] = ($existing['y'] * $existing['hits'] + $candidate['y']) / ($existing['hits'] + 1);
                $merged[$index]['m'] = ($existing['m'] * $existing['hits'] + $candidate['m']) / ($existing['hits'] + 1);
                $merged[$index]['hits']++;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $merged[] = $candidate;
        }
    }

    if (count($merged) < 3) {
        return array();
    }

    // Only centres seen on several scan lines can be finder patterns.
    $merged = array_values(array_filter($merged, function ($candidate) {
        return $candidate['hits'] >= 3;
    }));
    if (count($merged) < 3) {
        return array();
    }

    usort($merged, function ($a, $b) {
        return $b['hits'] <=> $a['hits'];
    });
    // Keep the search space small; real finders dominate the hit counts.
    $merged = array_slice($merged, 0, 12);

    return bqr_pick_finder_triple($merged);
}

/**
 * Choose the three centres that form a believable QR corner arrangement.
 *
 * A real triple has two similar-length legs meeting near a right angle, and all
 * three finders share one module size.
 *
 * @param array $clusters
 * @return array<int, array> Empty when nothing plausible is found.
 */
function bqr_pick_finder_triple(array $clusters)
{
    $count = count($clusters);
    if ($count < 3) {
        return array();
    }

    $best = array();
    $bestScore = INF;

    for ($a = 0; $a < $count - 2; $a++) {
        for ($b = $a + 1; $b < $count - 1; $b++) {
            for ($c = $b + 1; $c < $count; $c++) {
                $triple = array($clusters[$a], $clusters[$b], $clusters[$c]);
                $ordered = bqr_order_finders($triple);
                if ($ordered === null) {
                    continue;
                }

                $legA = sqrt(($ordered['tr']['x'] - $ordered['tl']['x']) ** 2 + ($ordered['tr']['y'] - $ordered['tl']['y']) ** 2);
                $legB = sqrt(($ordered['bl']['x'] - $ordered['tl']['x']) ** 2 + ($ordered['bl']['y'] - $ordered['tl']['y']) ** 2);
                if ($legA <= 0 || $legB <= 0) {
                    continue;
                }

                $modules = array($ordered['tl']['m'], $ordered['tr']['m'], $ordered['bl']['m']);
                $minModule = min($modules);
                $maxModule = max($modules);
                if ($minModule <= 0) {
                    continue;
                }

                $ratio = min($legA, $legB) / max($legA, $legB);
                $spread = $maxModule / $minModule;

                $v1x = $ordered['tr']['x'] - $ordered['tl']['x'];
                $v1y = $ordered['tr']['y'] - $ordered['tl']['y'];
                $v2x = $ordered['bl']['x'] - $ordered['tl']['x'];
                $v2y = $ordered['bl']['y'] - $ordered['tl']['y'];
                $cos = ($v1x * $v2x + $v1y * $v2y) / ($legA * $legB);
                $angle = rad2deg(acos(max(-1.0, min(1.0, $cos))));

                if ($ratio < 0.7 || $spread > 1.6 || $angle < 60 || $angle > 120) {
                    continue;
                }

                $score = (1.0 - $ratio) + abs($angle - 90.0) / 90.0 + ($spread - 1.0);

                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = $triple;
                }
            }
        }
    }

    return $best;
}

/**
 * Scan a column through ($x, around $y) for the finder ratio.
 *
 * @param array $lum
 * @param int   $threshold
 * @param float $x
 * @param int   $aroundY
 * @param float $expectedModule Optional module size to reject mismatched runs.
 * @return array{y:float, m:float}|null
 */
function bqr_scan_column(array $lum, $threshold, $x, $aroundY, $expectedModule = 0.0)
{
    $height = $lum['h'];
    $column = (int) round($x);
    if ($column < 0 || $column >= $lum['w']) {
        return null;
    }

    $runs = array();
    $colour = $lum['pixels'][0][$column] <= $threshold ? 1 : 0;
    $start = 0;
    for ($y = 1; $y <= $height; $y++) {
        $next = $y < $height ? ($lum['pixels'][$y][$column] <= $threshold ? 1 : 0) : -1;
        if ($next !== $colour) {
            $runs[] = array('colour' => $colour, 'start' => $start, 'length' => $y - $start);
            $colour = $next;
            $start = $y;
        }
    }

    $runCount = count($runs);
    $best = null;
    for ($i = 0; $i + 4 < $runCount; $i++) {
        if ($runs[$i]['colour'] !== 1 || $runs[$i + 1]['colour'] !== 0
            || $runs[$i + 2]['colour'] !== 1 || $runs[$i + 3]['colour'] !== 0
            || $runs[$i + 4]['colour'] !== 1) {
            continue;
        }

        $lengths = array($runs[$i]['length'], $runs[$i + 1]['length'], $runs[$i + 2]['length'], $runs[$i + 3]['length'], $runs[$i + 4]['length']);
        if (!bqr_looks_like_finder($lengths)) {
            continue;
        }

        $centerY = $runs[$i + 2]['start'] + $runs[$i + 2]['length'] / 2.0;
        $module = array_sum($lengths) / 7.0;
        if ($expectedModule > 0 && abs($module - $expectedModule) > $expectedModule * 0.6) {
            continue;
        }
        $distance = abs($centerY - $aroundY);
        if ($best === null || $distance < $best['distance']) {
            $best = array('y' => $centerY, 'm' => $module, 'distance' => $distance);
        }
    }

    if ($best === null) {
        return null;
    }

    return array('y' => $best['y'], 'm' => $best['m']);
}

/**
 * Order three finder centres into top-left, top-right, bottom-left.
 *
 * @param array $finders
 * @return array{tl:array, tr:array, bl:array}|null
 */
function bqr_order_finders(array $finders)
{
    if (count($finders) !== 3) {
        return null;
    }

    // The corner opposite the hypotenuse is the top-left finder: the one with
    // the widest angle between the other two.
    $bestIndex = 0;
    $bestRightAngle = PHP_INT_MAX;
    foreach ($finders as $index => $candidate) {
        $others = array_values(array_filter($finders, function ($key) use ($index) {
            return $key !== $index;
        }, ARRAY_FILTER_USE_KEY));
        if (count($others) !== 2) {
            continue;
        }

        $v1x = $others[0]['x'] - $candidate['x'];
        $v1y = $others[0]['y'] - $candidate['y'];
        $v2x = $others[1]['x'] - $candidate['x'];
        $v2y = $others[1]['y'] - $candidate['y'];

        $dot = $v1x * $v2x + $v1y * $v2y;
        $norm = sqrt($v1x ** 2 + $v1y ** 2) * sqrt($v2x ** 2 + $v2y ** 2);
        if ($norm <= 0) {
            continue;
        }
        $cos = $dot / $norm;
        if ($cos < $bestRightAngle) {
            $bestRightAngle = $cos;
            $bestIndex = $index;
        }
    }

    $tl = $finders[$bestIndex];
    $others = array();
    foreach ($finders as $index => $candidate) {
        if ($index !== $bestIndex) {
            $others[] = $candidate;
        }
    }
    if (count($others) !== 2) {
        return null;
    }

    // The remaining two are top-right and bottom-left. Which is which follows
    // from handedness: in a QR the turn from the top-right leg to the
    // bottom-left leg is the same one for every rotation, so the cross product
    // identifies them regardless of how the image was turned.
    $v1x = $others[0]['x'] - $tl['x'];
    $v1y = $others[0]['y'] - $tl['y'];
    $v2x = $others[1]['x'] - $tl['x'];
    $v2y = $others[1]['y'] - $tl['y'];
    $cross = $v1x * $v2y - $v1y * $v2x;

    if ($cross > 0) {
        return array('tl' => $tl, 'tr' => $others[0], 'bl' => $others[1]);
    }

    return array('tl' => $tl, 'tr' => $others[1], 'bl' => $others[0]);
}

/**
 * Sample the module grid using the affine frame defined by the three finders.
 *
 * @param array $lum
 * @param int   $threshold
 * @param array $ordered
 * @param int   $version
 * @return array{size:int, modules:array<int, array<int, int>>}|null
 */
function bqr_sample_matrix(array $lum, $threshold, array $ordered, $version)
{
    $size = $version * 4 + 17;
    $tl = $ordered['tl'];
    $tr = $ordered['tr'];
    $bl = $ordered['bl'];

    $span = $size - 7;
    if ($span <= 0) {
        return null;
    }

    $exX = ($tr['x'] - $tl['x']) / $span;
    $exY = ($tr['y'] - $tl['y']) / $span;
    $eyX = ($bl['x'] - $tl['x']) / $span;
    $eyY = ($bl['y'] - $tl['y']) / $span;

    $modules = array();
    for ($r = 0; $r < $size; $r++) {
        $row = array();
        for ($c = 0; $c < $size; $c++) {
            $px = $tl['x'] + ($c - 3) * $exX + ($r - 3) * $eyX;
            $py = $tl['y'] + ($c - 3) * $exY + ($r - 3) * $eyY;
            $row[] = bqr_lum_at($lum, $px, $py) <= $threshold ? 1 : 0;
        }
        $modules[] = $row;
    }

    return array('size' => $size, 'modules' => $modules);
}

/**
 * How well a sampled matrix matches the fixed finder/timing patterns.
 *
 * @param array $matrix
 * @return int Score from 0 to 1.
 */
function bqr_structure_score(array $matrix)
{
    $size = $matrix['size'];
    $modules = $matrix['modules'];
    $matches = 0;
    $total = 0;

    $corners = array(array(0, 0), array(0, $size - 7), array($size - 7, 0));
    foreach ($corners as $corner) {
        for ($dy = 0; $dy < 7; $dy++) {
            for ($dx = 0; $dx < 7; $dx++) {
                $expected = (max($dx - 3, 3 - $dx, $dy - 3, 3 - $dy) !== 2) ? 1 : 0;
                $actual = $modules[$corner[1] + $dy][$corner[0] + $dx];
                if ($actual === $expected) {
                    $matches++;
                }
                $total++;
            }
        }
    }

    // Timing patterns.
    for ($i = 8; $i < $size - 8; $i++) {
        $expected = $i % 2 === 0 ? 1 : 0;
        if ($modules[6][$i] === $expected) {
            $matches++;
        }
        if ($modules[$i][6] === $expected) {
            $matches++;
        }
        $total += 2;
    }

    return $total > 0 ? $matches / $total : 0.0;
}

/**
 * Try to locate and sample the QR symbol in a luminance image.
 *
 * @param array $lum
 * @param int   $threshold
 * @return array|null
 */
function bqr_locate_matrix(array $lum, $threshold)
{
    $finders = bqr_find_finders($lum, $threshold);
    $ordered = bqr_order_finders($finders);

    if ($ordered === null) {
        return null;
    }

    $moduleSize = ($ordered['tl']['m'] + $ordered['tr']['m'] + $ordered['bl']['m']) / 3.0;
    if ($moduleSize <= 0) {
        return null;
    }

    $horizontal = sqrt(($ordered['tr']['x'] - $ordered['tl']['x']) ** 2 + ($ordered['tr']['y'] - $ordered['tl']['y']) ** 2);
    $vertical = sqrt(($ordered['bl']['x'] - $ordered['tl']['x']) ** 2 + ($ordered['bl']['y'] - $ordered['tl']['y']) ** 2);
    $estimated = (($horizontal + $vertical) / 2.0) / $moduleSize + 7;

    // Snap to the nearest legal symbol size and check its neighbours.
    $best = null;
    $bestScore = -1.0;
    foreach (array(-1, 0, 1) as $drift) {
        $version = (int) round(($estimated - 17) / 4) + $drift;
        if ($version < 1 || $version > 40) {
            continue;
        }
        $matrix = bqr_sample_matrix($lum, $threshold, $ordered, $version);
        if ($matrix === null) {
            continue;
        }
        $score = bqr_structure_score($matrix);
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $matrix;
        }
    }

    if ($best === null || $bestScore < 0.85) {
        return null;
    }

    $best['score'] = $bestScore;

    return $best;
}

/**
 * The eight mask predicates, evaluated at module (x, y).
 *
 * @param int $mask
 * @param int $x
 * @param int $y
 * @return bool
 */
function bqr_mask_bit($mask, $x, $y)
{
    switch ($mask) {
        case 0: return ($x + $y) % 2 === 0;
        case 1: return $y % 2 === 0;
        case 2: return $x % 3 === 0;
        case 3: return ($x + $y) % 3 === 0;
        case 4: return (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0;
        case 5: return (($x * $y) % 2 + ($x * $y) % 3) === 0;
        case 6: return ((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0;
        case 7: return ((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0;
    }

    return false;
}

/**
 * Read the 15 format bits from both copies and pick the closest valid codeword.
 *
 * @param array $matrix
 * @return array{level:string, mask:int}|null
 */
function bqr_read_format(array $matrix)
{
    $size = $matrix['size'];
    $modules = $matrix['modules'];

    $bitsA = array();
    for ($i = 0; $i <= 5; $i++) {
        $bitsA[$i] = $modules[$i][8];
    }
    $bitsA[6] = $modules[7][8];
    $bitsA[7] = $modules[8][8];
    $bitsA[8] = $modules[8][7];
    for ($i = 9; $i < 15; $i++) {
        $bitsA[$i] = $modules[8][14 - $i];
    }

    $bitsB = array();
    for ($i = 0; $i < 8; $i++) {
        $bitsB[$i] = $modules[8][$size - 1 - $i];
    }
    for ($i = 8; $i < 15; $i++) {
        $bitsB[$i] = $modules[$size - 15 + $i][8];
    }

    $valueA = 0;
    foreach ($bitsA as $i => $bit) {
        $valueA |= ($bit & 1) << $i;
    }
    $valueB = 0;
    foreach ($bitsB as $i => $bit) {
        $valueB |= ($bit & 1) << $i;
    }

    $levels = array('L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2);
    $best = null;
    $bestDistance = PHP_INT_MAX;

    foreach ($levels as $levelName => $levelBits) {
        for ($mask = 0; $mask < 8; $mask++) {
            $data = ($levelBits << 3) | $mask;
            $rem = $data;
            for ($i = 0; $i < 10; $i++) {
                $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
            }
            $expected = (($data << 10) | $rem) ^ 0x5412;

            $distance = 0;
            for ($bit = 0; $bit < 15; $bit++) {
                $expectedBit = ($expected >> $bit) & 1;
                if ($expectedBit !== (($valueA >> $bit) & 1)) {
                    $distance++;
                }
                if ($expectedBit !== (($valueB >> $bit) & 1)) {
                    $distance++;
                }
            }

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = array('level' => $levelName, 'mask' => $mask);
            }
        }
    }

    // 15 bits give a minimum distance of 7, so up to 3 bit errors are safe.
    if ($best === null || $bestDistance > 6) {
        return null;
    }

    return $best;
}

/**
 * Walk the zigzag and read the codewords, undoing the mask.
 *
 * @param array $matrix
 * @param int   $mask
 * @return array<int,int>
 */
function bqr_extract_codewords(array $matrix, $mask)
{
    $size = $matrix['size'];
    $modules = $matrix['modules'];
    $bits = array();

    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right = 5;
        }
        for ($vert = 0; $vert < $size; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                $upward = (($right + 1) & 2) === 0;
                $y = $upward ? $size - 1 - $vert : $vert;
                if (!bqr_is_function_module($size, $x, $y)) {
                    $bit = $modules[$y][$x];
                    if (bqr_mask_bit($mask, $x, $y)) {
                        $bit ^= 1;
                    }
                    $bits[] = $bit;
                }
            }
        }
    }

    $codewords = array();
    $count = count($bits) - count($bits) % 8;
    for ($i = 0; $i < $count; $i += 8) {
        $value = 0;
        for ($b = 0; $b < 8; $b++) {
            $value = ($value << 1) | $bits[$i + $b];
        }
        $codewords[] = $value;
    }

    return $codewords;
}

/**
 * True when a module position belongs to a function pattern (not data).
 *
 * @param int $size
 * @param int $x
 * @param int $y
 * @return bool
 */
function bqr_is_function_module($size, $x, $y)
{
    if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) {
        return true;
    }

    // Finder patterns, their separators and the format information all sit
    // inside three 9x9 corner blocks.
    if ($x <= 8 && $y <= 8) {
        return true;
    }
    if ($x >= $size - 8 && $y <= 8) {
        return true;
    }
    if ($x <= 8 && $y >= $size - 8) {
        return true;
    }

    // Timing patterns.
    if ($x === 6 || $y === 6) {
        return true;
    }

    // Alignment patterns.
    $version = intdiv($size - 17, 4);
    $positions = BqrQrEncoder::alignmentPatternPositions($version);
    $last = count($positions) - 1;
    foreach ($positions as $cx) {
        foreach ($positions as $cy) {
            $overlapsFinder = ($cx === 6 && $cy === 6)
                || ($cx === 6 && $cy === $positions[$last])
                || ($cx === $positions[$last] && $cy === 6);
            if ($overlapsFinder) {
                continue;
            }
            if (abs($x - $cx) <= 2 && abs($y - $cy) <= 2) {
                return true;
            }
        }
    }

    // Version information blocks (3x6 each) from version 7 upward.
    if ($version >= 7) {
        if ($x >= $size - 11 && $x <= $size - 9 && $y <= 5) {
            return true;
        }
        if ($y >= $size - 11 && $y <= $size - 9 && $x <= 5) {
            return true;
        }
    }

    return false;
}

/**
 * Split interleaved codewords back into blocks and return the data codewords.
 *
 * @param array<int,int> $codewords
 * @param int $version
 * @param int $levelIndex
 * @return array<int, array<int,int>>|null  Blocks, each data + ecc.
 */
function bqr_deinterleave(array $codewords, $version, $levelIndex)
{
    $numBlocks = BqrQrEncoder::eccBlockCount($version, $levelIndex);
    $eccLen = BqrQrEncoder::eccCodewordsPerBlock($version, $levelIndex);
    $rawCodewords = BqrQrEncoder::numRawDataModules($version) >> 3;
    $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
    $shortBlockLen = intdiv($rawCodewords, $numBlocks);

    if (count($codewords) < $rawCodewords) {
        return null;
    }

    $blocks = array();
    for ($j = 0; $j < $numBlocks; $j++) {
        $blocks[$j] = array('data' => array(), 'ecc' => array(), 'dataLen' => 0);
        $blocks[$j]['dataLen'] = $shortBlockLen - $eccLen + ($j < $numShortBlocks ? 0 : 1);
    }

    // Mirror the encoder's interleave exactly, including the position it skips
    // for short blocks.
    $offset = 0;
    $rowCount = $shortBlockLen + 1;
    for ($i = 0; $i < $rowCount; $i++) {
        for ($j = 0; $j < $numBlocks; $j++) {
            if ($i === $shortBlockLen - $eccLen && $j < $numShortBlocks) {
                continue;
            }
            if ($offset >= count($codewords)) {
                return null;
            }
            $byte = $codewords[$offset];
            $offset++;
            if ($i < $blocks[$j]['dataLen']) {
                $blocks[$j]['data'][$i] = $byte;
            } else {
                $blocks[$j]['ecc'][] = $byte;
            }
        }
    }

    foreach ($blocks as $block) {
        if (count($block['data']) !== $block['dataLen'] || count($block['ecc']) !== $eccLen) {
            return null;
        }
    }

    return $blocks;
}

/**
 * Galois field exp/log tables for GF(256) with 0x11D.
 *
 * @return array{exp:array<int,int>, log:array<int,int>}
 */
function bqr_gf_tables()
{
    static $tables = null;
    if ($tables !== null) {
        return $tables;
    }

    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) {
            $x ^= 0x11D;
        }
    }
    for ($i = 255; $i < 512; $i++) {
        $exp[$i] = $exp[$i - 255];
    }

    $tables = array('exp' => $exp, 'log' => $log);

    return $tables;
}

/**
 * Multiply two GF(256) elements.
 *
 * @param int $a
 * @param int $b
 * @return int
 */
function bqr_gf_mul($a, $b)
{
    if ($a === 0 || $b === 0) {
        return 0;
    }
    $t = bqr_gf_tables();

    return $t['exp'][$t['log'][$a] + $t['log'][$b]];
}

/**
 * Compute the Reed-Solomon syndromes of a block. All zero means error free.
 *
 * @param array<int,int> $block
 * @param int $eccLen
 * @return array<int,int>
 */
function bqr_rs_syndromes(array $block, $eccLen)
{
    $t = bqr_gf_tables();
    $syndromes = array();
    // The generator polynomial has roots alpha^0 .. alpha^(eccLen-1).
    for ($i = 0; $i < $eccLen; $i++) {
        $value = 0;
        foreach ($block as $byte) {
            $value = bqr_gf_mul($value, $t['exp'][$i]) ^ $byte;
        }
        $syndromes[] = $value;
    }

    return $syndromes;
}

/**
 * Extract the data codewords from each block once the syndromes are clean.
 *
 * @param array $blocks
 * @param int $version
 * @param int $levelIndex
 * @return array<int,int>|null
 */
function bqr_collect_data(array $blocks, $version, $levelIndex)
{
    $data = array();
    foreach ($blocks as $block) {
        $codewordVector = array_merge($block['data'], $block['ecc']);
        $syndromes = bqr_rs_syndromes($codewordVector, count($block['ecc']));
        foreach ($syndromes as $syndrome) {
            if ($syndrome !== 0) {
                return null; // corruption we do not attempt to repair
            }
        }
        foreach ($block['data'] as $byte) {
            $data[] = $byte;
        }
    }

    return $data;
}

/**
 * Decode a bit stream of data codewords into the original text.
 *
 * @param array<int,int> $codewords
 * @param int $version
 * @return array{ok:bool, text:string, error:string}
 */
function bqr_decode_segments(array $codewords, $version)
{
    $bits = '';
    foreach ($codewords as $codeword) {
        $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
    }
    $length = strlen($bits);
    $offset = 0;
    $text = '';

    while ($offset + 4 <= $length) {
        $mode = bindec(substr($bits, $offset, 4));
        $offset += 4;

        if ($mode === 0) {
            break; // terminator
        }

        if ($mode === 1 || $mode === 2) {
            $countBits = $mode === 1
                ? ($version <= 9 ? 10 : ($version <= 26 ? 12 : 14))
                : ($version <= 9 ? 9 : ($version <= 26 ? 11 : 13));
            if ($offset + $countBits > $length) {
                break;
            }
            $count = bindec(substr($bits, $offset, $countBits));
            $offset += $countBits;

            if ($mode === 2) {
                $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';
                $pairs = intdiv($count, 2);
                for ($i = 0; $i < $pairs; $i++) {
                    if ($offset + 11 > $length) {
                        break 2;
                    }
                    $value = bindec(substr($bits, $offset, 11));
                    $offset += 11;
                    $text .= $alphabet[intdiv($value, 45)] . $alphabet[$value % 45];
                }
                if ($count % 2 === 1) {
                    if ($offset + 6 > $length) {
                        break;
                    }
                    $value = bindec(substr($bits, $offset, 6));
                    $offset += 6;
                    $text .= $alphabet[$value];
                }
            } else {
                for ($i = 0; $i < $count;) {
                    $group = min(3, $count - $i);
                    $widths = array(1 => 4, 2 => 7, 3 => 10);
                    $width = $widths[$group];
                    if ($offset + $width > $length) {
                        break 2;
                    }
                    $value = bindec(substr($bits, $offset, $width));
                    $offset += $width;
                    $text .= str_pad((string) $value, $group, '0', STR_PAD_LEFT);
                    $i += $group;
                }
            }
            continue;
        }

        if ($mode === 4) {
            $countBits = $version <= 9 ? 8 : 16;
            if ($offset + $countBits > $length) {
                break;
            }
            $count = bindec(substr($bits, $offset, $countBits));
            $offset += $countBits;
            if ($offset + $count * 8 > $length) {
                break;
            }
            for ($i = 0; $i < $count; $i++) {
                $text .= chr(bindec(substr($bits, $offset, 8)));
                $offset += 8;
            }
            continue;
        }

        if ($mode === 7) {
            // ECI designator: read it and keep going.
            $first = bindec(substr($bits, $offset, 8));
            $offset += 8;
            if (($first & 0x80) === 0) {
                continue;
            }
            if (($first & 0xC0) === 0x80) {
                $offset += 8;
                continue;
            }
            $offset += 16;
            continue;
        }

        return array('ok' => false, 'text' => '', 'error' => 'Unsupported QR segment mode (' . $mode . ').');
    }

    return array('ok' => true, 'text' => $text, 'error' => '');
}

/**
 * Read a QR payload out of an image file.
 *
 * @param string $path
 * @return array{ok:bool, payload:string, error:string, version:int, level:string, score:float}
 */
function bqr_decode_image($path)
{
    $failure = array('ok' => false, 'payload' => '', 'error' => '', 'version' => 0, 'level' => '', 'score' => 0.0);

    if (!bqr_can_render_png()) {
        $failure['error'] = 'The GD extension is required to read a QR image. Paste the QR payload instead.';

        return $failure;
    }

    $image = bqr_load_image($path);
    if ($image === null) {
        $failure['error'] = 'That file is not a readable image.';

        return $failure;
    }

    $lum = bqr_image_luminance($image);
    imagedestroy($image);

    $otsu = bqr_otsu_threshold($lum);
    $midpoint = bqr_midpoint_threshold($lum);

    // A clean PNG separates perfectly at the midpoint, while JPEG ringing and
    // tinted backgrounds need the extra candidates.
    $thresholds = array(
        $midpoint,
        $otsu,
        (int) (($midpoint + $otsu) / 2),
        (int) ($midpoint * 0.7),
        (int) ($midpoint * 1.3),
        128,
    );

    foreach ($thresholds as $threshold) {
        $threshold = max(1, min(254, $threshold));
        $matrix = bqr_locate_matrix($lum, $threshold);
        if ($matrix === null) {
            continue;
        }

        $format = bqr_read_format($matrix);
        if ($format === null) {
            continue;
        }

        $version = intdiv($matrix['size'] - 17, 4);
        $levelIndex = BqrQrEncoder::levelIndexOf($format['level']);
        $codewords = bqr_extract_codewords($matrix, $format['mask']);
        $blocks = bqr_deinterleave($codewords, $version, $levelIndex);
        if ($blocks === null) {
            continue;
        }

        $data = bqr_collect_data($blocks, $version, $levelIndex);
        if ($data === null) {
            continue;
        }

        $decoded = bqr_decode_segments($data, $version);
        if ($decoded['ok'] !== true || $decoded['text'] === '') {
            continue;
        }

        return array(
            'ok' => true,
            'payload' => $decoded['text'],
            'error' => '',
            'version' => $version,
            'level' => $format['level'],
            'score' => $matrix['score'],
        );
    }

    $failure['error'] = 'No readable QR code was found in that image. Try a sharper PNG, or paste the QR payload instead.';

    return $failure;
}
