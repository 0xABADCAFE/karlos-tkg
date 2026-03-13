<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Types;

use TKG\Mod\Common;
use \RuntimeException;
use \stdClass;

use function \is_array, \pack;

/**
 * WeaponAdjustment
 */
class WeaponAdjustment implements Common\IBinaryEncodable {

    private const int F_NO_RUN            = 0;
    private const int F_NO_CROUCH         = 1;
    private const int F_NO_FLY            = 2;
    private const int F_NO_FIRE_SUBMERGED = 3;

    private string $sPayload = '';

    public function __construct(
        stdClass $oSource,
        int $iSlot
    ) {
        if (empty($oSource)) {
            throw new RuntimeException('WeaponAdjustment cannot be empty');
        }
        if (
            !empty($oSource->SpawnOffset) &&
            (!is_array($oSource->SpawnOffset) || count($oSource->SpawnOffset) < 2)
        ) {
            throw new RuntimeException('Invalid WeaponAdjustment.SpawnOffset');
        }
        $iSpawnXOffset    = (int)($oSource->SpawnOffset[0] ?? 0);
        $iSpawnYOffset    = (int)($oSource->SpawnOffset[1] ?? 0);
        $iRecoil          = (int)($oSource->Recoil ?? 0);
        $iSpray           = (int)($oSource->Spray ?? 0);
        $iBurstLimit      = (int)($oSource->BurstLimit ?? 0);
        $iCooldown        = (int)($oSource->Cooldown ?? 0);
        $iFlags           = ((int)(($oSource->NoRun !== false) ?? 0)           << self::F_NO_RUN) |
                            ((int)(($oSource->NoCrouch !== false) ?? 0)        << self::F_NO_CROUCH) |
                            ((int)(($oSource->NoFly !== false) ?? 0)           << self::F_NO_FLY) |
                            ((int)(($oSource->NoFireSubmerged !== false) ?? 0) << self::F_NO_FIRE_SUBMERGED);

        if ($iRecoil < 0) {
            throw new RuntimeException('WeaponAdjustment.Recoil must not be negative');
        }
        if ($iSpray < 0) {
            throw new RuntimeException('WeaponAdjustment.Spray must not be negative');
        }
        if ($iBurstLimit < 0) {
            throw new RuntimeException('WeaponAdjustment.BurstLimit must not be negative');
        }
        if ($iCooldown < 0) {
            throw new RuntimeException('WeaponAdjustment.Cooldown must not be negative');
        }
        $this->sPayload = pack(
            self::PACK_WORD . self::PACK_MANY,
            $iSlot,
            $iSpawnXOffset,
            $iSpawnYOffset,
            $iRecoil,
            $iSpray,
            $iBurstLimit,
            $iCooldown,
            $iFlags
        );
    }

    public function toBinary(): string {
        return $this->sPayload;
    }
}
