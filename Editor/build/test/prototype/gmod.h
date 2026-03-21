#ifndef _TKG_GMOD_H_
#   define _TKG_GMOD_H_

#include "gmf.h"

#define NUM_BULLET_DEFS 20
/*
 * These structures are managed by the assembler side and the alignment constraints are to preven the compiler
 * from padding them further for alignment purposes. It does not mean that the structures themselves are only
 * aligned to a 2 byte boundary,
 */
typedef struct {
    /* Note that we have separate named fields here, but we regard the struct as equivalent to UWORD[]*/
    UWORD ic_Health;
    UWORD ic_JetpackFuel;
    UWORD ic_AmmoCounts[NUM_BULLET_DEFS];
} ASM_ALIGN(sizeof(WORD)) InventoryConsumables;

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
} ASM_ALIGN(sizeof(ULONG)) GMod_Reward;

/**
 * GMod_SpecialAmmoBonus
 *
 * Defines rewards associated with collecting of special ammo types.
 */
typedef struct {
    UWORD              spab_Index;
    UWORD              spab_AmmoID;
    GMod_Reward const* spab_Reward;
} ASM_ALIGN(sizeof(ULONG)) GMod_SpecialAmmoBonus;

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
} ASM_ALIGN(sizeof(ULONG)) GMod_Achievement;

/**
 * GMOD_LoadFile()
 *
 * Attempts to load the specified Game Modification File and process with a null terminated list of user supplied
 * parsers for any custom chunk types that are present.
 */
extern GMF_Data* GMod_LoadFile(char const* filename);

extern void GMod_ApplyReward(
    GMod_Reward const* pReward,
    InventoryConsumables* pInventoryLimits,
    InventoryConsumables* pInventoryConsumables
);

#endif
