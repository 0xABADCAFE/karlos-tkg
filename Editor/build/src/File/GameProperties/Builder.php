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
    private const string INVENTORY_LIMITS_IDENT = 'INVL';
    private const string REWARD_LIST_IDENT = 'RWRD';
    private const string SPECIAL_AMMO_BONUS_IDENT = 'SPAB';
    private const string ACHIEVEMENTS_IDENT = 'ACHV';

    private array $aAlienTypes             = [];
    private array $aPlayerAmmoTypes        = [];
    private array $aPlayerSpecialAmmoTypes = [];

    protected function getSubFormat(stdClass $oData): File\Header\SubFormat {
        if (!isset($oData->DataType) || self::SUBFORMAT_NAME !== $oData->DataType) {
            throw new RuntimeException('Missing or unexpected DataType');
        }
        return File\Header\SubFormat::Game;
    }

    protected function preprocess(
        File\Header\Section $oHeader,
        stdClass $oData,
        Common\StringList $oStringList
    ): void {
        printf(
            "Building %s ['%s' version: %d.%d, target: %d.%d]...\n",
            $this->sTargetPath,
            $oData->Header->Description ?? '<unnamed>',
            $oHeader->oVersion->iMajor,
            $oHeader->oVersion->iMinor,
            $oHeader->oRequires->iMajor,
            $oHeader->oRequires->iMinor
        );
        if (empty($oData->Import->LinkDefs)) {
            throw new RuntimeException('LinkDefs import cannot be empty');
        }
        $this->processLinkDefs($oData->Import->LinkDefs);
    }

    /**
     * @return array<File\Chunk>
     */
    public function getChunks(stdClass $oData, Common\StringList $oStringList): array {

        $oRewardList = new RewardList(
            $this->aPlayerAmmoTypes,
            $this->aPlayerSpecialAmmoTypes,
            $oStringList
        );

        $aChunks = [];

        if (!empty($oData->DefaultInventoryLimits)) {
            $aChunks[] = $this->buildDefaultInventoryLimitsChunk($oData, $oStringList);
        }

        if (!empty($oData->SpecialAmmoBonuses)) {
            $aChunks[] = $this->buildSpecialAmmoBonusesChunk($oData, $oRewardList);
        }

        if (!empty($oData->Achievements)) {
            $aChunks[] = $this->buildAchievemntsChunk($oData, $oRewardList, $oStringList);
        }

        if (!$oRewardList->isEmpty()) {
            $aChunks[] = new File\Chunk(
                self::REWARD_LIST_IDENT,
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

        if (!empty($oLinkDefs->PlayerSpecialAmmoTypes)) {
            $this->aPlayerSpecialAmmoTypes = (array)$oLinkDefs->PlayerSpecialAmmoTypes;
            printf("Got %d Player Special Ammo Types\n", count($this->aPlayerSpecialAmmoTypes));
        }
    }

    private function buildDefaultInventoryLimitsChunk(
        stdClass $oData,
        Common\StringList $oStringList
    ): File\Chunk {
        return new File\Chunk(
            self::INVENTORY_LIMITS_IDENT,
            new class (
                $oData->DefaultInventoryLimits,
                $this->aPlayerAmmoTypes,
                $this->aPlayerSpecialAmmoTypes
            ) implements Common\IBinaryEncodable {

                private const int NUM_AMMO_TYPES   = 20;
                private const int DEF_AMMO_LIMIT   = 32767;
                private const int DEF_HEALTH_LIMIT = 32767;
                private const int DEF_FUEL_LIMIT   = 255;

                public function __construct(
                    private stdClass $oInventoryLimits,
                    private array $aPlayerAmmoTypes,
                    private array $aPlayerSpecialAmmoTypes
                ) {}

                public function toBinary(): string {
                    $aMaxAmmoCounts = array_fill(0, self::NUM_AMMO_TYPES - 1, 0);
                    foreach ($this->aPlayerAmmoTypes as $sName => $iIndex) {
                        $aMaxAmmoCounts[$iIndex] = self::DEF_AMMO_LIMIT;
                    }
                    foreach ($this->oInventoryLimits->MaxAmmo as $sAmmoKey => $iValue) {
                        $iIndex = $this->aPlayerAmmoTypes[$sAmmoKey]
                            ?? $this->aPlayerSpecialAmmoTypes[$sAmmoKey]
                            ?? throw new RuntimeException('Unknown ammunition type ' . $sAmmoKey);
                        $aMaxAmmoCounts[$iIndex] = $iValue;
                    }

                    return pack(
                        self::PACK_WORD . self::PACK_MANY,
                        $this->oInventoryLimits->MaxHealth ?? self::DEF_HEALTH_LIMIT,
                        $this->oInventoryLimits->MaxJetpackFuel ?? self::DEF_FUEL_LIMIT,
                        ...$aMaxAmmoCounts
                    );
                }
            }
        );
    }

    private function buildSpecialAmmoBonusesChunk(
        stdClass $oData,
        RewardList $oRewardList
    ): File\Chunk {
        return new File\Chunk(
            self::SPECIAL_AMMO_BONUS_IDENT,
            new class (
                $this->aPlayerSpecialAmmoTypes,
                $oData->SpecialAmmoBonuses,
                $oRewardList
            ) implements Common\IBinaryEncodable {
                public function __construct(
                    private readonly array    $aPlayerSpecialAmmoTypes,
                    private readonly stdClass $oSpecialAmmoBonuses,
                    private RewardList $oRewardList
                ) {}

                public function toBinary(): string {
                    $sData = '';
                    $i = 0;
                    $sPack = self::PACK_WORD . self::PACK_WORD . self::PACK_LONG;
                    foreach ($this->oSpecialAmmoBonuses as $sSpecialAmmoType => $oRewardData) {
                        $iSpecialAmmoType = $this->aPlayerSpecialAmmoTypes[$sSpecialAmmoType] ??
                            throw new RuntimeException(
                                'Unknown Special Ammo Type: ' . $sSpecialAmmoType
                            );
                        $iOffset = $this->oRewardList->add($oRewardData);
                        $sData .= pack($sPack, $i++, $iSpecialAmmoType, $iOffset);
                    }
                    // -1 terminate the list
                    $sData .= pack(self::PACK_WORD, 0xFFFF);
                    return $sData;
                }
            }
        );
    }

    private function buildAchievemntsChunk(
        stdClass $oData,
        RewardList $oRewardList,
        StringList $oStringList
    ): File\Chunk {
        return new File\Chunk(
            self::ACHIEVEMENTS_IDENT,
            new class (
                $oData->Achievements,
                $oRewardList,
                $oStringList
            ) implements Common\IBinaryEncodable {
                public function __construct(
                    private stdClass $oAchievements,
                    private RewardList $oRewardList,
                    private StringList $oStringList
                ) {}

                public function toBinary(): string {
                    return pack('N', 0xABADCAFE);
                }
            }
        );
    }
}
