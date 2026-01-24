<?php

declare(strict_types=1);

namespace TKGD\Data\Format;

use stdClass;
use RangeException;
use RuntimeException;

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

    public function __construct(
        public readonly int $iMajor,
        public readonly int $iMinor
    ) {
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
    public const int FIXED_SIZE = 20;

    public function __construct(
        public readonly SubFormat $eSubFormat,
        public readonly Version $oVersion,
        public readonly Version $oRequires,
        public readonly int $iDescriptionOffset = 0
    ) { }

    public function toBinary(): string {
        //echo "Encoding ", self::class, "\n";
        return self::IDENT .
            $this->eSubFormat->value .
            $this->oRequires->toBinary() .
            $this->oVersion->toBinary() .
            pack('N', $this->iDescriptionOffset)
        ;
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
        public readonly Header $oHeader,
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
        $iIndexSize = Chunk::FIXED_SIZE + count($this->aChunks) * self::SIZE_LONG * 2;
        $iOffset    = Header::FIXED_SIZE + $iIndexSize;

        // Start with the Index itself for validation purposes
        $sBinary = ChunkIdent::CHUNK_INDEX . pack('N', $iIndexSize);


        foreach ($this->aChunks as $oChunk) {
            $sBinary .= $oChunk->sIdent . pack('N', $iOffset);
            $iOffset += $oChunk->size();
        }
        return $sBinary;
    }
}

abstract class Builder {
    protected readonly string $sBase;
    protected readonly string $sSourceBase;
    protected readonly string $sTargetPath;

    public function __construct(
        string $sSourceBase,
        string $sSource,
        string $sTarget
    ) {
        $this->assertSourceReadable($sSourceBase . $sSource);
        $this->assertTargetWritable($sTarget);
        $this->sSourceBase = $sSourceBase;
        $this->sSourcePath = $sSourceBase . $sSource;
        $this->sTargetPath = $sTarget;
    }

    public function build() {
        $oData = $this->loadSource($this->sSourcePath);

        if (empty($oData->Header)) {
            throw new RuntimeException('Missing Header section');
        }

        $oStringBlob = new StringBlob(Chunk::FIXED_SIZE);
        $oFile = new IndexedFile(
            new Header(
                eSubFormat: $this->getSubformat($oData->Header),
                oVersion:   $this->parseVersion($oData->Header, 'Version'),
                oRequires:  $this->parseVersion($oData->Header, 'Requires'),
                iDescriptionOffset: $oStringBlob->add($oData->Header->Description ?? '')
            ),
            $oStringBlob
        );

        $this->preprocess($oFile->oHeader, $oData, $oStringBlob);
        foreach ($this->getChunks($oData, $oStringBlob) as $oChunk) {
            $oFile->addChunk($oChunk);
        }

        file_put_contents($this->sTargetPath, $oFile->toBinary());
    }

    /**
     * Return the enumerated subformat of the data or throw an exception if it's not the
     * expected type.
     *
     * @throws RuntimeException
     */
    protected abstract function getSubformat(stdClass $oData): SubFormat;

    /**
     * This method is called before getChunks() and allows the implementation to do any special tasks
     * such as building lookups etc.
     */
    protected abstract function preprocess(Header $oHeader, stdClass $oData, StringBlob $oStringBlob): void;

    /**
     * The specific implementation must return the array of chunks to be
     * added here.
     *
     * @return array<Chunk>
     */
    protected abstract function getChunks(stdClass $oData, StringBlob $oStringBlob): array;

    private function parseVersion(stdClass $oData, string $sField): Version {
        if (empty($oData->{$sField})) {
            throw new RuntimeException('Missing version field ' . $sField);
        }
        if (!preg_match('/^(\d+)\.(\d+)$/', (string)$oData->{$sField}, $aMatches)) {
            throw new RuntimeException('Malformed version field ' . $sField);
        }
        return new Version((int)$aMatches[1], (int)$aMatches[2]);
    }

    private function loadSource(string $sSourcePath): stdClass {
        $str_contents = file_get_contents($sSourcePath);
        $str_contents = preg_replace('/\/\/.*$/m', '', $str_contents);
        $str_contents = preg_replace('/,\s*\}/', '}', $str_contents);
        $str_contents = preg_replace('/,\s*\]/', ']', $str_contents);

        $str_contents = preg_replace(
            '/^\s*([A-Za-z_0-9]+)\:/m',
            '"${1}":',
            $str_contents
        );

        if (empty($str_contents)) {
            RuntimeException('Unable to load source ' . $SourcePath . ', appears to be empty');
        }

        $oData = json_decode($str_contents);
        if (empty($oData)) {
            throw new RuntimeException('Unable to load source ' . $this->sSourcePath);
        }

        if (!empty($oData->Import)) {
            foreach ($oData->Import as $sField => $sIncludePath) {
                echo "Importing ", $sIncludePath, "\n";
                $oData->Import->{$sField} = $this->loadSource($this->sSourceBase . $sIncludePath);
            }
        }

        return $oData;
    }

    private function assertSourceReadable(string $sSource): void {
        if (!is_readable($sSource)) {
            throw new RuntimeException('Source ' . $sSource . ' is not readable');
        }
        if (!is_file($sSource)) {
            throw new RuntimeException('Source ' . $sSource . ' is not a file');
        }
    }

    private function assertTargetWritable(string $sTarget): void {
        if (file_exists($sTarget)) {
            if (!is_file($sTarget)) {
                throw new RuntimeException('Target ' . $sTarget . ' is not a file');
            }
            if (!is_writable($sTarget)) {
                throw new RuntimeException('Target ' . $sTarget . ' is not writable');
            }
        } else {
            $sTargetPath = dirname($sTarget);
            if (!is_writable($sTargetPath)) {
                throw new RuntimeException('Target directory ' . $sTargetPath . ' is not writable');
            }
        }
    }
}
