<?php

/**
 * TKG Redux Mod Builder
 */
declare(strict_types=1);

namespace TKG\Mod\Common;

use TKG\Mod\File;

final class StringList extends BlobCollector {

    public function __construct(int $iNextOffset = File\Chunk::FIXED_SIZE)
    {
        parent::__construct($iNextOffset, self::SIZE_BYTE);
    }

    public function add(string $sString): int {
        // Strings need to be null terminated
        return $this->addBlob($sString . "\0");
    }
}

