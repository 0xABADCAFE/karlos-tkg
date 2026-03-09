<?php

declare(strict_types=1);

namespace TKG\Mod\File;

use TKG\Mod\Common;
use \LogicException;

use function \pack, \strlen, \str_pad;

/**
 * Wrapper for a chunk payload. Encodes the payload immediately and pads the result to the required
 * alignment. Adds the ident and total size header at the beginning.
 */
final class Chunk implements Common\IBinaryEncodable {

    public const int FIXED_SIZE = 8;
    private const int ALIGN_MASK = (self::SIZE_LONG - 1);
    private const string PAD_CHAR = "\0";

    private int $iSize;
    private string $sContent;

    public function __construct(
        public readonly string $sIdent,
        Common\IBinaryEncodable $oPayload
    ) {
        assert(
            self::SIZE_LONG === strlen($sIdent),
            new LogicException('Invalid Ident Size')
        );
        $sPayload = $oPayload->toBinary();
        $iSize = strlen($sPayload);
        if ($iSize & self::ALIGN_MASK) {
            $iSize = ($iSize + self::ALIGN_MASK) & ~self::ALIGN_MASK;
            $sPayload = str_pad(
                $sPayload,
                $iSize,
                self::PAD_CHAR
            );
        }
        $this->iSize = $iSize + self::FIXED_SIZE;
        $this->sContent = $this->sIdent . pack(self::PACK_LONG, $this->iSize) . $sPayload;
    }

    public function toBinary(): string {
        return $this->sContent;
    }

    /**
     * Returns the final size of the chunk for indexing.
     */
    public function size(): int {
        return $this->iSize;
    }
}
