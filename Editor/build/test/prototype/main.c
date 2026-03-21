#include <stdio.h>
#include <stdint.h>
#include <stdlib.h>

#include "gmod.h"

void printInventory(InventoryConsumables const* pConsumables) {
    printf(
        "| H:%3d | F:%3d |\n| ",
        (int)pConsumables->ic_Health,
        (int)pConsumables->ic_JetpackFuel
    );
    for (int i = 0; i < NUM_BULLET_DEFS; ++i) {
        printf("%3d | ", (int)pConsumables->ic_AmmoCounts[i]);
    }
    putchar('\n');
}

void applyAchievements(GMF_Data const* pGMFData)
{
    GMF_ChunkHeader const* pAchievementsChunk = GMF_LocateChunk(pGMFData, IDENT_ACHV);
    int numAchievemnts = pAchievementsChunk->ch_Length / sizeof(GMod_Achievement);
    GMod_Achievement const* pAchievement = (GMod_Achievement const*)GMF_ChunkData(pAchievementsChunk);


    for (int i = 0; i < numAchievemnts; ++i) {
        if (pAchievement[i].achv_Reward) {

            InventoryConsumables consumables = {
                .ic_Health = 100,
                .ic_JetpackFuel = 0,
                .ic_AmmoCounts = {
                    0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                    0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
                }
            };

            InventoryConsumables limits = {
                .ic_Health = 200,
                .ic_JetpackFuel = 200,
                .ic_AmmoCounts = {
                    10, 10, 10, 10, 10, 10, 10, 10, 10, 10,
                    10, 10, 10, 10, 10, 10, 10, 10, 10, 10
                }
            };
            puts("\n\nBEFORE:");
            printf("\tCARRY LIMITS ");
            printInventory(&limits);
            printf("\tCURRENT      ");
            printInventory(&consumables);
            printf("Adding Achievement %d: %s\n", i, pAchievement[i].achv_Description);
            printf("\tReward: %s\n", pAchievement[i].achv_Reward->rwrd_Description);
            GMod_ApplyReward(
                pAchievement[i].achv_Reward,
                &limits,
                &consumables
            );
            puts("\nAFTER:");
            printf("\tCARRY LIMITS ");
            printInventory(&limits);
            printf("\tCURRENT      ");
            printInventory(&consumables);
        }
    }
}

/**
 * Test it out...
 */
int main(void) {

    GMF_Data* pGMFData = GMod_LoadFile("mods/redux.props");
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

        applyAchievements(pGMFData);

        GMF_Free(pGMFData);
    }

    return 0;
}
