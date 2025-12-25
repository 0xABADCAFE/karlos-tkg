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

A JSON-based text format is used for the source assets from which the binary files are compiled. The source asset is intended to be human editable in a basic text editor rather than machine generated. Consequently the following relaxations of the JSON notation are supported:

 - Line comments beginning with `//` are supported.
 - Lists and arrays may include a trailing comma.

** Example **

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

A type name may also refer to a named structure definiton. In addition there are two special purpose aliases of the uint32 scalar:

- ChkOffs is a 32-bit offset value that measures the distance from the beginning of the file to the beginning of a Chunk. Conceptually this can be thought of as a union:

```
    union ChkOffs {
        // In file
        uint32 fileOffset;

        // At runtime
        Chunk const* chunkAddress; // (Chunk const*) ((uint32)baseAddress + fileOffset)
    };
```

- StrOffs is a 32-bit offset value that measures the distance from the beginning of the String Heap Chunk to the first character of a string in the chunk data.

```
    union StrOffs {
        // In file
        uint32 heapOffset;

        // At runtime
        char const* stringAddress; // (char const*) ((uint32)chunkAddress + heapOffset)
    };
```


Each of the above offset types are converted to in-memory addresses after loading by adding their offset value to a base address:

- For ChkOffs values, the address at which the entire file data was loaded is used.
- For StrOffs values, the address at which the String Heap chunk was loaded is used.

All values that are larger than a byte will be stored in Big-Endian byte order.


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


### Chunks

Everything following the header is a Chunk. A Chunk begins with the data format and length, followed by the data itself.

**Asset Structure:**

There is no secific user-defined generalisation for the asset structure, only the data embedded within it, which is type-specific. The sub header is automatically generated based on the final encoded size and type information.


**Binary Structure:**

```
    {
        char[4]    Type;    // 0:
        uint32     Size;    // 4: Total size, including header, content and any padding
        uint8[...] Content; // 8: Content, padding
    }

```

The interpretation of the Content depends on the specific chunk type.


## Common Chunk Types

The following chunk types are common to each defined data file and can only be included once per file:

### Index

The Index chunk must immediately follow the file header in any file that contains it. The Index chunk shall contain a list of 32-bit offsets, each measured from the start of the file to each Chunk present in the file.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `INDX` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | Index | `ChkOffs[...]` | List of offsets |

**Notes:**

- Index chunk does not contain an entry for itself.
- Chunk index offsets are measured from the beginning of the file data to the beginning of the chunk Ident.
- The list count is trivial to derive as ( _chunk size_ / 4) - 2
- The ordering of chunks is not strongly mandated but should follow the conventions expected by the engine target version.
- (Low Level): When a file is loaded in its entirety, the chunk offset values in the list can be converted to their absolute in-memory addresses by adding the address at which the file itself is loaded.

### String Heap

The String Heap chunk gathers together common strings into a single blob of null-terminated values. There is no length indicator or padding per-entry but the blob itself will be padded out to the next 32-bit boundary if necessary.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `STRH` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | Content | `char[...]` | Catenated string data, zero padded at end if necessary |

**Notes:**

- When present in a file, the String Heap should be the first entry in the Index chunk
    - Can be located anwyhere in the file after the Index chunk.
    - Being placed last in the file is generally the most convenient for tooling that creates the file.

Other chunks that contain StrOffs fields are resolved to absolute `char const*` addresses:

- The offset to the string is measured from the beginning of the String Heap chunk:
    - The minimum legal 32-bit offset is 8.
    - (Low Level): On parsing the chunk, the string offset is converted into an appropriate address by adding the the address at which the String Heap is located in memory.
- An offset value of 0 is zero meaning a null refence.
- An offset value between 1 and 7 is considered an error.

## Modification File Chunk Types

### InventoryLimits

This chunk specifies the default game limits for the player inventory.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `INVL` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | Health | `uint16` | Health Limit |
| Base + 10 | Fuel | `uint16` | Fuel Limit |
| Base + 12 | Ammo | `uint16[...]` | Ammo Limit, entry per type (20) |


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

### Achievement Rewards

This chunk defines the reward associated with the achievements that have them.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `RWRD` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | Content | `Reward[...]` | Reward list |

**Proposed Reward structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| 0 | Description | `StrOffs` | Offset into String Heap for the description |
| 4 | Applicator Type ID | `uint16` | Enumerated ID of the application logic |
| 6 | Parameters | `uint8[26]` | Parameter space for the application logic |

**Notes:**

- Fixed total size is 32 bytes.
- The interpretation of the parameter space data depends strictly on the rule used.

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


