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




static BOOL gmf_CheckData(GMFData* pGMFData, Header const* pAgainst) {
    puts("\tgmf_CheckData()");

    if (!pGMFData || !pAgainst || !pGMFData->gmd_Data) {
        return FALSE;
    }
    Header const* pFrom = (Header const*)pGMFData->gmd_Data;

/*
    printf(
        "Ident: %.*s => %.*s\n"
        "SubfoFormat: %.*s => %.*s\n"
        "Version: %d.%d => %d.%d\n"
        "Offset: %d\n",
        4, pFrom->h_Ident.id_Text,
        4, pAgainst->h_Ident.id_Text,
        4, pFrom->h_SubFormat.id_Text,
        4, pAgainst->h_SubFormat.id_Text,
        (int) pFrom->h_RequiresVersion.v_Major,
        (int) pFrom->h_RequiresVersion.v_Minor,
        (int) pAgainst->h_Version.v_Major,
        (int) pAgainst->h_Version.v_Minor,
        (int) pFrom->h_Description.do_Offset
    );
*/
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
static BOOL gmf_ReadFile(char const* filename, GMFData* gmfData)
{
    puts("\tgmf_ReadFile()");

    if (!filename || !gmfData) {
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

        gmfData->gmd_Data      = pBuffer;
        gmfData->gmd_Length    = iLength;
        gmfData->gmd_Header    = NULL;
        gmfData->gmd_Index     = NULL;
        gmfData->gmd_IndexSize = 0;
        gmfData->gmd_Strings   = NULL;

        bResult = TRUE;

    } while (FALSE);

    if (pHandle) {
        fclose(pHandle);
    }
    return bResult;
}

static BOOL gmf_ProcessDefaultChunks(GMFData* gmfData)
{
    puts("\tgmf_ProcessDefaultChunks()");
    ChunkHeader const* pIndexHeader = (ChunkHeader const*)(gmfData->gmd_Data + sizeof(Header));
    if (
        pIndexHeader->ch_Ident.id_Value != IDENT_INDX ||
        pIndexHeader->ch_Length < (sizeof(ChunkHeader) + sizeof(IndexEntry))
    ) {
        return FALSE;
    }
    int iNumEntries = (pIndexHeader->ch_Length - sizeof(ChunkHeader))/sizeof(IndexEntry);

    IndexEntry* pIndexEntry = (IndexEntry*)(((UBYTE*)pIndexHeader) + sizeof(ChunkHeader));
    gmfData->gmd_IndexSize  = iNumEntries;
    gmfData->gmd_Index      = pIndexEntry;

    gmfData->gmd_Strings = NULL;

    /**
     * Convert the offsets in the index to their actual addresses
     */
    for (int i = 0; i < iNumEntries; ++i) {
        pIndexEntry[i].ie_Offset.do_ByteAddress = gmfData->gmd_Data + pIndexEntry[i].ie_Offset.do_Offset;

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
            gmfData->gmd_Strings = pIndexEntry[i].ie_Offset.do_Text;
        }
    }

    if (!gmfData->gmd_Strings) {
        printf("String Heap not found in Index\n");
        return FALSE;
    }

    /* Patch the description locaton */
    Header* pHeader = (Header*)gmfData->gmd_Data;
    pHeader->h_Description.do_Text = gmfData->gmd_Strings + pHeader->h_Description.do_Offset;
    gmfData->gmd_Header = pHeader;
    return TRUE;
}


GMFData* GMF_LoadFile(char const* filename, Header const* pCheckHeader)
{
    puts("GMF_LoadFile()");
    if (!filename || !pCheckHeader) {
        return NULL;
    }
    GMFData* gmfData = (GMFData*)calloc(1, sizeof(GMFData));
    if (!gmfData) {
        return NULL;
    }
    if (
        !gmf_ReadFile(filename, gmfData) ||
        !gmf_CheckData(gmfData, pCheckHeader) ||
        !gmf_ProcessDefaultChunks(gmfData)
    ) {
        free(gmfData);
        return NULL;
    }
    return gmfData;
}

void GMF_Free(GMFData* gmfData)
{
    puts("GMF_Free()");
    if (gmfData) {
        if (gmfData->gmd_Data) {
            free(gmfData->gmd_Data);
        }
        free(gmfData);
    }
}


Header const reference = {
    .h_Ident.id_Value     = IDENT_TKGD,
    .h_SubFormat.id_Value = IDENT_GMOD,
    .h_Version            = {1, 255}
};


int main(void) {

    GMFData* gmfData = GMF_LoadFile("mods/redux.props", &reference);
    if (gmfData) {
        printf(
            "\nInitial load successful!\n"
            "Mod Description:\n%s\n"
            "Mod Version    : %d.%d\n"
            "Requires TKG   : v%d.%d\n"
            "Chunks Index\n",
            gmfData->gmd_Header->h_Description.do_Text,
            (int)gmfData->gmd_Header->h_Version.v_Major,
            (int)gmfData->gmd_Header->h_Version.v_Minor,
            (int)gmfData->gmd_Header->h_RequiresVersion.v_Major,
            (int)gmfData->gmd_Header->h_RequiresVersion.v_Minor
        );
        for (int i = 0; i < gmfData->gmd_IndexSize; ++i) {
            ChunkHeader const *pChunkHeader = (ChunkHeader const *)gmfData->gmd_Index[i].ie_Offset.do_ByteAddress;
            printf(
                "\t%d %.*s : %p %u\n",
                i,
                4, gmfData->gmd_Index[i].ie_Ident.id_Text,
                pChunkHeader,
                pChunkHeader->ch_Length
            );
        }
        GMF_Free(gmfData);
    }

	return 0;
}
