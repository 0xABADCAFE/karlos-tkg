<?php

declare(strict_types=1);

namespace TKG\Mod\File;

use TKG\Mod\Common;
use \LogicException;

use function \pack, \strlen;

final class Indexed implements Common\IBinaryEncodable {

    private array $aChunks = [];

    public function __construct(
        public readonly Header\Section $oHeader,
        private Common\StringList $oStringBlob
    ) {}

    public function toBinary(): string {
        $sBinary = $this->oHeader->toBinary() . $this->buildIndex();
        foreach ($this->aChunks as $oChunk) {
            $sBinary .= $oChunk->toBinary();
        }

        printf("Generated binary, %d bytes.\n", strlen($sBinary));

        return $sBinary;
    }

    public function addChunk(Chunk $oChunk): void {
        $this->aChunks[] = $oChunk;
    }

    private function buildIndex(): string {
        if (false === $this->oStringBlob->isEmpty()) {
            $this->addChunk(
                new Chunk(
                    IChunkIdent::STRING_HEAP,
                    $this->oStringBlob
                )
            );
        }
        $iIndexSize = Chunk::FIXED_SIZE + count($this->aChunks) * self::SIZE_LONG * 2;
        $iOffset    = Header\Section::FIXED_SIZE + $iIndexSize;

        // Start with the Index itself for validation purposes
        $sBinary = IChunkIdent::CHUNK_INDEX . pack(self::PACK_LONG, $iIndexSize);

        foreach ($this->aChunks as $oChunk) {
            $sBinary .= $oChunk->sIdent . pack(self::PACK_LONG, $iOffset);
            $iOffset += $oChunk->size();
        }

        return $sBinary;
    }
}
