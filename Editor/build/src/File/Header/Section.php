<?php

declare(strict_types=1);

namespace TKG\Mod\File\Header;

use TKG\Mod\Common;

use function \pack;

/**
 * Header Section
 *
 * 0x0 Main identifier
 * 0x4 Subformat identifier
 * 0x8 Required Engine Version
 * 0xC File Version
 */
final class Section implements Common\IBinaryEncodable {
    public const string IDENT = 'TKGD';
    public const int FIXED_SIZE = 20;

    public function __construct(
        public readonly SubFormat $eSubFormat,
        public readonly Version $oVersion,
        public readonly Version $oRequires,
        public readonly int $iDescriptionOffset = 0
    ) { }

    public function toBinary(): string {
        return self::IDENT .
            $this->eSubFormat->value .
            $this->oRequires->toBinary() .
            $this->oVersion->toBinary() .
            pack(self::PACK_LONG, $this->iDescriptionOffset)
        ;
    }
}
