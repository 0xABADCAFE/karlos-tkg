# Modification Data Format

The following document describes the data format for game modification files. At the time of writing there are two types of modification file:

- AB3:Includes/game.props
    - Global modifiations to the game behaviour such as Inventory limits, achievements and rewards.

- AB3:Levels/LEVEL_x/level.props
    - Per-level modifications such as geometry fixes, objectives and zone or entity specific additional metadata.


## Features

- Trivial to load as a single in memory allocation.
- Compressable using the existing SB utilities.
- In-place data conversion after loading/decompression, e.g. offsets to addresses.
- Version checked.

### Prior Art

- IFF
    - This provides a simple container format for chunked binary data.
    - Has OS supported libraries for parsing.
    - File and Chunk ID values used to be officially registered.
    - Nominally 16-bit aligned data.

- XSF
    - IFF-inspired format intended for self-describing data that is platform dependent.
    - Well suited to the requirements, with additional per chunk medatada.
    - At least 32-bit aligned data.
    - Old personal project, not really maintained.

There are are shared issues with each format:

 - Overly general purpose.
 - Intended for incremental load and parse, which is not well suited to the goal of loading the entire data once and processing in-place.

### Design Choices

Like IFF and XSF the data are organised into a header followed by a sequential arrangement of data chunks:

- Strict 32-bit alignment of boundaries, e.g. Header and Chunk sizes.
- Header indicates overall content type, version and version required.
- Chunks begin with a minimal subheader for the chunk type and data size.
- Where necessary, Chunks are zero padded to the next 32-bit aligned length.
- Chunk subheader data size field represents the complete size of the Chunk, including the subheader, any padding and is consequently always a multiple of 4.


## Structure


### Source Assets

A JSON-based text format is used for the source assets from which the binary files are compiled. The source asset is intended to be human editable in a basic text editor rather than machine generated. Consequently the following relaxations of JSON notation are supported:

 - Line comments beginning with `//` are supported.
 - Lists and arrays may include a trailing comma.

**Example**

```
    {
        // A tuple of name:quantity pairs, with a trailing comma.
        "FruitBowl": {
            "Oranges": 5,
            "Apples": 3,
        },
    }
```

The tooling that generates the binary files from the assets can trivially strip these modifications from the source text to yeild strict JSON for parsing.

### Type Conventions

This document uses the following C-like conventions for binary types:

- char for 8-bit character data
- int<_size_> / uint<_size_> for signed and unsigned integer types, e.g. int8, uint16
- <_type_>[<_size_>] for fixed length arrays, e.g. uint32[8]
- <_type_>[...] for varying length arrays, e.g. char[...]
- { <_type_>, <_type_>, ... } for unnamed tuples, e.g. { uint32, int16, int16 }

A type name may also refer to a named structure definiton. All values that are larger than a byte will be stored in Big-Endian byte order.

In addition to the above, there is a special interpetation of a uint32 value that represents an offset from some defined base to the start location of some data of a particular type:

```
    union Offset<T> {
        // In file and immediately after loading
        uint32 offset;

        // After loading and processing, T* ((uint32)baseAddress + offset)
        T const* memoryLocation;
    };
```

When the file is loaded and the in-memory baseAddress is known, these offsets are converted to their respective pointer locations by adding the offset and interpeting the resuting value as the data type

There are two common implementations:

- `typedef Offset<Chunk> ChkOffs;`
    -  Distance from the beginning of the file to the beginning of a Chunk.


- `typedef Offset<char> StrOffs;`
    - Distance from the beginning of the String Heap Chunk to the first character of a string in the chunk data.

### Header

The file header should contain a simple type and version indication:

- Type indicator shall be a simple 4-byte value that indicates the specific content of the file. At the time of writing we require a global format for game modification and a per-level format for level modifications:

    - `GMOD`: Identifies the global game modification file.
    - `LMOD`: Identifies a per-level modification file.

- Version
    - Basic version data of the file.
    - Includes a major and minor component.

- Version Required
    - Defines the minimum version of the engine that the data format will work with.
    - Includes a major and minor component.

**Asset Structure:**

Within the source asset, the header is defines by a root level node indicated by a `Header` key:

```
    "Header": {
        "Type":"<Game|Level>",
        "Description": "<optional description>",
        "Version": "<major>.<minor>",
        "Requires": "<major>.<minor>",
    }
```

- The fields can be present in any order.
- Only one Header structure may be present.
- The Type field is a human-readable enumeration of the Subformat:
    - `GMOD`: Game
    - `LMOD`: Level

