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
 * GMF_DataOffset
 *
 * Represents an offset in the file that is to be resolved to an actual address during parsing.
 */
typedef union {
    ULONG       do_Offset;
    char const* do_Text;
    UBYTE*      do_ByteAddress;
} ALIGN(sizeof(ULONG)) GMF_DataOffset;

/**
 * GMF_Version
 *
 * Basic major.minor version tuple used for version checks.
 */
typedef struct {
    UWORD       v_Major;
    UWORD       v_Minor;
} ALIGN(sizeof(ULONG)) GMF_Version;

/**
 * GMF_Ident
 *
 * Basic 4-byte ident. Accessible either as a 32-bit word or as 4 characters. Big endian layout.
 */
typedef union {
    char        id_Text[4];
    ULONG       id_Value;
} ALIGN(sizeof(ULONG)) GMF_Ident;

/**
 * GMF_Header
 *
 * Main game modification file header.
 */
typedef struct {
    GMF_Ident       h_Ident;
    GMF_Ident       h_SubFormat;
    GMF_Version     h_RequiresVersion;
    GMF_Version     h_Version;
    GMF_DataOffset  h_Description;
} ALIGN(sizeof(ULONG)) GMF_Header;

/**
 * GMF_ChunkHeader
 *
 * Minimalist header for each Chunk.
 */
typedef struct {
    GMF_Ident   ch_Ident;
    ULONG       ch_Length;
} ALIGN(sizeof(ULONG)) GMF_ChunkHeader;

/**
 * GMF_IndexEntry
 *
 * Basic Ident/Offset pair for the common Index chunk.
 */
typedef struct {
    GMF_Ident       ie_Ident;
    GMF_DataOffset  ie_Offset;
} ALIGN(sizeof(ULONG)) GMF_IndexEntry;

/**
 * GMF_Data
 *
 * Main structure for loaded Game Modification Files.
 */
typedef struct {
    UBYTE*                gmd_Data;
    ULONG                 gmd_Length;
    ULONG                 gmd_IndexSize;
    GMF_Header const*     gmd_Header;
    GMF_IndexEntry const* gmd_Index;
    char const*           gmd_Strings;
} ALIGN(sizeof(ULONG)) GMF_Data;


/**
 * GMF_ChunkParser
 *
 * Callable type for parsing chunks after loading.
 */
typedef BOOL (*GMF_ChunkParser)(GMF_ChunkHeader const* pChunkHeader, GMF_Data* pGMFData);

/**
 * GMF_ParserEntry
 *
 * Basic Ident/Parser pair for associating a parser to a particular chunk type.
 */
typedef struct {
    ULONG pe_Ident;
    GMF_ChunkParser pe_Parser;
} ALIGN(sizeof(ULONG)) GMF_ParserEntry;

/**********************************************************************************************************************/

/**
 * gmf_CheckData()
 *
 * Basic valiation for Game Modification Files based on expected header properties.
 */
static BOOL gmf_CheckData(GMF_Data* pGMFData, GMF_Header const* pAgainst) {
    puts("\tgmf_CheckData()");

    if (!pGMFData || !pAgainst || !pGMFData->gmd_Data) {
        return FALSE;
    }
    GMF_Header const* pFrom = (GMF_Header const*)pGMFData->gmd_Data;

    return
        pAgainst->h_Ident.id_Value     == pFrom->h_Ident.id_Value &&
        pAgainst->h_SubFormat.id_Value == pFrom->h_SubFormat.id_Value &&
        pAgainst->h_Version.v_Major    == pFrom->h_RequiresVersion.v_Major &&
        pAgainst->h_Version.v_Minor    >= pFrom->h_RequiresVersion.v_Minor &&
        pFrom->h_Description.do_Offset >= 8;
}


/**
 * gmf_ReadFile()
 *
 * Attempts to load the named file, populating the data and length fields of the GMF_Data and zeroing the rest.
 * Once loaded, the data must be validated.
 */
static BOOL gmf_ReadFile(char const* filename, GMF_Data* pGMFData)
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

/**
 * gmf_ProcessDefaultChunks()
 *
 * Handles parsing the default chunks common to all Game Modification Format files, i.e. the Index and String
 * chunks.
 */
