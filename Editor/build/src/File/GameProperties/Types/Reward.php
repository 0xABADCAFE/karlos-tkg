<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Types;

use TKG\Mod\Common;
use TKG\Mod\File;
use \RuntimeException;
use \stdClass;

use function \is_string, \pack;

/**
 * Parses and encodes a Reward structure.
 */
class Reward implements Common\IBinaryEncodable {
    private string $sCarryData     = '';
    private string $sImmediateData = '';
    private int    $iDescOffset    = 0;

    /**
     * RAII constructor.
     * Fail for invalid input.
     */
    public function __construct(
        stdClass $oSource,
        Common\StringList $oStringList,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes
    ) {
        if (empty($oSource->Description) || !is_string($oSource->Description)) {
            throw new RuntimeException('Invalid Reward Structure: Missing Description');
        }

        // At least one bonus must be defined
        if (empty($oSource->ImmediateAdd) && empty($oSource->CarryLimitAdd)) {
            throw new RuntimeException('Invalid Reward Structure: Missing bonuses');
        }

        if (!empty($oSource->CarryLimitAdd)) {
            $oCarryLimitAdd = new SupplyQuantity(
                $oSource->CarryLimitAdd,
                0,
                0,
                $aPlayerAmmoTypes,
                $aPlayerSpecialAmmoTypes
            );
            if (false === $oCarryLimitAdd->isEmpty()) {
                $this->sCarryData = $oCarryLimitAdd->toBinary();
            }
        }

        if (!empty($oSource->ImmediateAdd)) {
            $oImmediateAdd = new SupplyQuantity(
                $oSource->ImmediateAdd,
                0,
                0,
                $aPlayerAmmoTypes,
                $aPlayerSpecialAmmoTypes
            );
            if (false === $oImmediateAdd->isEmpty()) {
                $this->sImmediateData = $oImmediateAdd->toBinary();
            }
        }
        // Last sanity check...
        if (empty($this->sCarryData) && empty($this->sImmediateData)) {
            throw new RuntimeException('Invalid Reward Structure: Empty bonuses');
        }

        // Record the description in to the StringList and capture the location.
        $this->iDescOffset = $oStringList->add($oSource->Description);
    }

    /**
     * uint32 uDescriptionOffset
     * uint16 uCarryOffset, 0 if no carry bonus, 8 if there is. Measured from start of blob.
     * uint16 uImmediateOffset, 0 if no immediate bonus, 8 + sizeof(aCarryData) if there is.
     * int16[] aCarryData, -1 terminates data if present
     * int16[] aImmediateData
     * int16 -1 terminates payload
     */
    public function toBinary(): string {
        $iCarryOffset     = empty($this->sCarryData) ? 0 : 8;
        $iImmediateOffset = empty($this->sImmediateData) ? 0 : 8 + strlen($this->sCarryData);
        return pack(
            self::PACK_LONG . self::PACK_WORD . self::PACK_WORD,
            $this->iDescOffset,
            $iCarryOffset,
            $iImmediateOffset
        ) . $this->sCarryData . $this->sImmediateData;
    }

}
