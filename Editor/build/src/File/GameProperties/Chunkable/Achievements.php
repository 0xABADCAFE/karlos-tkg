<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Chunkable;

use TKG\Mod\Common;
use \stdClass;
use \RuntimeException;

class Achievements implements Common\IBinaryEncodable {

    public const string IDENT = 'ACHV';

    public function __construct(
        private readonly array $aAchievements,
        private RewardList $oRewardList,
        private Common\StringList $oStringList
    ) {}

    public function toBinary(): string {
        return pack('N', 0xABADCAFE);
    }

}
