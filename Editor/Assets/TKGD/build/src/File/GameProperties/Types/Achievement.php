<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Types;

use TKG\Mod\Common;
use TKG\Mod\File;
use TKG\Mod\File\GameProperties\Chunkable;
use \RuntimeException;
use \LogicException;
use \stdClass;

use function \pack, \strlen;
/**
 * Parses and encodes an Achievement structure.
 */
class Achievement implements Common\IBinaryEncodable {

    private const ENC_SIZE = 32;

    // Count arguments are generally uint32 but we need to scale back a bit!
    private const MAX_COUNT = 1000000; // For now.

    private const array RULE_TYPES = [
        'KillCount'      => 0,
        'GroupKillCount' => 1,
        'ZoneFound'      => 2,
        'TimeImproved'   => 3,
        'PlayerDied'     => 4,
        'Collected'      => 5,
    ];

    private const array RULE_PARAM_FN = [
        'parseKillCountParams',
        'parseGroupKillCountParams',
        'parseZoneFoundParams',
        'parseMaskedLevelCountParams',
        'parseMaskedLevelCountParams',
        'parseCollectedParams',
    ];

    public int $iOrder = 0;
    private int $iRuleType = 0;
    private int $iDescOffset = 0;
    private int $iRewardOffset = 0;
    private string $sEncParams = '';
    /**
     * RAII constructor.
     * Fail for invalid input.
     */
    public function __construct(
        stdClass $oSource,
        Common\StringList $oStringList,
        Chunkable\RewardList $oRewardList,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes,
        array $aAlienTypes
    ) {
        $this->validate($oSource);

        if (!empty($oSource->Order)) {
            $this->iOrder = (int) $oSource->Order;
        }

        $this->iRuleType = self::RULE_TYPES[$oSource->Rule];
        $this->sEncParams = $this->parseParams(
            $oSource->Params,
            $aPlayerAmmoTypes,
            $aPlayerSpecialAmmoTypes,
            $aAlienTypes
        );
        $this->iDescOffset   = $oStringList->add($oSource->Description);

        if (!empty($oSource->Reward)) {
            $this->iRewardOffset = $oRewardList->add($oSource->Reward);
        }
    }

    /**
     * Fixed size:
     *
     * uint32 iDescOffset
     * uint32 iRewardOffset
     * uint16 iRuleType
     * uint16 iReserved
     * uint8[12] (varying) aParams
     */
    public function toBinary(): string {
        $sPayload = pack(
            self::PACK_LONG . // Description Offset
            self::PACK_LONG . // Reward Offset
            self::PACK_WORD .
            self::PACK_WORD,
            $this->iDescOffset,
            $this->iRewardOffset,
            $this->iRuleType,
            0x000
        ) . $this->sEncParams;

        $iLength = strlen($sPayload);

        if ($iLength > self::ENC_SIZE) {
            throw new LogicException('Unexpected encoding size for Achievement: ' . $iLength);
        }
        else if ($iLength < self::ENC_SIZE) {

            $sPad = chr(0xF0|$this->iRuleType);

            $sPayload = str_pad($sPayload, self::ENC_SIZE, $sPad, STR_PAD_RIGHT);
        }
        return $sPayload;
    }

    private function validate(stdClass $oSource)
    {
        if (
            empty($oSource->Description) ||
            !is_string($oSource->Description)
        ) {
            throw new RuntimeException('Missing/Invalid Achievement.Description');
        }

        if (
            empty($oSource->Rule) ||
            !is_string($oSource->Rule) ||
            !isset(self::RULE_TYPES[$oSource->Rule])
        ) {
            throw new RuntimeException('Missing/Invalid Achievement.Rule');
        }

        if (
            empty($oSource->Params) ||
            !is_object($oSource->Params)
        ) {
            throw new RuntimeException('Missing/Invalid Achievement.Params');
        }

        if (
            isset($oSource->Reward) &&
            !is_object($oSource->Reward)
        ) {
            throw new RuntimeException('Invalid Achievement.Reward');
        }
    }

    private function parseParams(
        stdClass $oParams,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes,
        array $aAlienTypes
    ): string {
        $cParser = [$this, self::RULE_PARAM_FN[$this->iRuleType]];
        return $cParser(
            $oParams,
            $aPlayerAmmoTypes,
            $aPlayerSpecialAmmoTypes,
            $aAlienTypes
        );
    }

    /**
     * Count: uint32
     * Alien ID: uint16
     */
    private function parseKillCountParams(
        stdClass $oParams,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes,
        array $aAlienTypes
    ): string {
        if (
            empty($oParams->Alien) ||
            !is_string($oParams->Alien) ||
            !isset($aAlienTypes[$oParams->Alien])
        ) {
            throw new RuntimeException("Invald/Empty Achievement.Params.Alien for KillCount Rule");
        }
        $iCount = $this->getCount($oParams, 'KillCount');
        return pack(
            self::PACK_LONG .
            self::PACK_WORD,
            $oParams->Count,
            $aAlienTypes[$oParams->Alien]
        );
    }