- The Description field is optional.
   - When omitted, the corresponding binary field is set to zero.
   - When included, the file is guaranteed to contain the String Heap chunk even if there are no other string data.


**Binary Structure:**

The asset fields are encoded into a 16-byte binary structure:

```
    {
        char[4]   Type;        //  0: GMOD, LMOD, etc
        uint16[2] Requires;    //  4: [0] Major, [1] Minor
        uint16[2] Version;     //  8: [0] Major, [1] Minor
        StrOffs   Description; // 12:
    }

```

### Import

Asset files support an import mechanism that allows definitions to be loaded in from other files. This is intended to ensure a single point of defintion, especially for things like entity names, etc.

```
    "Import": {
        "<name>":"<path to file>",
    }
```

For each entry in the Import node, the corresponding file is loaded and the root structure defined within it is assigned to the corresponding key in the Import node. For example:

**Main asset.json**

```
{
    "Import": {
        "Fruit": "common/fruit.json",
    },
}
```

**Include common/fruit.json**

```
{
    // Enumerated fruit
    "Apple": 0,
    "Banana": 1,
    "Pear": 2,
    "Orange": 3,
}
```

On parsing the `asset.json` file, processing the Import node attempts to load the `common/fruit.json` and apply the contents in place, e.g:

```
{
    "Import": {
        "Fruit": {
            "Apple": 0,
            "Banana": 1,
            "Pear": 2,
            "Orange": 3,
        },
    },
}
```

**Note:**

- This behaviour is only applied to the Import node.
- The process supports nesting and imports are processed recursively.

### Chunks

Everything following the header is a Chunk. A Chunk begins with the data format and length, followed by the data itself.

**Asset Structure:**

There is no secific user-defined generalisation for the asset structure, only the data embedded within it, which is type-specific. A sub-header is automatically generated based on the final encoded size and type information.


**Binary Structure:**

```
    {
        char[4]    Type;    // 0:
        uint32     Size;    // 4: Total size, including header, content and any padding
        uint8[...] Content; // 8: Content, padding
    }
```

The interpretation of the Content depends on the specific Chunk type.


## Common Chunk Types

The following chunk types are common to each defined data file and can only be included once per file:

### Index

The Index Chunk contains a list of `ChkOffs` that point to the location of other Chunks in the file. Each offset is accompanied by the 4 character ident string of the Chunk pointed to, permitting simple verification of the data after loading and looking up the location of a Chunk by ident string. The Index chunk must immediately follow the file header in any file that contains it. Since this implies a fixed location, the Index Chunk does not contain an entry for itself.

**Asset Structure:**

The Index Chunk is not manually generated and consequently does not have a defined asset structure. It is produced as an artefact by the compilation process.

**Binary Structure:**

```
    {
        char[4]    Type;    // 0: { 'I', 'N', 'D', 'X' }
        uint32     Size;    // 4: (N * 8) + 8
        struct {            // 8:
            char[4] Ident;  // Each entry is 8 bytes
            ChkOffs Index;
        } [N]
    }
```

**Notes:**

- The number of entries in the Index is trivially determined from the Size field, e.g. (Size - 8)/8.


### String Heap

The String Heap Chunk consolidates text strings from Chunks into a single blob of null-terminated strings, allowing them to be represented as `StrOffs` entries in the Chunks that contain them rather than being directly embedded.

**Asset Structure:**

The String Heap Chunk is not manually generated and consequently does not have a defined asset structure. It is produced as an artefact by the compilation process.


**Binary structure:**

```
    {
        char[4]   Type;    // 0: { 'S', 'T', 'R', 'H' }
        uint32    Size;    // 4:
        char[...] Content; // 8:
    }
```

**Notes:**

- Unlike the Index Chunk, the String Heap Chunk does not have a predefined location in the file and will always have an entry in the Index Chunk.
    - It may be simpler for tooling to place the String Heap Chunk as the final Chunk.

- As char array data, individual strings are null-terminated but not padded. Only the end of the chunk is padded.
- Only unique strings are recorded in the heap.
- `StrOffs` values are measured from the beginning of the String Heap Chunk, meaning that the smallest non-zero value is 8.
    - Empty strings are not encoded and will generate zero as the `StrOffs` value, which will be interpreted as NULL reference when converted to a pointer at runtime.

## Imports

This section describes the currently defined set of common import files.

### Link Definitions

The Link Definitions include acts as a bridge between the data in the original game test.lnk file an asset file by assigning names to ID lookups.

