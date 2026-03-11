<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Chunkable;

use TKG\Mod\File\GameProperties\Types\Achievement;
use TKG\Mod\Common;
use \stdClass;
use \RuntimeException;

class Achievements implements Common\IBinaryEncodable {

    public const string IDENT = 'ACHV';

    public function __construct(
        private readonly array $aAchievements,
        private Common\StringList $oStringList,
        private RewardList $oRewardList,
        private array $aPlayerAmmoTypes,
        private array $aPlayerSpecialAmmoTypes,
        private array $aAlienTypes
    ) {}

    public function toBinary(): string {

        $aSorted  = [];
        foreach ($this->aAchievements as $oAchievementDef) {
            $aSorted[] = new Achievement(
                $oAchievementDef,
                $this->oStringList,
                $this->oRewardList,
                $this->aPlayerAmmoTypes,
                $this->aPlayerSpecialAmmoTypes,
                $this->aAlienTypes
            );
        }

        usort(
            $aSorted,
            function (Achievement $a, Achievement $b): int {
                return $a->iOrder <=> $b->iOrder;
            }
        );

        $sPayload = '';
        foreach ($aSorted as $oAchievement) {
            $sPayload .= $oAchievement->toBinary();
        }
        return $sPayload;
    }

}
