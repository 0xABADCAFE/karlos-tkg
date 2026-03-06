<?php

/**
 * Alien Breed 3D Redux
 */

declare(strict_types=1);

namespace TKGD\Data\Format;
use \RuntimeException;
use function \spl_autoload_register;

if (PHP_VERSION_ID < 70400) {
    throw new RuntimeException('Requires at least PHP 7.4');
}

const CLASS_MAP = [
  'TKGD\\Data\\Format\\SubFormat' => '/Library.php',
  'TKGD\\Data\\Format\\BinaryEncodable' => '/Library.php',
  'TKGD\\Data\\Format\\Version' => '/Library.php',
  'TKGD\\Data\\Format\\Header' => '/Library.php',
  'TKGD\\Data\\Format\\LevelSet' => '/Library.php',
  'TKGD\\Data\\Format\\SupplyQuantity' => '/Library.php',
  'TKGD\\Data\\Format\\ChunkIdent' => '/Library.php',
  'TKGD\\Data\\Format\\Chunk' => '/Library.php',
  'TKGD\\Data\\Format\\BlobCollector' => '/Library.php',
  'TKGD\\Data\\Format\\StringBlob' => '/Library.php',
  'TKGD\\Data\\Format\\IndexedFile' => '/Library.php',
  'TKGD\\Data\\Format\\Builder' => '/Library.php',
];

const PROJ_SRC_BASE = __DIR__;

spl_autoload_register(function(string $str_class): void {
    if (isset(CLASS_MAP[$str_class])) {
        require_once PROJ_SRC_BASE . CLASS_MAP[$str_class];
    }
});
