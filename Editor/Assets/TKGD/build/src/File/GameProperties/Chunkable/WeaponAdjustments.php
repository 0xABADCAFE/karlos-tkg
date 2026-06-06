<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Chunkable;

use TKG\Mod\File\GameProperties\Types;
use TKG\Mod\Common;
use \stdClass;
use \RuntimeException;

class WeaponAdjustments implements Common\IBinaryEncodable {

    public const string IDENT = 'WADJ';

    public function __construct(
        private readonly array    $aPlayerWeapons,
        private readonly stdClass $oWeaponAdjustments
    ) {

    }

    public function toBinary(): string {
        $sData = '';
        foreach ($this->oWeaponAdjustments as $sWeaponName => $oWeaponAdjustmentDef) {
            $iSlot = $this->aPlayerWeapons[$sWeaponName] ??
                throw new RuntimeException('Invalid Weapon Name ' . $sWeaponName);
            $oWeaponAdjustment = new Types\WeaponAdjustment(
                $oWeaponAdjustmentDef,
                $iSlot
            );
            $sData .= $oWeaponAdjustment->toBinary();
        }
        return $sData;
    }

}
