<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Types;

use TKG\Mod\Common;
use \RuntimeException;
use \stdClass;

use function \is_iterable, \pack;

/**
 * SupplyQuantity
 */
class SupplyQuantity implements Common\IBinaryEncodable {

    private array $aData = [];

    private bool $bEmpty = true;

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

        if (isset($oSource->Ammo) && !($oSource->Ammo instanceof stdClass)) {
            throw new RuntimeException('SupplyQuantity:Ammo must be a collection');
        }

        $this->aData[] = self::MASK_WORD & ($oSource->Health ?? $iDefaultHealth);
        $this->aData[] = self::MASK_WORD & ($oSource->Fuel ?? $iDefaultFuel);

        $this->bEmpty = (0 === $this->aData[0]) && (0 === $this->aData[1]);

        if (!empty($oSource->Ammo)) {
            foreach ($oSource->Ammo as $sName => $iQuantity) {
                $iAmmoType = $aPlayerAmmoTypes[$sName] ??
                    $aPlayerSpecialAmmoTypes[$sName] ??
                    throw new RuntimeException('Unknown Ammo type: ' . $sName);
                $this->aData[] = $iAmmoType;
                $this->aData[] = $iQuantity;
                $this->bEmpty = $this->bEmpty && (0 === $iQuantity);
            }
        }

        // List termination
        $this->aData[] = self::MASK_WORD;
    }

    /**
     * A structurally valid SupplyQuantity can be empty if each defined component is zero.
     */
    public function isEmpty(): bool {
        return $this->bEmpty;
    }

    public function toBinary(): string {
        return pack(self::PACK_WORD . self::PACK_MANY, ...$this->aData);
    }
}
