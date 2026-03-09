<?php

/**
 * TKG Redux Mod Builder
 */
declare(strict_types=1);

namespace TKG\Mod\File\Header;

use TKG\Mod\Common;
use \RangeException;
use function \pack;

/**
 * Major.Minor version class
 */
final class Version implements Common\IBinaryEncodable {
    public const int FIXED_SIZE = self::SIZE_LONG;
    private const int MAX = 65535;

    public function __construct(
        public readonly int $iMajor,
        public readonly int $iMinor
    ) {
        assert(
            $iMajor >= 0 && $iMajor <= self::MAX &&
            $iMinor >= 0 && $iMinor <= self::MAX,
            new RangeException()
        );
    }

    public function toBinary(): string {
        return pack(
            self::PACK_WORD . self::PACK_WORD,
            $this->iMajor,
            $this->iMinor
        );
    }
}



