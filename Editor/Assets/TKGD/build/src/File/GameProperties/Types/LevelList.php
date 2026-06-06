<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Types;

use TKG\Mod\Common;
use TKG\Mod\File;
use \RuntimeException;
use \stdClass;

use function \pack, \preg_match, \preg_replace;

final class LevelList implements Common\IBinaryEncodable {

    private const array CODE_TO_BIT = [
        'A' => 1 << 0,
        'B' => 1 << 1,
        'C' => 1 << 2,
        'D' => 1 << 3,
        'E' => 1 << 4,
        'F' => 1 << 5,
        'G' => 1 << 6,
        'H' => 1 << 7,
        'I' => 1 << 8,
        'J' => 1 << 9,
        'K' => 1 << 10,
        'L' => 1 << 11,
        'M' => 1 << 12,
        'N' => 1 << 13,
        'O' => 1 << 14,
        'P' => 1 << 15,
    ];

    private const ALL_MASK = 0xFFFF;

    private int $iLevelMask = 0;

    public function __construct(string $sMatch) {
        if (!preg_match('/^[\s,]{0,}[A-P\*][A-P\*\s,]{0,}$/', $sMatch)) {
            throw new RuntimeException('Invalid LevelSet match ' . $sMatch);
        }
        $sMatch = preg_replace('/[^A-P\*]/', '', $sMatch);

        $iMask  = 0;
        $bInvert = false;
        $i = 0;
        while (isset($sMatch[$i])) {
            $sChar = $sMatch[$i++];
            if ('*' === $sChar) {
                if ($bInvert) {
                    throw new RuntimeException('Duplicate wildcard in LevelList match');
                } else {
                    $bInvert = true;
                }
            } else {
                if ($iMask & self::CODE_TO_BIT[$sChar]) {
                    throw new RuntimeException('Duplicate Level in LevelList match');
                } else {
                    $iMask |= self::CODE_TO_BIT[$sChar];
                }
            }
        }
        if ($bInvert) {
            $iMask = ~$iMask;
        }
        $this->iLevelMask = $iMask & self::ALL_MASK;
    }

    public function getMask(): int {
        return $this->iLevelMask;
    }

    public function toBinary(): string {
        return pack(self::PACK_WORD, $this->iLevelMask);
    }
}
