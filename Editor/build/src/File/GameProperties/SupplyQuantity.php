<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties;

use TKG\Mod\Common;
use \RuntimeException;
use \stdClass;

use function \is_iterable, \pack;

/**
 * SupplyQuantity
 */
class SupplyQuantity implements Common\IBinaryEncodable {

    protected array $aData = [];

    public function __construct(
        stdClass $oSource,
        int $iDefaultHealth,
        int $iDefaultFuel,
        array $aPlayerAmmoTypes,
        array $aPlayerSpecialAmmoTypes
    ) {
        if (
            !isset($oSource->Health) && !isset($oSource->Fuel) && empty($oSource->Ammo)
        ) {
            throw new RuntimeException('SupplyQuantity definition cannot be empty');
        }

        if (isset($oSource->Ammo) && !is_iterable($oSource->Ammo)) {
            throw new RuntimeException('SupplyQuantity.Ammo must be a collection');
        }

        $this->aData[] = self::MASK_WORD & ($oSource->Health ?? $iDefaultHealth);
        $this->aData[] = self::MASK_WORD & ($oSource->Fuel ?? $iDefaultFuel);
        if (!empty($oSource->Ammo)) {
            foreach ($oSource->Ammo as $sName => $iQuantity) {
                $iAmmoType = $aPlayerAmmoTypes[$sName] ??
                    $aPlayerSpecialAmmoTypes[$sName] ??
                    throw new RuntimeException('Unknown Ammo type: ' . $sName);
                $this->aData[] = $iAmmoType;
                $this->aData[] = $iQuantity;
            }
        }
        $this->aData[] = self::MASK_WORD;
    }

    public function toBinary(): string {
        return pack(self::PACK_WORD . self::PACK_MANY, ...$this->aData);
    }
}
