#include <stdio.h>
#include <stdlib.h>
#include "gmf.h"

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
        pFrom->h_Description.do_Offset >= sizeof(GMF_ChunkHeader);
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

        if (fread(pBuffer, 1, iLength, pHandle) != (ULONG)iLength) {
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
 * GMF_LocateChunk()
 *
 * Returns the first encountered instance of a chunk with the supplied index. It is assumed that a game modification
 * file only contains one of each chunk type.
 */
GMF_ChunkHeader const* GMF_LocateChunk(GMF_Data const* pGMFData, ULONG iIdentValue)
{
    for (ULONG i = 0; i < pGMFData->gmd_IndexSize; ++i) {
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
