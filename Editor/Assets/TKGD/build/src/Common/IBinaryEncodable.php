<?php

/**
 * TKG Mod Builder
 */
declare(strict_types=1);

namespace TKG\Mod\Common;

interface IBinaryEncodable extends IBinaryProperties {
    public function toBinary(): string;
}
