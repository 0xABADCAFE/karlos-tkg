<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Types;

use TKG\Mod\Common;
use TKG\Mod\File;
use \RuntimeException;
use \stdClass;

use function \pack, \preg_match, \preg_replace;

final class LevelList implements Common\IBinaryEncodable {

    private int $iMask = 0;

    public function __construct(
        array $aAlienNames,
        array $aAlienTypes
    ) {
        foreach ($aAlienNames as $sAlienType) {
            $iLinkID = $aAlienTypes[$sAlienType] ??
                throw new RuntimeException('Unknown Alien Type: ' . $sAlienType);
            $this->iMask |= (1 << $iLinkID);
        }
    }

    public function getMask(): int {
        return $this->iMask;
    }

    public function toBinary(): string {
        return pack(self::PACK_LONG, $this->iMask);
    }
}
