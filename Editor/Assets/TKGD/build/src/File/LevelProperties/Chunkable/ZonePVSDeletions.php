<?php

declare(strict_types=1);

namespace TKG\Mod\File\LevelProperties\Chunkable;

use TKG\Mod\Common;
use \stdClass;
use \RuntimeException;

class ZonePVSDeletions implements Common\IBinaryEncodable {

    public const string IDENT = 'PVSD';

    public function __construct(private stdClass $aPVSErrata) {

    }

    public function toBinary(): string {
        $sData = '';
        foreach ($this->aPVSErrata as $iZoneID => $aRemoveZoneIDs) {
            $aRemoveZoneIDs[] = -1;
            $sData .= pack(
                self::PACK_WORD . self::PACK_MANY,
                $iZoneID,
                ...$aRemoveZoneIDs
            );
        }
        $sData .= pack(self::PACK_WORD, -1);
        return $sData;
    }
}