static BOOL gmf_ProcessDefaultChunks(GMF_Data* pGMFData)
{
    puts("\tgmf_ProcessDefaultChunks()");
    GMF_ChunkHeader const* pIndexHeader = (GMF_ChunkHeader const*)(pGMFData->gmd_Data + sizeof(GMF_Header));
    if (
        pIndexHeader->ch_Ident.id_Value != IDENT_INDX ||
        pIndexHeader->ch_Length < (sizeof(GMF_ChunkHeader) + sizeof(GMF_IndexEntry))
    ) {
        return FALSE;
    }
    int iNumEntries = (pIndexHeader->ch_Length - sizeof(GMF_ChunkHeader))/sizeof(GMF_IndexEntry);

    GMF_IndexEntry* pIndexEntry = (GMF_IndexEntry*)(((UBYTE*)pIndexHeader) + sizeof(GMF_ChunkHeader));
    pGMFData->gmd_IndexSize  = iNumEntries;
    pGMFData->gmd_Index      = pIndexEntry;

    pGMFData->gmd_Strings = NULL;

    /**
     * Convert the offsets in the index to their actual addresses
     */
    for (int i = 0; i < iNumEntries; ++i) {
        pIndexEntry[i].ie_Offset.do_ByteAddress = pGMFData->gmd_Data + pIndexEntry[i].ie_Offset.do_Offset;

        /* Quick sanity check - ensure the index ident is a match for the in memory location */
        GMF_Ident const* pChunkIdent = (GMF_Ident const*)pIndexEntry[i].ie_Offset.do_ByteAddress;
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
    GMF_Header* pHeader = (GMF_Header*)pGMFData->gmd_Data;
    pHeader->h_Description.do_Text = pGMFData->gmd_Strings + pHeader->h_Description.do_Offset;
    pGMFData->gmd_Header = pHeader;
    return TRUE;
}

/**
 * gmf_ChunkData()
 *
 * Returns the start of the chunk body data for a given header. This is simply the data immediately following.
 * Assumes the returned data requires modification.
 */
static void* gmf_ChunkData(GMF_ChunkHeader const *pHeader)
{
    return ((UBYTE*)pHeader) + sizeof(GMF_ChunkHeader);
}

/**
 * gmf_ResolveString()
 *
 * For a given initial string reference, resolves an offset to the actual in-memory location within the common
 * string chunk.
 */
static const char* gmf_ResolveString(char const* string, GMF_Data const* pGMFData)
{
    return (string < pGMFData->gmd_Strings) ? pGMFData->gmd_Strings + (size_t)string : string;
}

/**
 * GMF_LocateChunk()
 *
 * Returns the first encountered instance of a chunk with the supplied index. It is assumed that a game modification
 * file only contains one of each chunk type.
 */
GMF_ChunkHeader const* GMF_LocateChunk(GMF_Data const* pGMFData, ULONG iIdentValue)
{
    for (int i = 0; i < pGMFData->gmd_IndexSize; ++i) {
        if (pGMFData->gmd_Index[i].ie_Ident.id_Value == iIdentValue) {
            return (GMF_ChunkHeader const *)pGMFData->gmd_Index[i].ie_Offset.do_ByteAddress;
        }
    }
    return NULL;
}

/**
 * GMF_LoadFile()
 *
 * Attempts to load the specified Game Modification File and process with a null terminated list of user supplied
 * parsers for any custom chunk types that are present.
 */
GMF_Data* GMF_LoadFile(char const* filename, GMF_Header const* pCheckHeader, GMF_ParserEntry const* pCustomParsers)
{
    puts("GMF_LoadFile()");
    if (!filename || !pCheckHeader) {
        return NULL;
    }
    GMF_Data* pGMFData = (GMF_Data*)calloc(1, sizeof(GMF_Data));
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
        GMF_ChunkHeader const* pChunkHeader = NULL;
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

/**
 * GMF_Free()
 *
 * Releases a GMF_Data instance and any associated data.
 */
void GMF_Free(GMF_Data* pGMFData)
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

/**
 * Main Game Modification File, GMOD
 */
GMF_Header const reference = {
    .h_Ident.id_Value     = IDENT_TKGD,
    .h_SubFormat.id_Value = IDENT_GMOD,
    .h_Version            = {1, 255}
};

enum {
    IDENT_INVL = 0x494E564C,
    IDENT_SPAB = 0x53504142,
    IDENT_RWRD = 0x52575244,
    IDENT_ACHV = 0x41434856,
};

/**
 * GMod_Reward
 *
 * Defines a reward structure, which applied modifications to player items and carry limits.
 */
typedef struct {
    char const* r_Description;
    UWORD       r_CarryOffset;
    UWORD       r_ImmediateOffset;
    UWORD       r_RewardData[1];
} ALIGN(sizeof(ULONG)) GMod_Reward;

/**
 * GMod_SpecialAmmoBonus
 *
 * Defines rewards associated with collecting of special ammo types.
 */
typedef struct {
    UWORD              spab_Index;
    UWORD              spab_AmmoID;
    GMod_Reward const* spab_Reward;
} ALIGN(sizeof(ULONG)) GMod_SpecialAmmoBonus;

/**
 * GMod_Achievement
 *
 * Defines rewards associated with collecting of special ammo types.
 */
typedef struct {
    char const*        achv_Description;
    GMod_Reward const* achv_Reward;
    UWORD              achv_RuleType;
    UWORD              achv_Params[3];
} ALIGN(sizeof(ULONG)) GMod_Achievement;

/**********************************************************************************************************************/

/**
 * gmod_ParseDummy()
 *
 * Dummy chunk parser just for evaluation purposes.
 */
BOOL gmod_ParseDummy(GMF_ChunkHeader const* pChunkHeader, GMF_Data* pGMFData)
{
    printf(
        "\tgmod_ParseDummy() %.*s\n",
        4, pChunkHeader->ch_Ident.id_Text
    );
    return TRUE;
}

/**
 * gmod_ResolveReward()
 *
 * For a given initial reward reference, resolves an offset to the actual in memory location of the GMod_Reward
 * instance.
 */
static GMod_Reward* gmod_ResolveReward(GMod_Reward const* pReward, GMF_ChunkHeader const* pRewardChunk)
{
    return (GMod_Reward*)((UBYTE*)pReward + (size_t)pRewardChunk);
}

/**
 * gmod_ParseSpecialAmmoBonuses()
 *
 * Parser for the Special Ammo Bonuses Chunk
 */
BOOL gmod_ParseSpecialAmmoBonuses(GMF_ChunkHeader const* pChunkHeader, GMF_Data* pGMFData)
{
    printf(
        "\tgmod_ParseSpecialAmmoBonuses() %.*s\n",
        4, pChunkHeader->ch_Ident.id_Text
    );
    GMF_ChunkHeader const* pRewardChunk = GMF_LocateChunk(pGMFData, IDENT_RWRD);
    GMod_SpecialAmmoBonus* pSPAB = (GMod_SpecialAmmoBonus*)gmf_ChunkData(pChunkHeader);
    while (pSPAB->spab_Index != 0xFFFF) {
        if (pSPAB->spab_Reward > 0) {
            printf(
                "\t\tResolving SPAB %d [%d]\n\t\t\tReward [%p + %zu] ",
                (int)pSPAB->spab_Index,
                (int)pSPAB->spab_AmmoID,
                pRewardChunk,
                (size_t)pSPAB->spab_Reward
            );
            GMod_Reward* pReward   = gmod_ResolveReward(pSPAB->spab_Reward, pRewardChunk);
            pReward->r_Description = gmf_ResolveString(pReward->r_Description, pGMFData);
            pSPAB->spab_Reward     = pReward;
            puts(pReward->r_Description);
        }
        ++pSPAB;
    }
    return TRUE;
}

/**
 * gmod_ParseAchievements()
 *
 * Parser for the Special Ammo Bonuses Chunk
 */
BOOL gmod_ParseAchievements(GMF_ChunkHeader const* pChunkHeader, GMF_Data* pGMFData)
{
    printf(
        "\tgmod_ParseAchievements() %.*s\n",
        4, pChunkHeader->ch_Ident.id_Text
    );
    GMF_ChunkHeader const* pRewardChunk = GMF_LocateChunk(pGMFData, IDENT_RWRD);
    GMod_Achievement*      pAchievement = (GMod_Achievement*)gmf_ChunkData(pChunkHeader);

    int iNumEntries = pChunkHeader->ch_Length / sizeof(GMod_Achievement);

    for (int i = 0; i < iNumEntries; ++i) {
        pAchievement->achv_Description = gmf_ResolveString(pAchievement->achv_Description, pGMFData);
        if (pAchievement->achv_Reward > 0) {
            printf(
                "\t\tResolving ACHV %d [Rule %d] [Name %s]\n\t\t\tReward [%p + %zu] ",
                i,
                (int)pAchievement->achv_RuleType,
                pAchievement->achv_Description,
                pRewardChunk,
                (size_t)pAchievement->achv_Reward
            );
            GMod_Reward* pReward   = gmod_ResolveReward(pAchievement->achv_Reward, pRewardChunk);
            pReward->r_Description = gmf_ResolveString(pReward->r_Description, pGMFData);
            pAchievement->achv_Reward = pReward;
            puts(pReward->r_Description);
        } else {
            printf(
                "\t\tResolving ACHV %d [Rule %d] [Name %s]\n",
                i,
                (int)pAchievement->achv_RuleType,
                pAchievement->achv_Description
            );
        }
        ++pAchievement;
    }

    return TRUE;
}

/**********************************************************************************************************************/

/**
 * Zero terminated list of custom parser functions for specific idents
 */
GMF_ParserEntry parsers[] = {
    { IDENT_INVL, gmod_ParseDummy },
    { IDENT_SPAB, gmod_ParseSpecialAmmoBonuses },
    { IDENT_ACHV, gmod_ParseAchievements },
    { 0, NULL },
};

/**
 * Test it out...
 */
int main(void) {

    GMF_Data* pGMFData = GMF_LoadFile("mods/redux.props", &reference, parsers);
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
            GMF_ChunkHeader const *pChunkHeader = (GMF_ChunkHeader const *)pGMFData->gmd_Index[i].ie_Offset.do_ByteAddress;
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