**Asset Structure:**

```
{
    "AlienTypes": {
        // The game link file defines up 20 alien types, enumerated 0-19.
        // This node defines names to each type that are then used in the rest of the file.
        "<name>": <id>,
    },
    "PlayerAmmoTypes": {
        // The game link file defines up to 20 ammunition types, enumerated 0-19. These are
        // shared between aliens and the player and any 10 of these are assignable to
        // the weapons used by the player. This node defines names for those used by player
        // weapons.
        // It is worth noting that a pickup can award any amount of any of the 20 ammunition
        // types.
        "<name>": <id>,
    },
    "SpecialAmmoTypes": {
        // Since the Player can not use the other 10 ammunition types directly and a pickup
        // can give any of the 20 defined types, we can repurpose the other types for special
        // collectables.
        "<name>": <id>,
    },
    // Other lookups
}
```

The tooling that generates the binary modification file parses the `LinkDefs` node to build the required mapping of names back to integer values.


## Game Modification Asset

This section documents the main Game Modification file. The specific ordering of nodes in the file is not important, only that they are added at the root level.

### Header

The Game Modification file must include a `Header` node at the root level:

```
    "Header": {
        "Type":"Game",
        "Description": "<optional description>",
        "Version": "<major>.<minor>",
        "Requires": "<major>.<minor>",
    },
```

### Import

The game modification file must have an `Import` node for the Link Definitions data file as `LinkDefs` in order to have access the appropriate link enumerations.

```
    "Import": {
        "LinkDefs": "<path to link definitions file>",
    },
```

### DefaultInventoryLimits

The `DefaultInventoryLimits` node sets the initial limits for player comsumables and ammunition when starting a new game:

**Asset Structure:**

```
    "DefaultInventoryLimits": {
        "MaxHealth": <count>,
        "MaxJetpackFuel": <count>,
        "MaxAmmo": {
            // Initial limits for each of the PlayerAmmoTypes and SpecialAmmoTypes
            "<name>": <count>,
        },
    },

```

The initial limits defined here can be raised via rewards for completing objectives or finding special bonus items. The actual limits are saved in the player progress data when exiting the game.

The `DefaultInventoryLimits` data are encoded into a Chunk:

**Binary structure:**

```
    {
        char[4]    Type;      // 0: { 'I', 'N', 'V', 'L' }
        uint32     Size;      // 4:
        uint16     MaxHealth; // 8:
        uint16     MaxFuel;   // 10:
        uint16[20] MaxAmmo;   // 12: One for each ammunition type.
    }
```

**Notes:**

- The binary chunk contains a value for each of the 20 ammunition types defined in the game.
- If no limit is defined for any particular `PlayerAmmoType`, the internal default value of 32767 is used.
- If the `MaxHealth` limit is ommitted, the internal default value of 32767 is used.
- If the `MaxFuel` limit is ommitted, the internal default of 255 is used.


### Rewards

Rewards are modifications to the active Inventory Limits that are awarded for completing various achievements or collecting special items. In order to make the asset file more accessible, like text strings, rewards are defined inline within other structures that they pertain to. Also, like text strings, rewards are collected into a single Chunk witin the file.

Rewards define two parts:

- Immediate bonus - Adds health/fuel/ammo
- Carry limit bonus - Increases the amount of health/fuel/ammo

A reward can contain any combination of these. Where there are both carry limit bonuses and immediate bonuses, the carry limit bonus is applied first.

**Asset Structure**

```
    {
        "Description": "<text>",
        "Immediate": {
            // Immediate bonuses (if any)
            "AddHealth": <count>,
            "AddJetpackFuel": <count>,
            "AddAmmo": {
                "<name>": <count>,
            },
        },
        "CarryLimit": {
            // Carry limit bonuses (if any)
            "AddHealth": <count>,
            "AddJetpackFuel": <count>,
            "AddAmmo": {
                "<name>": <count>,
            }
        }
    }
```

**Binary Structure:**

Reward definitions are consolidated into a single Chunk. Individual Reward structures are varying length due to the fact they are not required to modify the entire player inventory. Therefore, References to reward data in other Chunks operate along the same principle as `StrOffs` values; a 32-bit offset value that when added to the address location of the Reward Chunk, give the location of the reward data. An offset of zero, is considered a NULL reference.

```
    {
        char[4]     Type;       // 0: { 'R', 'W', 'R', 'D' }
        uint32      Size;       // 4:
        uint16[...] RewardList; // 8:
    }
```

