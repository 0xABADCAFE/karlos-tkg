<?php

/**
 * TKG Mod Builder
 */
declare(strict_types=1);

namespace TKG\Mod\Common;

interface IBinaryProperties {

    public const int SIZE_BYTE = 1;
    public const int SIZE_WORD = 2;
    public const int SIZE_LONG = 4;

    public const int MASK_BYTE = 0xFF;
    public const int MASK_WORD = 0xFFFF;
    public const int MASK_LONG = 0xFFFFFFFF;

    /**
     * pack() qualifiers
     */
    public const string PACK_BYTE = 'C';
    public const string PACK_WORD = 'n';
    public const string PACK_LONG = 'N';
    public const string PACK_MANY = '*';
}
