#include <stdio.h>
#include "gmod.h"

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
    GMod_SpecialAmmoBonus* pSPAB = (GMod_SpecialAmmoBonus*)GMF_ChunkData(pChunkHeader);
    while (pSPAB->spab_Index != 0xFFFF) {
        if (pSPAB->spab_Reward != NULL) {
            printf(
                "\t\tResolving SPAB %d [%d]\n\t\t\tReward [%p + %zu] ",
                (int)pSPAB->spab_Index,
                (int)pSPAB->spab_AmmoID,
                pRewardChunk,
                (size_t)pSPAB->spab_Reward
            );
            GMod_Reward* pReward   = gmod_ResolveReward(pSPAB->spab_Reward, pRewardChunk);
            pReward->r_Description = GMF_ResolveString(pReward->r_Description, pGMFData);
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
    GMod_Achievement*      pAchievement = (GMod_Achievement*)GMF_ChunkData(pChunkHeader);

    int iNumEntries = pChunkHeader->ch_Length / sizeof(GMod_Achievement);

    for (int i = 0; i < iNumEntries; ++i) {
        pAchievement->achv_Description = GMF_ResolveString(pAchievement->achv_Description, pGMFData);
        if (pAchievement->achv_Reward != NULL) {
            printf(
                "\t\tResolving ACHV %d [Rule %d] [Name %s]\n\t\t\tReward [%p + %zu] ",
                i,
                (int)pAchievement->achv_RuleType,
                pAchievement->achv_Description,
                pRewardChunk,
                (size_t)pAchievement->achv_Reward
            );
            GMod_Reward* pReward   = gmod_ResolveReward(pAchievement->achv_Reward, pRewardChunk);
            pReward->r_Description = GMF_ResolveString(pReward->r_Description, pGMFData);
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

/**
 * Zero terminated list of custom parser functions for specific idents
 */
static GMF_ParserEntry gmod_Parsers[] = {
    { IDENT_SPAB, gmod_ParseSpecialAmmoBonuses },
    { IDENT_ACHV, gmod_ParseAchievements },
    { 0, NULL },
};

/**
 * Main Game Modification File, GMOD
 */
static GMF_Header const gmod_Header = {
    .h_Ident.id_Value     = IDENT_TKGD,
    .h_SubFormat.id_Value = IDENT_GMOD,
    .h_Version            = {1, 255}
};


GMF_Data* GMOD_LoadFile(char const* filename)
{
    return GMF_LoadFile(filename, &gmod_Header, gmod_Parsers);
}
