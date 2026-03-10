<?php

/**
 * Alien Breed 3D Redux
 */

declare(strict_types=1);

namespace TKG\Mod;
use \RuntimeException;
use function \spl_autoload_register;

if (PHP_VERSION_ID < 70400) {
    throw new RuntimeException('Requires at least PHP 7.4');
}

const CLASS_MAP = [
  'TKG\\Mod\\Common\\StringList' => '/Common/StringList.php',
  'TKG\\Mod\\Common\\IBinaryEncodable' => '/Common/IBinaryEncodable.php',
  'TKG\\Mod\\Common\\IBinaryProperties' => '/Common/IBinaryProperties.php',
  'TKG\\Mod\\Common\\BlobCollector' => '/Common/BlobCollector.php',
  'TKG\\Mod\\File\\IChunkIdent' => '/File/IChunkIdent.php',
  'TKG\\Mod\\File\\Indexed' => '/File/Indexed.php',
  'TKG\\Mod\\File\\Chunk' => '/File/Chunk.php',
  'TKG\\Mod\\File\\Builder' => '/File/Builder.php',
  'TKG\\Mod\\File\\LevelProperties\\Builder' => '/File/LevelProperties/Builder.php',
  'TKG\\Mod\\File\\GameProperties\\RewardList' => '/File/GameProperties/RewardList.php',
  'TKG\\Mod\\File\\GameProperties\\SupplyQuantity' => '/File/GameProperties/SupplyQuantity.php',
  'TKG\\Mod\\File\\GameProperties\\LevelList' => '/File/GameProperties/LevelList.php',
  'TKG\\Mod\\File\\GameProperties\\Reward' => '/File/GameProperties/Reward.php',
  'TKG\\Mod\\File\\GameProperties\\Builder' => '/File/GameProperties/Builder.php',
  'TKG\\Mod\\File\\Header\\Section' => '/File/Header/Section.php',
  'TKG\\Mod\\File\\Header\\SubFormat' => '/File/Header/SubFormat.php',
  'TKG\\Mod\\File\\Header\\Version' => '/File/Header/Version.php',
];

const PROJ_SRC_BASE = __DIR__;

spl_autoload_register(function(string $str_class): void {
    if (isset(CLASS_MAP[$str_class])) {
        require_once PROJ_SRC_BASE . CLASS_MAP[$str_class];
    }
});