The `RewardList` field is a stream of varying sized structures. For simplicity of parsing, an offset pair is embedded that indicate the locations of the carry and immediate bonus offset struture in the binary.

```
    {
        StrOffs Description;

        // Following offsets are measured relative to the start of the structure.
        // If an offset is zero, the corresponding section does not exist.
        uint16      ImmediateOffset;    // Offset to ImmediateBonusData, 0 if not present
        uint16      CarryOffset;        // Offset to CarryBonusData, 0 if not present
        uint16[...] ImmediateBonusData; // if present
        uint16[...] CarryBonusData;     // if present
    }
```

At least one of `CarryBonusData` and `ImmediateBonusData` must be present. These are varying length uint16 arrays that are terminated by an 0xFFFF entry. These contain the Health, Fuel and Ammo increments as defined by a `Reward` node. The data in these arrays conforms to the following structure:

```
    {
        uint16 AddHealth;      // Always present, can be zero if no bonus.
        uint16 AddJetpackFuel; // Always present, can be zero if no bonus.
        {
            uint16 AmmoType;
            uint16 Count;
        } [...] AddAmmo;
        uint16 _terminator; // 0xFFFF
    }
```

**Notes:**

- A complete Reward structure always contains the Health and Fuel values, with a zero value indicating no change to the required inventory/limit.
- The `AddAmmo` list can be empty if there are no specific ammunition bonuses.
- If the entire Bonus structure is an odd number of uint16, it is padded to the next 32-bit boundary with an additional termination word.

**Worked Examples**

The following asset node defines a 40 point health bonus, plus a permanent increase of 40 for the maximum health.

```
    {
        "Description": "Hands of the Healer! +40 HP",
        "Immediate": {
            "AddHealth": 40,
        },
        "CarryLimit": {
            "AddHealth": 40,
        }
    }

```

The corresponding binary representation:

```
    {
        .Description = <offset in String Heap>, // 0
        .ImmediateOffset = 8,                   // 4
        .CarryOffset     = 14,                  // 6
        { // 8
            // Immediate Bonus Data
            .AddHealth = 40;                    // 8
            .AddJetpackFuel = 0,                // 10
            // AddAmmo list empty
            ._terminator = 0xFFFF               // 12
        },
        { // 14
            // Carry Bonus Data
            .AddHealth = 40;                    // 14
            .AddJetpackFuel = 0,                // 16
            // AddAmmo list empty
            ._terminator = 0xFFFF               // 18
        }                                       // 20 - no padding required
    } // Total Size 20
```

The following asset node defines an increased carry limit for explosives only:

```
    {
        "Description": "Boomer! Increased explosives carry.",
        "CarryLimit": {
            "AddAmmo": {
                "Rockets": 3,
                "Grenades": 6,
                "Mines": 2,
            },
        }
    }

```

The corresponding binary representation:
```
    {
        .Description = <offset in String Heap>, // 0
        .CarryOffset = 8,                       // 4
        .ImmediateOffset = 0,                   // 6
        { // 8
            // Carry Bonus Data
            .AddHealth = 0;                     // 8
            .AddJetpackFuel = 0,                // 10
            .AddAmmo = {
                <Rocket ID>, 3,                 // 12
                <Grenade ID>, 6,                // 16
                <Mine ID>, 2                    // 20
            },
            ._terminator = 0xFFFF,              // 24
        }                                       // 26 - padding required
        ._padding = 0xFFFF,                     // 26
    } // Total Size 28
```

### SpecialAmmoBonuses

The optional `SpecialAmmoBonuses` node defines a set of `Reward` definitions that pertain to the collection of items that give any of the ammunition types enumerated in the `SpecialAmmoTypes` node imported from `LinkDefs`. This allows for the definition of one-off collectable objects in game, that can give the special ammo type on collection, triggering the associated Reward as a consequence.

**Asset Structure:**

The Asset structure is a simple list of Special Ammo Name => Reward data

```
    "SpecialAmmoBonuses": {
        "<Special Ammo Type>": {
            <Reward Definition>
        },
    }

```

The `SpecialAmmoBonuses` data are encoded into a dedicated Chunk:

**Binary structure:**

```
    {
        char[4]    Type;      // 0: { 'S', 'P', 'A', 'B' }
        uint32     Size;      // 4:
        struct {
            uint16 Reserved;
            uint16 AmmoType;
            Offset<Reward> Reward; // Offset to the already described varying length Reward structure.
        } [N] // Each record is 8 bytes
    }
```