    /**
     * Count: uint32
     * Mask: uint32
     */
    private function parseGroupKillCountParams(
        stdClass $oParams,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes,
        array $aAlienTypes
    ): string {
        if (
            empty($oParams->Aliens) ||
            !is_array($oParams->Aliens)
        ) {
            throw new RuntimeException("Invald/Empty Achievement.Params.Aliens for GroupKillCount Rule");
        }
        $iMask  = 0;
        foreach ($oParams->Aliens as $sAlien) {
            if (
                !is_string($sAlien) ||
                !isset($aAlienTypes[$sAlien])
            ) {
                throw new RuntimeException("Invald entry in Achievement.Params.Aliens for KillCount Rule");
            }
            $iAlienID = $aAlienTypes[$sAlien];
            $iMask |= (1 << $iAlienID);
        }

        $iCount = $this->getCount($oParams, 'GroupKillCount');

        return pack(
            self::PACK_LONG .
            self::PACK_LONG,
            $iCount,
            $iMask
        );
    }

    private function parseZoneFoundParams(
        stdClass $oParams,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes,
        array $aAlienTypes
    ): string {
        if (
            empty($oParams->Level) ||
            !is_string($oParams->Level) ||
            !preg_match('/^[A-P]$/', $oParams->Level)
        ) {
            throw new RuntimeException("Invald/Empty Achievement.Params.Level for ZoneFound Rule");
        }
        $iLevel = ord($oParams->Level) - 65; // char to int code

        // Don't define an upper limit yet as we might want to expand zone counts.
        if (
            empty($oParams->Zone) ||
            !is_int($oParams->Zone) ||
            $oParams->Zone < 0
        ) {
            throw new RuntimeException("Invald/Empty Achievement.Params.Zone for ZoneFound Rule");
        }
        return pack(
            self::PACK_WORD .
            self::PACK_WORD,
            $iLevel,
            $oParams->Zone
        );
    }

    /**
     *   Levels: "<LevelList>",
     *   Count: <#count>,
     *   Overall: <bool>
     */
    private function parseMaskedLevelCountParams(
        stdClass $oParams,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes,
        array $aAlienTypes
    ): string {
        if (
            empty($oParams->Levels) ||
            !is_string($oParams->Levels)
        ) {
            throw new RuntimeException("Invald/Empty Achievement.Params.Levels for Rule");
        }
        $oLevelList = new LevelList($oParams->Levels);
        $iCount   = $this->getCount($oParams, 'Rule');

        if (
            !isset($oParams->Overall) ||
            !is_bool($oParams->Overall)
        ) {
            throw new RuntimeException("Invald/Empty Achievement.Params.Overall for Rule");
        }
        $iOverall = $oParams->Overall ? self::MASK_WORD : 0;
        return pack(
            self::PACK_LONG .
            self::PACK_WORD .
            self::PACK_WORD,
            $iCount,
            $iOverall,
            $oLevelList->getMask()
        );
    }

    private function parseCollectedParams(
        stdClass $oParams,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes,
        array $aAlienTypes
    ): string {
        if (
            empty($oParams->Type) ||
            !is_string($oParams->Type)
        ) {
            throw new RuntimeException("Invald/Empty Achievement.Params.Type for Collected Rule");
        }
        $iTypeID = -1;

        if ("Health" === $oParams->Type) {
            $iTypeID = 0;
        } else if ("Fuel" === $oParams->Type) {
            $iTypeID = 1;
        } else if (isset($aPlayerAmmoTypes[$oParams->Type])) {
            $iTypeID = 2 + $aPlayerAmmoTypes[$oParams->Type];
        } else if (isset($aPlayerSpecialAmmoTypes[$oParams->Type])) {
            $iTypeID = 2 + $aPlayerSpecialAmmoTypes[$oParams->Type];
        } else {
            throw new RuntimeException("Invald Achievement.Params.Type for Collected Rule");
        }
        $iCount = $this->getCount($oParams, 'Collected');
        return pack(
            self::PACK_LONG .
            self::PACK_WORD,
            $iCount,
            $iTypeID
        );
    }

    private function getCount(stdClass $oParams, string $sRule): int {
        if (
            empty($oParams->Count) ||
            !is_int($oParams->Count) ||
            $oParams->Count < 1 ||
            $oParams->Count > self::MAX_COUNT
        ) {
            throw new RuntimeException("Invald/Empty Achievement.Params.Count for " . $sRule . " Rule");
        }
        return (int)$oParams->Count;
    }
}
