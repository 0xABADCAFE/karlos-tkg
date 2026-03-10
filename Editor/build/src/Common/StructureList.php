<?php

/**
 * TKG Mod Builder
 */
declare(strict_types=1);

namespace TKG\Mod\Common;
use \stdClass;

abstract class StructureList extends BlobCollector {

    public function add(stdClass $oDefinition): int {
        return $this->addBlob($this->parseStructure($oDefinition)->toBinary());
    }

    /**
     * Implementation must provide this,
     */
    protected abstract function parseStructure(stdClass $oDefinttion): IBinaryEncodable;
}
