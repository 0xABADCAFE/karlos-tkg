<?php

declare(strict_types=1);

namespace TKG\Mod\File\LevelProperties\Chunkable;

use TKG\Mod\Common;
use \stdClass;
use \RuntimeException;

abstract class MessageList implements Common\IBinaryEncodable {

    private const array ATTR_MAP = [
        "Narrative" => (0 << 14),
        "Default"   => (1 << 14),
        "Options"   => (2 << 14),
        "Other"     => (3 << 14),
    ];

    private const MAX_LENGTH = 240;

    public function __construct(
        private stdClass $oMessages,
        private Common\StringList $oStringList
    ) {}

    public function toBinary(): string {
        $aData = [];

        foreach ($this->oMessages as $iZoneID => $oInfo) {

            $iAttr = self::ATTR_MAP[$oInfo->Attr] ?? throw new RuntimeException(
                "Unrecognised message attribute " . $oInfo->Attr
            );
            $iLength = strlen($oInfo->Text);
            if ($iLength > self::MAX_LENGTH) {
                throw new RuntimeException(
                    "Text content too long (" . $iLength . " chars\n"
                );
            }
            $aData[] = $iZoneID << 16 | $iAttr | $iLength;
            $aData[] = $this->oStringList->add($oInfo->Text);
        }
        // Terminate with a -1
        $aData[] = 0xFFFFFFFF;

        return pack(
            self::PACK_LONG . self::PACK_MANY,
            ...$aData
        );
    }

}
