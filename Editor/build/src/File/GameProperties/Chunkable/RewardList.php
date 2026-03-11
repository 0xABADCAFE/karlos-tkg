<?php

declare(strict_types=1);

namespace TKG\Mod\File\GameProperties\Chunkable;

use TKG\Mod\File\GameProperties\Types;
use TKG\Mod\Common;
use TKG\Mod\File;
use \RuntimeException;
use \stdClass;

/**
 * RewardList.
 */
class RewardList extends Common\StructureList {

    public const string IDENT = 'RWRD';

    public function __construct(
        private array $aPlayerAmmoTypes,
        private array $aPlayerSpecialAmmoTypes,
        private Common\StringList $oStringList
    ) {
        parent::__construct(File\Chunk::FIXED_SIZE, self::SIZE_LONG);
    }

    protected function parseStructure(stdClass $oRewardDef): Common\IBinaryEncodable {
        return new Types\Reward(
            $oRewardDef,
            $this->oStringList,
            $this->aPlayerAmmoTypes,
            $this->aPlayerSpecialAmmoTypes
        );
    }
}
