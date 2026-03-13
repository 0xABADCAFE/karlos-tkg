<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Chunkable;

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
//         $i = 0;
//         $sPack = self::PACK_WORD . self::PACK_WORD . self::PACK_LONG;
//         foreach ($this->oSpecialAmmoBonuses as $sSpecialAmmoType => $oRewardData) {
//             $iSpecialAmmoType = $this->aPlayerSpecialAmmoTypes[$sSpecialAmmoType] ??
//                 throw new RuntimeException(
//                     'Unknown Special Ammo Type: ' . $sSpecialAmmoType
//                 );
//             $iOffset = $this->oRewardList->add($oRewardData);
//             $sData .= pack($sPack, $i++, $iSpecialAmmoType, $iOffset);
//         }
//         // -1 terminate the list
//         $sData .= pack(self::PACK_WORD, 0xFFFF);
        return $sData;
    }

}
