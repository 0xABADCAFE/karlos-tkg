#include <stdio.h>
#include <stdint.h>
#include <stdlib.h>

#include "gmod.h"

/**
 * Test it out...
 */
int main(void) {

    GMF_Data* pGMFData = GMOD_LoadFile("mods/redux.props");
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
        for (ULONG i = 0; i < pGMFData->gmd_IndexSize; ++i) {
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
