<?php

declare(strict_types=1);

namespace TKG\Mod\File;

use TKG\Mod\Common;
use \RuntimeException;
use \LogicException;
use \stdClass;

use function \dirname, \file_get_contents, \file_put_contents, \json_decode;
use function \is_file, \is_readable, \is_writeable, \pack, \preg_match, \preg_replace;


abstract class Builder {
    protected readonly string $sBase;
    protected readonly string $sSourceBase;
    protected readonly string $sTargetBase;

    protected string $sTargetPath = '';

    public function __construct(
        string $sSourceBase,
        string $sSource,
        string $sTargetBase
    ) {
        $sSourceBase = rtrim($sSourceBase, '/') . '/';
        $sTargetBase = rtrim($sTargetBase, '/') . '/';

        $this->assertSourceReadable($sSourceBase . $sSource);
        $this->assertTargetDirectory($sTargetBase);
        $this->sSourceBase = $sSourceBase;
        $this->sSourcePath = $sSourceBase . $sSource;
        $this->sTargetBase = $sTargetBase;
    }

    public function build() {
        $oData = $this->loadSource($this->sSourcePath);

        if (empty($oData->Header)) {
            throw new RuntimeException('Missing Header section');
        }

        if (empty($oData->Target)) {
            throw new RuntimeException('Missing requird Target property');
        }

        $sTargetPath = $this->sTargetBase . $oData->Target;
        $this->assertTargetWritable($sTargetPath);
        $this->sTargetPath = $sTargetPath;


        $oStringList = new Common\StringList(Chunk::FIXED_SIZE);
        $oFile = new Indexed(
            new Header\Section(
                eSubFormat: $this->getSubformat($oData->Header),
                oVersion:   $this->parseVersion($oData->Header, 'Version'),
                oRequires:  $this->parseVersion($oData->Header, 'Requires'),
                iDescriptionOffset: $oStringList->add($oData->Header->Description ?? '')
            ),
            $oStringList
        );

        $this->preprocess($oFile->oHeader, $oData, $oStringList);
        foreach ($this->getChunks($oData, $oStringList) as $oChunk) {
            $oFile->addChunk($oChunk);
        }
        file_put_contents($this->sTargetPath, $oFile->toBinary());
        printf("Saved to %s\n", $this->sTargetPath);
    }

    /**
     * Return the enumerated subformat of the data or throw an exception if it's not the
     * expected type.
     *
     * @throws RuntimeException
     */
    protected abstract function getSubformat(stdClass $oData): Header\SubFormat;

    /**
     * This method is called before getChunks() and allows the implementation to do any special tasks
     * such as building lookups etc.
     */
    protected abstract function preprocess(
        Header\Section $oHeader,
        stdClass $oData,
        Common\StringList $oStringList
    ): void;

    /**
     * The specific implementation must return the array of chunks to be
     * added here.
     *
     * @return array<Chunk>
     */
    protected abstract function getChunks(stdClass $oData, Common\StringList $oStringList): array;

    private function parseVersion(stdClass $oData, string $sField): Header\Version {
        if (empty($oData->{$sField})) {
            throw new RuntimeException('Missing version field ' . $sField);
        }
        if (!preg_match('/^(\d+)\.(\d+)$/', (string)$oData->{$sField}, $aMatches)) {
            throw new RuntimeException('Malformed version field ' . $sField);
        }
        return new Header\Version((int)$aMatches[1], (int)$aMatches[2]);
    }

    /**
     * Convert raw RSON to JSON friendly format
     */
    public static function parseRSON(string $sContents): string {
        // Strip line comments
        $sContents = preg_replace('/\/\/.*$/m', '', $sContents);

        // Strip trailing comma at end of structure definition
        $sContents = preg_replace('/,\s*\}/', '}', $sContents);

        // Strip trailing comma at end of array definition
        $sContents = preg_replace('/,\s*\]/', ']', $sContents);

        // String catenation (cpp style)
        $sContents = preg_replace('/"\s*"/', '', $sContents);

        // Add quotes to identifier names
        $sContents = preg_replace(
            '/^\s*([A-Za-z_0-9]+)\:/m',
            '"${1}":',
            $sContents
        );
        return $sContents;
    }

    private function loadSource(string $sSourcePath): stdClass {
        $sContents = self::parseRSON(file_get_contents($sSourcePath));

        if (empty($sContents)) {
            RuntimeException('Unable to load source ' . $SourcePath . ', appears to be empty');
        }

        $oData = json_decode($sContents);
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

    private function assertTargetDirectory(string $sTarget): void {
            if (!is_dir($sTarget)) {
                throw new RuntimeException('Target ' . $sTarget . ' is not a directory');
            }
            if (!is_writable($sTarget)) {
                throw new RuntimeException('Target ' . $sTarget . ' is not writable');
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
            $sTargetBase = dirname($sTarget);
            if (!is_writable($sTargetBase)) {
                throw new RuntimeException('Target directory ' . $sTargetBase . ' is not writable');
            }
        }
    }
}
