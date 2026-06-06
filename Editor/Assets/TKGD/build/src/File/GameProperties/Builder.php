<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties;

use TKG\Mod\Common;
use TKG\Mod\File;
use \stdClass;
use \RuntimeException;

use function \pack, \printf;

/**
 *  Builder implementation for the main game properties file. This makes use of anonymous
 *  implementations of the IBinaryEncodable interface as the data providers for Chunks.
 */
class Builder extends File\Builder {

    private const string SUBFORMAT_NAME = 'Game';

    private array $aAlienTypes             = [];
    private array $aPlayerAmmoTypes        = [];
    private array $aPlayerSpecialAmmoTypes = [];
    private array $aPlayerWeaponTypes      = [];

    protected function getSubFormat(stdClass $oGameProperties): File\Header\SubFormat {
        if (!isset($oGameProperties->DataType) || self::SUBFORMAT_NAME !== $oGameProperties->DataType) {
            throw new RuntimeException('Missing or unexpected DataType');
        }
        return File\Header\SubFormat::Game;
    }

    protected function preprocess(
        File\Header\Section $oHeader,
        stdClass $oGameProperties,
        Common\StringList $oStringList
    ): void {
        printf(
            "Building %s ['%s' version: %d.%d, target: %d.%d]...\n",
            $this->sTargetPath,
            $oGameProperties->Header->Description ?? '<unnamed>',
            $oHeader->oVersion->iMajor,
            $oHeader->oVersion->iMinor,
            $oHeader->oRequires->iMajor,
            $oHeader->oRequires->iMinor
        );
        if (empty($oGameProperties->Import->LinkDefs)) {
            throw new RuntimeException('LinkDefs import cannot be empty');
        }
        $this->processLinkDefs($oGameProperties->Import->LinkDefs);
    }

    /**
     * @return array<File\Chunk>
     */
    public function getChunks(stdClass $oGameProperties, Common\StringList $oStringList): array {

        $oRewardList = new Chunkable\RewardList(
            $this->aPlayerAmmoTypes,
            $this->aPlayerSpecialAmmoTypes,
            $oStringList
        );

        $aChunks = [];

        if (!empty($oGameProperties->DefaultInventoryLimits)) {
            $aChunks[] = $this->buildDefaultInventoryLimitsChunk($oGameProperties, $oStringList);
        }

        if (!empty($oGameProperties->SpecialAmmoBonuses)) {
            $aChunks[] = $this->buildSpecialAmmoBonusesChunk($oGameProperties, $oRewardList);
        }

        if (!empty($oGameProperties->WeaponAdjustments)) {
            $aChunks[] = $this->buildWeaponAdjustmentsChunk($oGameProperties);
        }

        if (!empty($oGameProperties->Achievements)) {
            $aChunks[] = $this->buildAchievemntsChunk($oGameProperties, $oRewardList, $oStringList);
        }

        if (!$oRewardList->isEmpty()) {
            $aChunks[] = new File\Chunk(
                Chunkable\RewardList::IDENT,
                $oRewardList
            );
        }

        return $aChunks;
    }

    private function processLinkDefs(stdClass $oLinkDefs): void {
        if (empty($oLinkDefs->AlienTypes)) {
            throw new RuntimeException('AlienTypes cannot be empty');
        }
        $this->aAlienTypes = (array)$oLinkDefs->AlienTypes;
        printf("Got %d Alien Types\n", count($this->aAlienTypes));
        if (empty($oLinkDefs->PlayerAmmoTypes)) {
            throw new RuntimeException('PlayerAmmoTypes cannot be empty');
        }
        $this->aPlayerAmmoTypes = (array)$oLinkDefs->PlayerAmmoTypes;
        printf("Got %d Player Ammo Types\n", count($this->aPlayerAmmoTypes));

        if (empty($oLinkDefs->PlayerWeapons)) {
            throw new RuntimeException('PlayerWeapons cannot be empty');
        }

        $this->aPlayerWeaponTypes = (array)$oLinkDefs->PlayerWeapons;
        printf("Got %d Player Weapon Types\n", count($this->aPlayerWeaponTypes));

        if (!empty($oLinkDefs->PlayerSpecialAmmoTypes)) {
            $this->aPlayerSpecialAmmoTypes = (array)$oLinkDefs->PlayerSpecialAmmoTypes;
            printf("Got %d Player Special Ammo Types\n", count($this->aPlayerSpecialAmmoTypes));
        }
    }

    private function buildDefaultInventoryLimitsChunk(
        stdClass $oGameProperties,
        Common\StringList $oStringList
    ): File\Chunk {
        echo "Processing Default Inventory Limits...\n";
        return new File\Chunk(
            Chunkable\DefaultInventoryLimits::IDENT,
            new Chunkable\DefaultInventoryLimits(
                $oGameProperties->DefaultInventoryLimits,
                $this->aPlayerAmmoTypes,
                $this->aPlayerSpecialAmmoTypes
            )
        );
    }

    private function buildSpecialAmmoBonusesChunk(
        stdClass $oGameProperties,
        Chunkable\RewardList $oRewardList
    ): File\Chunk {
        echo "Processing Special Ammo Bonuses...\n";
        return new File\Chunk(
            Chunkable\SpecialAmmoBonuses::IDENT,
            new Chunkable\SpecialAmmoBonuses(
                $this->aPlayerSpecialAmmoTypes,
                $oGameProperties->SpecialAmmoBonuses,
                $oRewardList
            )
        );
    }

    private function buildWeaponAdjustmentsChunk(
        stdClass $oGameProperties
    ): File\Chunk {
        echo "Processing Weapon Adjustments...\n";
        return new File\Chunk(
            Chunkable\WeaponAdjustments::IDENT,
            new Chunkable\WeaponAdjustments(
                $this->aPlayerWeaponTypes,
                $oGameProperties->WeaponAdjustments
            )
        );
    }

    private function buildAchievemntsChunk(
        stdClass $oGameProperties,
        Chunkable\RewardList $oRewardList,
        Common\StringList $oStringList
    ): File\Chunk {
        echo "Processing Achievements...\n";
        return new File\Chunk(
            Chunkable\Achievements::IDENT,
            new Chunkable\Achievements(
                $oGameProperties->Achievements,
                $oStringList,
                $oRewardList,
                $this->aPlayerAmmoTypes,
                $this->aPlayerSpecialAmmoTypes,
                $this->aAlienTypes
            )
        );
    }
}
