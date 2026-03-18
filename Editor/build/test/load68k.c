#include <stdio.h>
#include <stdint.h>
#include <stdlib.h>

#define ALIGN(n) __attribute__((packed)) __attribute__ ((aligned (n)))

/**
 * AmigaOS type puns
 */
typedef uint32_t ULONG;
typedef int32_t  LONG;
typedef uint16_t UWORD;
typedef int16_t  WORD;
typedef uint8_t  UBYTE;
typedef int8_t   BYTE;
typedef int16_t  BOOL;

#define FALSE 0
#define TRUE 1

enum {
    IDENT_TKGD = 0x544b4744,
    IDENT_GMOD = 0x474d4f44,
    IDENT_INDX = 0x494e4458,
    IDENT_STRH = 0x53545248,
};

/**
 * DataOffset type. This can be converted to an actual address after loading.
 */
typedef union {
    ULONG       do_Offset;
    char const* do_Text;
    UBYTE*      do_ByteAddress;
} ALIGN(sizeof(ULONG)) DataOffset;

/**
 * Basic Version tuple for checks
 */
typedef struct {
    UWORD       v_Major;
    UWORD       v_Minor;
} ALIGN(sizeof(ULONG)) Version;

typedef union {
    char        id_Text[4];
    ULONG       id_Value;
} ALIGN(sizeof(ULONG)) Ident;

typedef struct {
    Ident       h_Ident;
    Ident       h_SubFormat;
    Version     h_RequiresVersion;
    Version     h_Version;
    DataOffset  h_Description;
} ALIGN(sizeof(ULONG)) Header;

typedef struct {
    Ident       ch_Ident;
    ULONG       ch_Length;
} ALIGN(sizeof(ULONG)) ChunkHeader;

typedef struct {
    Ident       ie_Ident;
    DataOffset  ie_Offset;
} ALIGN(sizeof(ULONG)) IndexEntry;

typedef struct {
    UBYTE*            gmd_Data;
    ULONG             gmd_Length;
    ULONG             gmd_IndexSize;
    Header const*     gmd_Header;
    IndexEntry const* gmd_Index;
    char const*       gmd_Strings;
} ALIGN(sizeof(ULONG)) GMFData;


/**
 * Custom parsers
 */
typedef BOOL (*ChunkParser)(ChunkHeader const* pChunkHeader, GMFData* pGMFData);

typedef struct {
    ULONG pe_Ident;
    ChunkParser pe_Parser;
} ALIGN(sizeof(ULONG)) ParserEntry;

static BOOL gmf_CheckData(GMFData* pGMFData, Header const* pAgainst) {
    puts("\tgmf_CheckData()");

    if (!pGMFData || !pAgainst || !pGMFData->gmd_Data) {
        return FALSE;
    }
    Header const* pFrom = (Header const*)pGMFData->gmd_Data;

    return
        pAgainst->h_Ident.id_Value     == pFrom->h_Ident.id_Value &&
        pAgainst->h_SubFormat.id_Value == pFrom->h_SubFormat.id_Value &&
        pAgainst->h_Version.v_Major    == pFrom->h_RequiresVersion.v_Major &&
        pAgainst->h_Version.v_Minor    >= pFrom->h_RequiresVersion.v_Minor &&
        pFrom->h_Description.do_Offset >= 8;
}


/**
 * Attempts to load the named file, populating the data and length fields of
 * the GMFData and zeroing the rest. Once loaded, the data must be validated.
 */
static BOOL gmf_ReadFile(char const* filename, GMFData* pGMFData)
{
    puts("\tgmf_ReadFile()");

    if (!filename || !pGMFData) {
        return FALSE;
    }
    FILE*  pHandle = NULL;
    UBYTE* pBuffer = NULL;
    LONG   iLength = 0;
    BOOL   bResult = FALSE;
    do {
        pHandle = fopen(filename, "rb");
        if (!pHandle) {
            printf("Couldnt open %s for input\n", filename);
            break;
        }

        if (0 != fseek(pHandle, 0, SEEK_END)) {
            printf("seek to end of %s failed\n", filename);
            break;
        }

        iLength = ftell(pHandle);
        if (iLength <= 0) {
            printf("Invalid length %d for %s\n", iLength, filename);
            break;
        }

        if (0 != fseek(pHandle, 0, SEEK_SET)) {
            printf("seek to start of %s failed\n", filename);
            break;
        }

        pBuffer = (UBYTE*)calloc(iLength, 1);
        if (!pBuffer) {
            printf("Failed to allocate %d bytes for %s\n", iLength, filename);
            break;
        }

        if (fread(pBuffer, 1, iLength, pHandle) != iLength) {
            free(pBuffer);
            printf("Failed to read %d bytes from %s\n", iLength, filename);
            break;
        }

        pGMFData->gmd_Data      = pBuffer;
        pGMFData->gmd_Length    = iLength;
        pGMFData->gmd_Header    = NULL;
        pGMFData->gmd_Index     = NULL;
        pGMFData->gmd_IndexSize = 0;
        pGMFData->gmd_Strings   = NULL;

        bResult = TRUE;

    } while (FALSE);

    if (pHandle) {
        fclose(pHandle);
    }
    return bResult;
}

