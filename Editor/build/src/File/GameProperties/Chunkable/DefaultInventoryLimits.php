<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Chunkable;

use TKG\Mod\Common;
use TKG\Mod\File;
use \stdClass;
use \RuntimeException;

class DefaultInventoryLimits implements Common\IBinaryEncodable {

    public const string IDENT = 'INVL';

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
