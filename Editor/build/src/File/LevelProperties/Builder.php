<?php

declare(strict_types=1);

namespace TKG\Mod\File\LevelProperties;

use TKG\Mod\Common;
use TKG\Mod\File;
use \stdClass;
use \RuntimeException;

class Builder extends File\Builder implements Common\IBinaryProperties {

    private const string SUBFORMAT_NAME = 'Level';
    private const string PVS_ERRATA_IDENT = 'PVSE';
    private const string BACKDROP_ERRATA_IDENT = 'BKDE';
    private const string ZONE_MESSAGE_IDENT = 'ZMSG';
    private const string OBJECT_MESSAGE_IDENT = 'OMSG';

    protected function getSubFormat(stdClass $oData): File\Header\SubFormat {
        if (!isset($oData->DataType) || self::SUBFORMAT_NAME !== $oData->DataType) {
            throw new RuntimeException('Missing or unexpected DataType');
        }
        return File\Header\SubFormat::Level;
    }

    protected function preprocess(
        File\Header\Section $oHeader,
        stdClass $oData,
        Common\StringList $oStringList
    ): void {
        printf(
            "Building %s ['%s' version: %d.%d, target: %d.%d]...\n",
            $this->sTargetPath,
            $oData->Header->Description ?? '<No Description>',
            $oHeader->oVersion->iMajor,
            $oHeader->oVersion->iMinor,
            $oHeader->oRequires->iMajor,
            $oHeader->oRequires->iMinor
        );
    }

    public function getChunks(stdClass $oData, Common\StringList $oStringList): array {
        $aChunks = [];
        if (isset($oData->ZoneErrata->PVSDeletions)) {
            $aChunks[] = $this->buildZonePVSErrata($oData, $oStringList);
        }
        if (isset($oData->ZoneErrata->NoSkyVisible)) {
            $aChunks[] = $this->buildZoneBackropErrata($oData, $oStringList);
        }
        if (isset($oData->ZoneMessages)) {
            $aChunks[] = $this->buildZoneMessages($oData, $oStringList);
        }
        return $aChunks;
    }

    private function buildZonePVSErrata(
        stdClass $oData,
        Common\StringList $oStringList
    ): File\Chunk {
        return new File\Chunk(
            self::PVS_ERRATA_IDENT,
            new class($oData->ZoneErrata->PVSDeletions) implements Common\IBinaryEncodable {
                public function __construct(private stdClass $aPVSErrata) {}
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
        );
    }

    private function buildZoneBackropErrata(
        stdClass $oData,
        Common\StringList $oStringList
    ): File\Chunk {
        return new File\Chunk(
            self::BACKDROP_ERRATA_IDENT,
            new class($oData->ZoneErrata->NoSkyVisible) implements Common\IBinaryEncodable {
                public function __construct(private array $aBackdropErrata) {}
                public function toBinary(): string {
                    $this->aBackdropErrata[] = -1;
                    return pack(
                        self::PACK_WORD . self::PACK_MANY,
                        ...$this->aBackdropErrata
                    );
                }
            }
        );
    }


    private function buildZoneMessages(
        stdClass $oData,
        Common\StringList $oStringList
    ): File\Chunk {
        return new File\Chunk(
            self::ZONE_MESSAGE_IDENT,
            new class($oData->ZoneMessages, $oStringList) implements Common\IBinaryEncodable {
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
                    // Terminate with a -1 zone reference
                    $aData[] = 0xFFFF0000;

                    return pack(
                        self::PACK_LONG . self::PACK_MANY,
                        ...$aData
                    );
                }
            }
        );
    }
}