static BOOL gmf_ProcessDefaultChunks(GMFData* pGMFData)
{
    puts("\tgmf_ProcessDefaultChunks()");
    ChunkHeader const* pIndexHeader = (ChunkHeader const*)(pGMFData->gmd_Data + sizeof(Header));
    if (
        pIndexHeader->ch_Ident.id_Value != IDENT_INDX ||
        pIndexHeader->ch_Length < (sizeof(ChunkHeader) + sizeof(IndexEntry))
    ) {
        return FALSE;
    }
    int iNumEntries = (pIndexHeader->ch_Length - sizeof(ChunkHeader))/sizeof(IndexEntry);

    IndexEntry* pIndexEntry = (IndexEntry*)(((UBYTE*)pIndexHeader) + sizeof(ChunkHeader));
    pGMFData->gmd_IndexSize  = iNumEntries;
    pGMFData->gmd_Index      = pIndexEntry;

    pGMFData->gmd_Strings = NULL;

    /**
     * Convert the offsets in the index to their actual addresses
     */
    for (int i = 0; i < iNumEntries; ++i) {
        pIndexEntry[i].ie_Offset.do_ByteAddress = pGMFData->gmd_Data + pIndexEntry[i].ie_Offset.do_Offset;

        /* Quick sanity check - ensure the index ident is a match for the in memory location */
        Ident const* pChunkIdent = (Ident const*)pIndexEntry[i].ie_Offset.do_ByteAddress;
        if (pIndexEntry[i].ie_Ident.id_Value != pChunkIdent->id_Value) {
            printf(
                "Index entry %d ident mismatch. Index: %.*s => Chunk: %.*s\n",
                i,
                4, pIndexEntry[i].ie_Ident.id_Text,
                4, pChunkIdent->id_Text
            );
            return FALSE;
        }

        if (pIndexEntry[i].ie_Ident.id_Value == IDENT_STRH) {
            pGMFData->gmd_Strings = pIndexEntry[i].ie_Offset.do_Text;
        }
    }

    if (!pGMFData->gmd_Strings) {
        printf("String Heap not found in Index\n");
        return FALSE;
    }

    /* Patch the description locaton */
    Header* pHeader = (Header*)pGMFData->gmd_Data;
    pHeader->h_Description.do_Text = pGMFData->gmd_Strings + pHeader->h_Description.do_Offset;
    pGMFData->gmd_Header = pHeader;
    return TRUE;
}

static void* gmf_ChunkData(ChunkHeader const * pHeader)
{
    return ((UBYTE*)pHeader) + sizeof(ChunkHeader);
}

static const char* gmf_RelocateString(char const* string, GMFData const* pGMFData)
{
    return (string < pGMFData->gmd_Strings) ? pGMFData->gmd_Strings + (size_t)string : string;
}

ChunkHeader const* GMF_LocateChunk(GMFData const* pGMFData, ULONG iIdentValue)
{
    for (int i = 0; i < pGMFData->gmd_IndexSize; ++i) {
        if (pGMFData->gmd_Index[i].ie_Ident.id_Value == iIdentValue) {
            return (ChunkHeader const *)pGMFData->gmd_Index[i].ie_Offset.do_ByteAddress;
        }
    }
    return NULL;
}


GMFData* GMF_LoadFile(char const* filename, Header const* pCheckHeader, ParserEntry const* pCustomParsers)
{
    puts("GMF_LoadFile()");
    if (!filename || !pCheckHeader) {
        return NULL;
    }
    GMFData* pGMFData = (GMFData*)calloc(1, sizeof(GMFData));
    if (!pGMFData) {
        return NULL;
    }
    if (
        !gmf_ReadFile(filename, pGMFData) ||
        !gmf_CheckData(pGMFData, pCheckHeader) ||
        !gmf_ProcessDefaultChunks(pGMFData)
    ) {
        free(pGMFData);
        return NULL;
    }

    if (pCustomParsers) {
        ChunkHeader const* pChunkHeader = NULL;
        while (
            pCustomParsers->pe_Ident &&
            pCustomParsers->pe_Parser
        ) {
            if ( (pChunkHeader = GMF_LocateChunk(pGMFData, pCustomParsers->pe_Ident)) ) {
                pCustomParsers->pe_Parser(pChunkHeader, pGMFData);
            }
            ++pCustomParsers;
        }
    }

    return pGMFData;
}

