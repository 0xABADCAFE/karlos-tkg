[Back To Overview](../README.md)

# Game Modification File

Please read the [Data Format](./DataFormat.md) document for further information on the file structure.

The Game Modification File is the binary encoded represntation of the data defined in the [RSON Source](../source/GameModification.md).

## Chunks

The following Chunks are included:

- [Index](./DataFormat.md#index-chunk)
- Inventory Limits
- Special Ammo Bonuses
- Weapon Behaviours
- Achievenents
- Rewards
- [String](./DataFormat.md#string-chunk)

Only the Index, Inventory Limits and String chunks are mandatory.

### Inventory Limits Chunk

The Inventory Limits Chunk contains the binary encoded limits defined in the source [Default Inventory Limits](../GameModification.md#default-inventory-limits) node. A Limit is defined for each of the 20 Ammunition types, along with Health and Fuel. Where these were not specified in the source, the respective internal default is used.

| Offset In Chunk | Content |
| :---- | :---- |
| 0 | **Ident** `"INVL"` |
| 4 | **Length** `uint32` |
| 8 | **Max Health** `int16` |
| 10 | **Max Fuel** `int16` |
| 12 | **Max Ammo \[0\]** `int16` |
| 14 | **Max Ammo \[1\]** `int16` |
| ... | ... |
| 48 | **Max Ammo \[19\]** `int16` |

Notes:

- The default maximum value for Ammunition is 32767
- The default maximum value for Health is 32767
- The default maximum value for Fuel is 255
- The values in this chunk represent the initial limits for a new game. Player progression files are saved that include the impact of any bonuses added to these limits due to locating special bonuses or completing achievements.

### Special Ammo Bonuses

The Special Ammo Bonuses Chunk contains the binary encoded values defined in the [Special Ammo Bonuses](./GameModification.md#special-ammo-bonuses) node. If the node is omitted, no Chunk is generated.

| Offset In Chunk | Content |
| :---- | :---- |
| 0 | **Ident** `"SPAB"` |
| 4 | **Length** `uint32` |
| 8 | **Reserved \[0\]** `uint16` |
| 10 | **Ammo Type ID \[0\]** `uint16` |
| 12 | **Reward Offset \[0\]** `uint32` Offset into Reward Chunk for |
| ... | ... |
| N \+ 0 | **Reserved \[N\]** `uint16` |
| N \+ 2 | **Ammo Type ID \[N\]** `uint16` |
| N \+ 4 | **Reward Offset \[N\]** `uint32` |
| N \+ 8 | **End Marker** `uint16` 0xFFFF |
| N \+ 10 | **Pad** `uint8[2]` |

Notes:

- Since each special bonus defines a corresponding Reward, the Reward Chunk must also be present.
- The Ammo Type values can only be those defined in the SpecialAmmoTypes list.
