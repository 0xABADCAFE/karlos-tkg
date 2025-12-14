<?php

declare(strict_types=1);

namespace TKGD\Data\Format;

/**
 * File subformat enumeration
 */
enum SubFormat: string {
    case Game  = 'GMOD';
    case Level = 'LMOD';
};

/**
 * Basic interface for entities that can be serialised to binary.
 */
interface BinaryEncodable {
    public const int SIZE_BYTE = 1;
    public const int SIZE_WORD = 2;
    public const int SIZE_LONG = 4;
    public function toBinary(): string;
}

/**
 * Major.Minor version class
 */
final class Version implements BinaryEncodable {
    public const int FIXED_SIZE = 4;
    private const int MAX = 65535;

    public function __construct(public int $iMajor, public int $iMinor) {
        assert(
            $iMajor >= 0 && $iMajor <= self::MAX &&
            $iMinor >= 0 && $iMinor <= self::MAX,
            new \RangeException()
        );
    }

    public function toBinary(): string {
        //echo "Encoding ", self::class, "\n";
        return pack('nn', $this->iMajor, $this->iMinor);
    }
}

/**
 * File Header.
 *
 * 0x0 Main identifier
 * 0x4 Subformat identifier
 * 0x8 Required Engine Version
 * 0xC File Version
 */
final class Header implements BinaryEncodable {
    public const string IDENT = 'TKGD';
    public const int FIXED_SIZE = 16;

    public function __construct(
        public readonly SubFormat $eSubFormat,
        public readonly Version $oVersion,
        public readonly Version $oRequires
    ) { }

    public function toBinary(): string {
        //echo "Encoding ", self::class, "\n";
        return self::IDENT .
            $this->eSubFormat->value .
            $this->oRequires->toBinary() .
            $this->oVersion->toBinary();
    }
}

interface ChunkIdent {
    public const string CHUNK_INDEX = 'INDX';
    public const string STRING_HEAP = 'STRH';
};

/**
 * Wrapper for a chunk payload. Encodes the payload immediately and pads the result to the required
 * alignment. Adds the ident and total size header at the beginning.
 */
final class Chunk implements BinaryEncodable {

    public const int FIXED_SIZE = 8;
    private const int ALIGN_MASK = (self::SIZE_LONG - 1);
    private const string PAD_CHAR = "\0";

    private int $iSize;
    private string $sContent;

    public function __construct(
        public readonly string $sIdent,
        BinaryEncodable $oPayload
    ) {
        assert(self::SIZE_LONG === strlen($sIdent), new \LogicException());
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
        $this->sContent = $this->sIdent . pack('N', $this->iSize) . $sPayload;
    }

    public function toBinary(): string {
        //echo "Encoding ", self::class, "[", $this->sIdent, "]\n";
        return $this->sContent;
    }

    /**
     * Returns the final size of the chunk for indexing.
     */
    public function size(): int {
        return $this->iSize;
    }
}

/**
 * StringBlob
 */
final class StringBlob implements BinaryEncodable {

    private const string NULL_TERM = "\0";

    private array  $aStrings = [];
    private string $sHeap    = '';
    private int    $iOffset  = 0;

    private int    $iAdded   = 0;
    private int    $iUnique  = 0;
    private int    $iEmpty   = 0;

    public function __construct(int $iOffset = Chunk::FIXED_SIZE) {
        $this->iOffset = $iOffset;
    }

    public function isEmpty(): bool {
        return empty($this->aStrings);
    }

    /**
     * Adds a string, returning the offset to the start within the heap.
     * Empty strings are not stored and will always return a zero offset.
     */
    public function add(string $sString): int {
        ++$this->iAdded;
        if (empty($sString)) {
            ++$this->iEmpty;
            return 0;
        } else if (isset($this->aStrings[$sString])) {
            ++$this->iUnique;
            return $this->aStrings[$sString];
        }
        $this->sHeap .= $sString . self::NULL_TERM;
        $iOffset = $this->iOffset;
        $this->aStrings[$sString] = $iOffset;
        $this->iOffset += strlen($sString) + self::SIZE_BYTE;
        return $iOffset;
    }

    /**
     * Export the actual string heap. This begins with a 4 byte size field (big endian), followed by the set of
     * strings.
     */
    public function toBinary(): string {
        return $this->sHeap;
    }
}

final class IndexedFile implements BinaryEncodable {

    private array $aChunks = [];

    public function __construct(
        private Header $oHeader,
        private StringBlob $oStringBlob
    ) {}

    public function toBinary(): string {
        //echo "Encoding ", self::class, "\n";
        $sBinary = $this->oHeader->toBinary() . $this->buildIndex();
        foreach ($this->aChunks as $oChunk) {
            $sBinary .= $oChunk->toBinary();
        }
        return $sBinary;
    }

    public function addChunk(Chunk $oChunk): void {
        $this->aChunks[] = $oChunk;
    }

    private function buildIndex(): string {
        //echo "Encoding ", self::class, " index\n";
        if (false === $this->oStringBlob->isEmpty()) {
            $this->addChunk(
                new Chunk(
                    ChunkIdent::STRING_HEAP,
                    $this->oStringBlob
                )
            );
        }
        $iIndexSize = Chunk::FIXED_SIZE + count($this->aChunks) * self::SIZE_LONG;
        $iOffset    = Header::FIXED_SIZE + $iIndexSize;
        $aPackLongs = [$iIndexSize];
        foreach ($this->aChunks as $oChunk) {
            $aPackLongs[] = $iOffset;
            $iOffset += $oChunk->size();
        }
        return ChunkIdent::CHUNK_INDEX . pack('N*', ...$aPackLongs);
    }
}
/*
$oStringBlob =  new StringBlob(Chunk::FIXED_SIZE);

$oFile = new IndexedFile(
    new Header(
        eSubFormat: SubFormat::Level,
        oVersion: new Version(1, 0),
        oRequires: new Version(1, 11)
    ),
    $oStringBlob
);

$oStringBlob->add('This is a string.');
$oStringBlob->add('Well, this should be a different string');

$oPayload = new class implements BinaryEncodable {
    public function toBinary(): string {
        return 'abadcafe1';
    }
};

$oFile->addChunk(
    new Chunk(
        'TST0',
        $oPayload
    )
);

$oFile->addChunk(
    new Chunk(
        'TST1',
        $oPayload
    )
);

echo bin2hex($oFile->toBinary());
*/