void GMF_Free(GMFData* pGMFData)
{
    puts("GMF_Free()");
    if (pGMFData) {
        if (pGMFData->gmd_Data) {
            free(pGMFData->gmd_Data);
        }
        free(pGMFData);
    }
}

/**********************************************************************************************************************/


Header const reference = {
    .h_Ident.id_Value     = IDENT_TKGD,
    .h_SubFormat.id_Value = IDENT_GMOD,
    .h_Version            = {1, 255}
};

enum {
    IDENT_INVL = 0x494E564C,
    IDENT_SPAB = 0x53504142,
    IDENT_RWRD = 0x52575244,
};

typedef struct {
    char const* r_Description;
    UWORD r_CarryOffset;
    UWORD r_ImmediateOffset;
    UWORD r_RewardData[1];
} ALIGN(sizeof(ULONG)) Reward;

typedef struct {
    UWORD         spab_Index;
    UWORD         spab_AmmoID;
    Reward const* spab_Reward;
} ALIGN(sizeof(ULONG)) SpecialAmmoBonus;

BOOL gmod_ParseDummy(ChunkHeader const* pChunkHeader, GMFData* pGMFData)
{
    printf(
        "\tgmod_ParseDummy() %.*s\n",
        4, pChunkHeader->ch_Ident.id_Text
    );
    return TRUE;
}

static Reward* gmod_RelocateReward(Reward const* pReward, ChunkHeader const* pRewardChunk)
{
    return (Reward*)((UBYTE*)pReward + (size_t)pRewardChunk);
}

BOOL gmod_ParseSpecialAmmoBonuses(ChunkHeader const* pChunkHeader, GMFData* pGMFData)
{
    printf(
        "\tgmod_ParseSpecialAmmoBonuses() %.*s\n",
        4, pChunkHeader->ch_Ident.id_Text
    );
    ChunkHeader const* pRewardChunk = GMF_LocateChunk(pGMFData, IDENT_RWRD);
    SpecialAmmoBonus* pSPAB = (SpecialAmmoBonus*)gmf_ChunkData(pChunkHeader);
    while (pSPAB->spab_Index != 0xFFFF) {
        if (pSPAB->spab_Reward > 0) {
            printf(
                "Relocating SPAB %d [%d] Reward [%p + %zu] => ",
                (int)pSPAB->spab_Index,
                (int)pSPAB->spab_AmmoID,
                pRewardChunk,
                (size_t)pSPAB->spab_Reward
            );
            Reward* pReward        = gmod_RelocateReward(pSPAB->spab_Reward, pRewardChunk);
            pReward->r_Description = gmf_RelocateString(pReward->r_Description, pGMFData);
            pSPAB->spab_Reward     = pReward;
            puts(pReward->r_Description);
        }
        ++pSPAB;
    }
    return TRUE;
}

/**
 * Zero terminated list of custom parser functions for specific idents
 */
ParserEntry parsers[] = {
    { IDENT_INVL, gmod_ParseDummy },
    { IDENT_SPAB, gmod_ParseSpecialAmmoBonuses },
    { 0, NULL },
};

int main(void) {

    GMFData* pGMFData = GMF_LoadFile("mods/redux.props", &reference, parsers);
    if (pGMFData) {
        printf(
            "\nInitial load successful!\n"
            "Mod Description:\n%s\n"
            "Mod Version    : %d.%d\n"
            "Requires TKG   : v%d.%d\n"
            "Chunks Index\n",
            pGMFData->gmd_Header->h_Description.do_Text,
            (int)pGMFData->gmd_Header->h_Version.v_Major,
            (int)pGMFData->gmd_Header->h_Version.v_Minor,
            (int)pGMFData->gmd_Header->h_RequiresVersion.v_Major,
            (int)pGMFData->gmd_Header->h_RequiresVersion.v_Minor
        );
        for (int i = 0; i < pGMFData->gmd_IndexSize; ++i) {
            ChunkHeader const *pChunkHeader = (ChunkHeader const *)pGMFData->gmd_Index[i].ie_Offset.do_ByteAddress;
            printf(
                "\t%d %.*s : %p %u\n",
                i,
                4, pGMFData->gmd_Index[i].ie_Ident.id_Text,
                pChunkHeader,
                pChunkHeader->ch_Length
            );
        }
        GMF_Free(pGMFData);
    }

	return 0;
}
