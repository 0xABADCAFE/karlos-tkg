<?php

declare(strict_types=1);

namespace TKG\Mod\File\LevelProperties;

use TKG\Mod\Common;
use TKG\Mod\File;
use \stdClass;
use \RuntimeException;

class Builder extends File\Builder implements Common\IBinaryProperties {

    private const string SUBFORMAT_NAME = 'Level';

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
        if (isset($oData->ZoneErrata->BackdropDeletions)) {
            $aChunks[] = $this->buildZoneBackropErrata($oData, $oStringList);
        }
        if (isset($oData->ZoneMessages)) {
            $aChunks[] = $this->buildZoneMessages($oData, $oStringList);
        }
        if (isset($oData->ObjectMessages)) {
            $aChunks[] = $this->buildObjectMessages($oData, $oStringList);
        }
        return $aChunks;
    }

    private function buildZonePVSErrata(
        stdClass $oData,
        Common\StringList $oStringList
    ): File\Chunk {
        echo "Processing Zone PVS Deletions...\n";
        return new File\Chunk(
            Chunkable\ZonePVSDeletions::IDENT,
            new Chunkable\ZonePVSDeletions($oData->ZoneErrata->PVSDeletions)
        );
    }

    private function buildZoneBackropErrata(
        stdClass $oData,
        Common\StringList $oStringList
    ): File\Chunk {
        echo "Processing Zone Backdrop Deletions...\n";
        return new File\Chunk(
            Chunkable\ZoneBackdropDeletions::IDENT,
            new Chunkable\ZoneBackdropDeletions($oData->ZoneErrata->BackdropDeletions)
        );
    }


    private function buildZoneMessages(
        stdClass $oData,
        Common\StringList $oStringList
    ): File\Chunk {
        echo "Processing Zone Messages...\n";
        return new File\Chunk(
            Chunkable\ZoneMessages::IDENT,
            new Chunkable\ZoneMessages($oData->ZoneMessages, $oStringList)
        );
    }

    private function buildObjectMessages(
        stdClass $oData,
        Common\StringList $oStringList
    ): File\Chunk {
        echo "Processing Zone Messages...\n";
        return new File\Chunk(
            Chunkable\ObjectMessages::IDENT,
            new Chunkable\ObjectMessages($oData->ObjectMessages, $oStringList)
        );
    }
}
