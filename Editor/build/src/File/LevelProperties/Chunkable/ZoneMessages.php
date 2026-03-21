<?php

declare(strict_types=1);

namespace TKG\Mod\File\LevelProperties\Chunkable;

use TKG\Mod\Common;
use \stdClass;
use \RuntimeException;

class ZoneMessages implements Common\IBinaryEncodable {

    public const string IDENT = 'ZMSG';

    public function __construct(
        private stdClass $aZoneMessages,
        private Common\StringList $oStringList
    ) {}

    public function toBinary(): string {
        $aData = [];
        foreach ($this->aZoneMessages as $iZoneID => $oInfo) {
            $aData[] = $iZoneID << 16 | $oInfo->attr | strlen($oInfo->text);
            $aData[] = $this->oStringList->add($oInfo->text);
        }
        // Terminate with a -1
        $aData[] = 0xFFFFFFFF;

        return pack(
            self::PACK_LONG . self::PACK_MANY,
            ...$aData
        );
    }

}
