#ifndef _TKG_LMOD_H_
#   define _TKG_LMOD_H_

#include "gmf.h"

typedef struct {
    UWORD zd_Data[1];
} ASM_ALIGN(sizeof(WORD)) ZonePVSDeletions;

typedef struct {
    UWORD zd_Data[1];
} ASM_ALIGN(sizeof(WORD)) ZoneBackdropErrata;

typedef struct {
    UWORD zm_ZoneID;
    UWORD zm_Attributes;
    char const* zm_Text;
} ASM_ALIGN(sizeof(WORD)) ZoneMessage;

enum {
    IDENT_PVSE = 0x50565345,
    IDENT_BKDE = 0x424B4445,
    IDENT_ZMSG = 0x5A4D5347,
};

extern GMF_Data* LMod_LoadFile(char const* filename);

#endif
