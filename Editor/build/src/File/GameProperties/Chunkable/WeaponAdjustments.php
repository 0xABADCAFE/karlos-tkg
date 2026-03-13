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
        private readonly stdClass $oWeaponAdjustments,
        private readonly array    $aPlayerWeapons
    ) {

    }

    public function toBinary(): string {
        $sData = '';
        foreach ($this->oWeaponAdjustments as $sWeaponName => $oWeaponAdjustmentDef)
            if (!isset($this->aPlayerWeapons[$sWeaponName])) {

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
