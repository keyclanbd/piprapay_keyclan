<?php
/**
 * Minimal, dependency-free QR Code encoder (byte mode, versions 1-40, all four
 * error correction levels).
 *
 * Produces a module matrix as a 2D array of 0/1. Rendering to an image lives in
 * bangla-qr-image.php so this file never needs the GD extension.
 *
 * The structure follows the QR Code specification (ISO/IEC 18004).
 */

if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

final class BqrQrEncoder
{
    /** Error correction codewords per block, indexed [level][version]. */
    private const ECC_CODEWORDS_PER_BLOCK = array(
        // L
        array(-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30),
        // M
        array(-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28),
        // Q
        array(-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30),
        // H
        array(-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30),
    );

    /** Number of error correction blocks, indexed [level][version]. */
    private const NUM_ECC_BLOCKS = array(
        // L
        array(-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25),
        // M
        array(-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49),
        // Q
        array(-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68),
        // H
        array(-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81),
    );

    private const LEVEL_INDEX = array('L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3);
    private const FORMAT_BITS = array('L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2);

    /** @var int */
    private $version;
    /** @var int */
    private $size;
    /** @var string */
    private $level;
    /** @var array<int, array<int, int>> */
    private $modules = array();
    /** @var array<int, array<int, bool>> */
    private $isFunction = array();

    private function __construct($version, $level)
    {
        $this->version = $version;
        $this->size = $version * 4 + 17;
        $this->level = $level;

        for ($y = 0; $y < $this->size; $y++) {
            $this->modules[$y] = array_fill(0, $this->size, 0);
            $this->isFunction[$y] = array_fill(0, $this->size, false);
        }
    }

    /**
     * Encode text into a QR module matrix.
     *
     * @param string $text       Bytes to encode (UTF-8 passes through unchanged).
     * @param string $level      One of L, M, Q, H.
     * @param int    $minVersion Lowest version to consider (1-40); the encoder
     *                           still grows past it when needed.
     * @return array{size:int, version:int, mask:int, level:string, modules:array<int, array<int, int>>}
     * @throws InvalidArgumentException When the data does not fit in version 40.
     */
    public static function encode($text, $level = 'M', $minVersion = 1)
    {
        $text = (string) $text;
        $level = strtoupper((string) $level);
        if (!isset(self::LEVEL_INDEX[$level])) {
            $level = 'M';
        }
        $minVersion = max(1, min(40, (int) $minVersion));

        $bytes = $text === '' ? array() : array_values(unpack('C*', $text));
        $levelIndex = self::LEVEL_INDEX[$level];

        $version = 0;
        for ($v = $minVersion; $v <= 40; $v++) {
            $capacityBits = self::numDataCodewords($v, $levelIndex) * 8;
            $neededBits = 4 + ($v <= 9 ? 8 : 16) + count($bytes) * 8;
            if ($neededBits <= $capacityBits) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new InvalidArgumentException('The data is too long for a QR code.');
        }

        $encoder = new self($version, $level);
        $codewords = self::makeDataCodewords($bytes, $version, $levelIndex);
        $allCodewords = self::addEccAndInterleave($codewords, $version, $levelIndex);

        $encoder->drawFunctionPatterns();
        $encoder->drawCodewords($allCodewords);

        // Pick the mask with the lowest penalty; ties keep the earlier mask.
        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $encoder->applyMask($mask);
            $encoder->drawFormatBits($mask);
            $penalty = $encoder->penaltyScore();
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
            }
            $encoder->applyMask($mask); // XOR again to undo
        }

        $encoder->applyMask($bestMask);
        $encoder->drawFormatBits($bestMask);

        return array(
            'size' => $encoder->size,
            'version' => $version,
            'mask' => $bestMask,
            'level' => $level,
            'modules' => $encoder->modules,
        );
    }

    /**
     * Number of error correction blocks for a version/level pair.
     *
     * @param int $version
     * @param int $levelIndex
     * @return int
     */
    public static function eccBlockCount($version, $levelIndex)
    {
        return self::NUM_ECC_BLOCKS[$levelIndex][$version];
    }

