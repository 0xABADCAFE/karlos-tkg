<?php

/**
 * TKG Mod Builder
 */
declare(strict_types=1);

namespace TKG\Mod\Common;

use function \array_values, \bin2hex, \sha1, \strlen, \str_repeat;

/**
 * Base class for varying length data collections. Blobs are added and if necessary padded
 * to meet the required alignment before appending to the internal blob. The offset to the
 * data within that blob is returned.
 * Adding empty data will return an offset of zero.
 */
abstract class BlobCollector implements IBinaryEncodable {

    protected const string PAD_BYTE = "\0";

    private const int KEY_LENGTH = 20;

    private int $iNextOffset;
    private int $iAlignment;
    private int $iAdded   = 0;
    private int $iUnique  = 0;
    private int $iEmpty   = 0;

    protected array  $aOffsets  = [];
    protected string $sBlobData = '';

    public function __construct(int $iInitialOffset, int $iAlignment) {
        $this->iNextOffset = $iInitialOffset;
        $this->iAlignment  = $iAlignment;
    }

    public final function isEmpty(): bool {
        return empty($this->aOffsets);
    }

    protected final function addBlob(string $sBlobData): int {
        ++$this->iAdded;

        if (empty($sBlobData)) {
            ++$this->iEmpty;
            return 0;
        }

        // Get the length and choose a key.
        $iBlobLen = strlen($sBlobData);
        $sBlobKey = $iBlobLen > self::KEY_LENGTH ? sha1($sBlobData) : bin2hex($sBlobData);

        // Pad out if needed.
        if ($this->iAlignment > self::SIZE_BYTE) {
            $iPadLength = $this->iAlignment - ($iBlobLen & ($this->iAlignment - 1));
            $sBlobData .= str_repeat(
                self::PAD_BYTE,
                $iPadLength
            );
            $iBlobLen += $iPadLength;
        }

        // If the same data was added previously, return the existing offset.
        if (isset($this->aOffsets[$sBlobKey])) {
            return $this->aOffsets[$sBlobKey];
        }

        ++$this->iUnique;

        // Append the data, update the next offset and return this one
        $this->sBlobData .= $sBlobData;
        $iOffset = $this->iNextOffset;
        $this->aOffsets[$sBlobKey] = $iOffset;
        $this->iNextOffset += $iBlobLen;
        return $iOffset;
    }

    /**
     * @return array<string,int>
     */
    public final function getOffsets(): array {
        return array_values($this->aOffsets);
    }

    public final function getStats(): array {
        return [
            'Added'      => $this->iAdded,
            'Unique'     => $this->iUnique,
            'Empty'      => $this->iEmpty,
            'TotalSize'  => strlen($this->sBlobData),
            'NextOffset' => $this->iNextOffset,
        ];
    }

    public function toBinary(): string {
        return $this->sBlobData;
    }
}