The `Reserved` field is currently unused and presently only serves to keep each Offset record aligned.

## TODO - Rewrite everything below


### Achievements

This chunk specifies the set of achievements that are defined by the modification. The current implementation of the game combines achievements and rewards into a single structure definition. For future expansion, the proposal is to decouple these into separate data structures, with an achievement having an optional reference to the appropriate reward, if any.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `ACHV` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | Content | `Achievement[...]` | Achievement list |


**Achievement Record structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| 0 | Name | `StrOffs` | Offset into String Heap for the achievement name |
| 4 | Rule Type ID | `uint16` | Enumerated ID of the rule logic |
| 6 | Reward ID | `uint16` | Specific enumeration of the Reward (if any) |
| 8 | Parameters | `uint8[ 8 ]` | Parameter space for the rule logic |

**Notes:**

- Fixed total size is 16 bytes.
- Rule ID enumerates a specific function that is called to test if the achievement condition is met.
- The interpretation of the parameter space data depends strictly on the rule used.
- Achievements are only tested at runtime when specific signals are set, e.g. a kill, item collect, etc.
- A bitmap of the specific achievements already awarded is recorded in the player progression.

## Level Modification Chunk Types

### PVS Errata

The PVS Errata modifies the PVS structure of the loaded level to fix visibility anomalies. This predates the per-edge visibility solution but is still useful for intractable cases that cannot currently be solved automatically at load time.

The PVS Errata chunk contains a set of Zone ID lists. Each Zone ID list begins with the ID of a Zone interest and is followed by a number of ID values for Zones that will be specifically removed from the Zone of interest's PVS set. The list is terminated with a -1 ID and the set of lists terminated with a final -1.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `PVSE` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | Content | `int16[...]` | List data |

**Notes:**

- This chunk is optional.
- When present, the data it contains must be processed before the per-edge PVS solution runs.

### Backdrop Errata

The Backdrop Errata chunk contains data that modifies the backdrop properties of the Zone structures in the level. The level editor makes a build-time decision about which Zones should render the sky backdrop, in order to ensure that any exposed areas of sky are filled correctly. This nominally applies to:

- Zones with open ceilings
- Zones with closed ceilings that have open-ceiling zones in their PVS set.

The editor does not always make an ideal determination for the latter case, meaning that there can be zones that completely occlude the sky but are instructed to render it anyway.

The data shall be a simple list of Zone ID that should have their backdrop flag cleared after the level has been loaded. The list shall be terminated with a -1 ID entry.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `BKDE` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | Content | `int16[...]` | List data |

**Notes:**

- This chunk is optional.

### Zone Messages

The Zone Messages chunk contains a list of Zone ID that have specific messages attached to them when the player enters the Zone for the first time. This is intended to replace the current invisible object collection behaviour and allow for greater flexibility in narrative expansion.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `ZMSG` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | List | `{ int16, uint16, StrOffs }[...]` | Zone ID, Message Attributes, Heap Offset |

**Notes:**

- This chunk is optional.
- Each record contains the Zone ID, Attributes and offset in the String Heap chunk to the message text.
    - After loading, each offset is converted to the appropriate in-memory address.

- The Attributes word is based on the current in game messaging format, which reserves the uppermost two bits for the message label and the remainder as the overall length of the string.
    - Although string rendering will stop at a null byte, the engine knows that strings below a certain length will not require wrapping.
    - Knowing the length ahead of time allows for faster rendering.

### Object Messages

The Object Messages chunk contains a list of Object ID that have specific messages attached to them when the player finds, activates, kills or destroys a particular object. This is intended to replace the current behaviour and allow for greater flexibility in narrative expansion.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `OMSG` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | List | `{ int16, uint16, StrOffs }[...]` | Object ID, Message Attributes, Heap Offset |

**Notes:**

- This chunk is optional.
- To preserve backwards compatibility, the existing object messaging mechanism shall be retained.
    - When an object has an existing legacy text and an entry in the Object Messages chunk, the existing message shall be pushed first.
- Each record contains the Object ID, Attributes and offset in the String Heap chunk to the message text.
    - After loading, each offset is converted to the appropriate in-memory address.

- The Attributes word is based on the current in game messaging format, which reserves the uppermost two bits for the message label and the remainder as the overall length of the string.
    - Although string rendering will stop at a null byte, the engine knows that strings below a certain length will not require wrapping.
    - Knowing the length ahead of time allows for faster rendering.