    /**
     * Error correction codewords per block for a version/level pair.
     *
     * @param int $version
     * @param int $levelIndex
     * @return int
     */
    public static function eccCodewordsPerBlock($version, $levelIndex)
    {
        return self::ECC_CODEWORDS_PER_BLOCK[$levelIndex][$version];
    }

    /**
     * Level name to the internal index used by the tables.
     *
     * @param string $level
     * @return int
     */
    public static function levelIndexOf($level)
    {
        $level = strtoupper((string) $level);

        return self::LEVEL_INDEX[$level] ?? 1;
    }

    /**
     * Number of data codewords available for a version/level pair.
     *
     * @param int $version
     * @param int $levelIndex
     * @return int
     */
    public static function numDataCodewords($version, $levelIndex)
    {
        $rawCodewords = self::numRawDataModules($version) >> 3;

        return $rawCodewords
            - self::ECC_CODEWORDS_PER_BLOCK[$levelIndex][$version] * self::NUM_ECC_BLOCKS[$levelIndex][$version];
    }

    /**
     * Total number of modules available for data + ECC in a version.
     *
     * @param int $version
     * @return int
     */
    public static function numRawDataModules($version)
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $numAlign = intdiv($version, 7) + 2;
            $result -= (25 * $numAlign - 10) * $numAlign - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }

        return $result;
    }

    /**
     * Build the padded data codeword sequence (byte mode).
     *
     * @param array<int,int> $bytes
     * @param int $version
     * @param int $levelIndex
     * @return array<int,int>
     */
    private static function makeDataCodewords(array $bytes, $version, $levelIndex)
    {
        $capacityBits = self::numDataCodewords($version, $levelIndex) * 8;
        $bits = '';

        $bits .= '0100'; // byte mode
        $countBits = $version <= 9 ? 8 : 16;
        $bits .= str_pad(decbin(count($bytes)), $countBits, '0', STR_PAD_LEFT);
        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $terminator = min(4, $capacityBits - strlen($bits));
        $bits .= str_repeat('0', $terminator);
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);

        $padBytes = array(0xEC, 0x11);
        $padIndex = 0;
        while (strlen($bits) < $capacityBits) {
            $bits .= str_pad(decbin($padBytes[$padIndex % 2]), 8, '0', STR_PAD_LEFT);
            $padIndex++;
        }

        $codewords = array();
        for ($i = 0; $i < $capacityBits; $i += 8) {
            $codewords[] = bindec(substr($bits, $i, 8));
        }

        return $codewords;
    }

    /**
     * Split data into blocks, append Reed-Solomon ECC, and interleave.
     *
     * @param array<int,int> $data
     * @param int $version
     * @param int $levelIndex
     * @return array<int,int>
     */
    private static function addEccAndInterleave(array $data, $version, $levelIndex)
    {
        $numBlocks = self::NUM_ECC_BLOCKS[$levelIndex][$version];
        $blockEccLen = self::ECC_CODEWORDS_PER_BLOCK[$levelIndex][$version];
        $rawCodewords = self::numRawDataModules($version) >> 3;
        $numShortBlocks = $numBlocks - $rawCodewords % $numBlocks;
        $shortBlockLen = intdiv($rawCodewords, $numBlocks);

        $divisor = self::reedSolomonDivisor($blockEccLen);
        $blocks = array();
        $offset = 0;

        for ($i = 0; $i < $numBlocks; $i++) {
            $dataLen = $shortBlockLen - $blockEccLen + ($i < $numShortBlocks ? 0 : 1);
            $blockData = array_slice($data, $offset, $dataLen);
            $offset += $dataLen;

            $ecc = self::reedSolomonRemainder($blockData, $divisor);

            // Short blocks get a padding byte so every block has the same length
            // for interleaving; it is skipped again when reading out.
            if ($i < $numShortBlocks) {
                $blockData[] = 0;
            }

            $blocks[] = array_merge($blockData, $ecc);
        }

        $result = array();
        $blockLength = count($blocks[0]);
        for ($i = 0; $i < $blockLength; $i++) {
            for ($j = 0; $j < $numBlocks; $j++) {
                if ($i !== $shortBlockLen - $blockEccLen || $j >= $numShortBlocks) {
                    $result[] = $blocks[$j][$i];
                }
            }
        }

        return $result;
    }

    /**
     * Generator polynomial for Reed-Solomon ECC of the given degree.
     *
     * @param int $degree
     * @return array<int,int>
     */
    private static function reedSolomonDivisor($degree)
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;

        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }

        return $result;
    }

    /**
     * Remainder of the data polynomial divided by the generator polynomial.
     *
     * @param array<int,int> $data
     * @param array<int,int> $divisor
     * @return array<int,int>
     */
    private static function reedSolomonRemainder(array $data, array $divisor)
    {
        $degree = count($divisor);
        $result = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= self::gfMultiply($divisor[$i], $factor);
            }
        }

        return $result;
    }

    /**
     * Multiplication in GF(2^8) with the QR polynomial 0x11D.
     *
     * @param int $x
     * @param int $y
     * @return int
     */
    private static function gfMultiply($x, $y)
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0x1FF;
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z & 0xFF;
    }

    /**
     * Draw finder, timing and alignment patterns, then reserve format/version areas.
     *
     * @return void
     */
    private function drawFunctionPatterns()
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunctionModule(6, $i, $i % 2 === 0);
            $this->setFunctionModule($i, 6, $i % 2 === 0);
        }

        $this->drawFinderPattern(3, 3);
        $this->drawFinderPattern($this->size - 4, 3);
        $this->drawFinderPattern(3, $this->size - 4);

        $positions = self::alignmentPatternPositions($this->version);
        $numAlign = count($positions);
        for ($i = 0; $i < $numAlign; $i++) {
            for ($j = 0; $j < $numAlign; $j++) {
                $overlapsFinder = ($i === 0 && $j === 0)
                    || ($i === 0 && $j === $numAlign - 1)
                    || ($i === $numAlign - 1 && $j === 0);
                if ($overlapsFinder) {
                    continue;
                }
                $this->drawAlignmentPattern($positions[$i], $positions[$j]);
            }
        }

        $this->drawFormatBits(0); // placeholder, redrawn once the mask is chosen
        $this->drawVersionBits();
    }

    /**
     * @param int $x Center column.
     * @param int $y Center row.
     * @return void
     */
    private function drawFinderPattern($x, $y)
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $this->setFunctionModule($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    /**
     * @param int $x Center column.
     * @param int $y Center row.
     * @return void
     */
    private function drawAlignmentPattern($x, $y)
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunctionModule($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /**
     * Format information: error level and mask, BCH protected.
     *
     * @param int $mask
     * @return void
     */
    private function drawFormatBits($mask)
    {
        $data = (self::FORMAT_BITS[$this->level] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunctionModule(8, $i, self::bitAt($bits, $i));
        }
        $this->setFunctionModule(8, 7, self::bitAt($bits, 6));
        $this->setFunctionModule(8, 8, self::bitAt($bits, 7));
        $this->setFunctionModule(7, 8, self::bitAt($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunctionModule(14 - $i, 8, self::bitAt($bits, $i));
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunctionModule($this->size - 1 - $i, 8, self::bitAt($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunctionModule(8, $this->size - 15 + $i, self::bitAt($bits, $i));
        }

        // The always-dark module.
        $this->setFunctionModule(8, $this->size - 8, true);
    }

    /**
     * Version information, only present from version 7 upward.
     *
     * @return void
     */
    private function drawVersionBits()
    {
        if ($this->version < 7) {
            return;
        }

        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;

        for ($i = 0; $i < 18; $i++) {
            $bit = self::bitAt($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunctionModule($a, $b, $bit);
            $this->setFunctionModule($b, $a, $bit);
        }
    }

    /**
     * Place the interleaved codewords in the two-module-wide zigzag.
     *
     * @param array<int,int> $data
     * @return void
     */
    private function drawCodewords(array $data)
    {
        $totalBits = count($data) * 8;
        $i = 0;

        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // skip the vertical timing pattern column
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $totalBits) {
                        $this->modules[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1);
                        $i++;
                    }
                }
            }
        }
    }

    /**
     * XOR a mask over all non-function modules. Applying twice undoes it.
     *
     * @param int $mask
     * @return void
     */
    private function applyMask($mask)
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = false;
                switch ($mask) {
                    case 0: $invert = ($x + $y) % 2 === 0; break;
                    case 1: $invert = $y % 2 === 0; break;
                    case 2: $invert = $x % 3 === 0; break;
                    case 3: $invert = ($x + $y) % 3 === 0; break;
                    case 4: $invert = (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0; break;
                    case 5: $invert = ($x * $y) % 2 + ($x * $y) % 3 === 0; break;
                    case 6: $invert = ((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0; break;
                    case 7: $invert = ((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0; break;
                }
                if ($invert) {
                    $this->modules[$y][$x] ^= 1;
                }
            }
        }
    }

    /**
     * Mask penalty score, lower is better (ISO/IEC 18004 section 8.8.2).
     *
     * @return int
     */
    private function penaltyScore()
    {
        $penalty = 0;
        $size = $this->size;

        // Rule 1: runs of five or more identical modules, rows then columns.
        for ($y = 0; $y < $size; $y++) {
            $runColor = $this->modules[$y][0];
            $runLength = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runLength++;
                    if ($runLength === 5) {
                        $penalty += 3;
                    } elseif ($runLength > 5) {
                        $penalty++;
                    }
                } else {
                    $runColor = $this->modules[$y][$x];
                    $runLength = 1;
                }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            $runColor = $this->modules[0][$x];
            $runLength = 1;
            for ($y = 1; $y < $size; $y++) {
                if ($this->modules[$y][$x] === $runColor) {
                    $runLength++;
                    if ($runLength === 5) {
                        $penalty += 3;
                    } elseif ($runLength > 5) {
                        $penalty++;
                    }
                } else {
                    $runColor = $this->modules[$y][$x];
                    $runLength = 1;
                }
            }
        }

        // Rule 2: 2x2 blocks of one colour.
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $colour = $this->modules[$y][$x];
                if ($colour === $this->modules[$y][$x + 1]
                    && $colour === $this->modules[$y + 1][$x]
                    && $colour === $this->modules[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        // Rule 3: finder-like 1:1:3:1:1 patterns with four light modules beside them.
        // Both orientations are matched as an exact 11-module window.
        $badPatterns = array('10111010000', '00001011101');
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x <= $size - 11; $x++) {
                $window = '';
                for ($k = 0; $k < 11; $k++) {
                    $window .= $this->modules[$y][$x + $k];
                }
                if (in_array($window, $badPatterns, true)) {
                    $penalty += 40;
                }
            }
        }
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y <= $size - 11; $y++) {
                $window = '';
                for ($k = 0; $k < 11; $k++) {
                    $window .= $this->modules[$y + $k][$x];
                }
                if (in_array($window, $badPatterns, true)) {
                    $penalty += 40;
                }
            }
        }

        // Rule 4: deviation from a 50% dark ratio.
        $dark = 0;
        foreach ($this->modules as $row) {
            foreach ($row as $module) {
                $dark += $module;
            }
        }
        $total = $size * $size;
        $k = (int) (abs($dark * 20 - $total * 10) / $total);
        $penalty += $k * 10;

        return $penalty;
    }

    /**
     * @param int  $x
     * @param int  $y
     * @param bool $dark
     * @return void
     */
    private function setFunctionModule($x, $y, $dark)
    {
        $this->modules[$y][$x] = $dark ? 1 : 0;
        $this->isFunction[$y][$x] = true;
    }

    /**
     * Row/column centres of the alignment patterns for a version.
     *
     * @param int $version
     * @return array<int,int>
     */
    public static function alignmentPatternPositions($version)
    {
        if ($version === 1) {
            return array();
        }

        $numAlign = intdiv($version, 7) + 2;
        $step = $version === 32
            ? 26
            : intdiv($version * 4 + $numAlign * 2 + 1, $numAlign * 2 - 2) * 2;

        $result = array_fill(0, $numAlign, 0);
        $result[0] = 6;
        $pos = $version * 4 + 10;
        for ($i = $numAlign - 1; $i >= 1; $i--) {
            $result[$i] = $pos;
            $pos -= $step;
        }

        return $result;
    }

    /**
     * @param int $value
     * @param int $index
     * @return bool
     */
    private static function bitAt($value, $index)
    {
        return (($value >> $index) & 1) === 1;
    }
}
