<?php

declare(strict_types=1);

namespace TKG\Mod\File\LevelProperties\Chunkable;

use TKG\Mod\Common;
use \stdClass;
use \RuntimeException;

class ZoneBackdropDeletions implements Common\IBinaryEncodable {

    public const string IDENT = 'BCKD';

    public function __construct(private array $aBackdropErrata) {

    }

    public function toBinary(): string {
        $this->aBackdropErrata[] = -1;
        return pack(
            self::PACK_WORD . self::PACK_MANY,
            ...$this->aBackdropErrata
        );
    }
}
