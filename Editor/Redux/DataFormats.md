# Modification Data Formats (V2 provisional)

The following document describes a proposed data format for game modification files. At the time of writing there are two files that this format intends to replace:

- AB3:Includes/game.props
    - Contains current inventory limits and achievements.
- AB3:Levels/LEVEL_x/errata.dat
    - Contains PVS fixes/optimisations that cannot be solved by the current runtime validator.

The goal of this document is to define a format that can replace both of these domain-specific resources and allow trivial addition of new data sections without requiring further changes.

The data structures will require a corresponding rewrite of the logic that loads and parses them.

## Required features

- Trivial to load, ideally as a single in memory allocation.
- Ideally compressable using the existing SB format utilities.
- In-place data conversion, e.g. offsets to pointers.
- Version checked.

## Prior Art

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

The key problem with each format is that they are intended for completely general-purpose use, incremental loading and other features that are basically overkill. It's also not clear that they can work in conjunction with the compression mechanisms used by the game.
        
## Proposed structure

While IFF and XSF are overkill, the container/chunk concept is ideal and we can take cues from it.

### Header

The file header should contain a simple format and version indication.

- Format indicator shall be a simple 4-byte value that indicates this file contains game data:
    - `TKGD`

- Subformat indicator shall be a simple 4-byte value that indicates the specific content of the file. At the time of writing we require a global format for game modification and a per-level format for level modifications:

    - `GMOD`: Identifies the global game modification file.   
    - `LMOD`: Identifies a per-level modification file.

- Version
    - Basic version data of the file.
- Version Required
    - Defines the minimum version of the engine that the data format will work with. This should be composed of a major and minor component.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| 0 | Format | `char[4]` | `TKGD` |
| 4 | Subformat | `char[4]` | `GMOD`, `LMOD`, etc. |
| 8 | Requires | `uint16[2]` | Major:Minor minimum engine version required to load and process the file. |
| 12 | Version | `uint16[2]` | Major:Minor version of the file itself. |

All values that are larger than a byte will be stored in Big Endian byte order.

### Chunks

Everything following the header shall be a chunk. A chunk should forst indicate the data format length, followed by the data itself. Where the data are not aligned to 32 bits, zero padding will be appended:

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | Purpose-specific identifier for the chunk data |
| Base + 4 | Length | `uint32` | Total length of the data, including any padding |
| Base + 8 | Content | Varying | Chunk Data, pad |

**Notes:**
 - The Length field always includes the header.
     - Due to alignment requirements, the length field should always be a multiple of 4.
     - The mininum possible length is 8 bytes, i.e. just the header data.
 - Chunks can be individually loaded to different locations or the entire file can be loaded as a single allocation.

## Common Chunk Types

The following chunk types are common to each defined data file and can only be included once per file:

### Index

The Index chunk must immediately follow the file header in any file that contains it. The Index chunk shall contain a list of 32-bit offsets, each measured from the start of the file to each Chunk present in the file.

**Proposed structure:**

| Offset | Data | Type | Notes |
| - | - | - | - |
| Base + 0 | Ident | `char[4]` | `INDX` |
| Base + 4 | Length | `uint32` | Total size of the chunk |
| Base + 8 | Index | `uint32[...]` | List of offsets |

**Notes:**

- Index chunk does not index itself.
- Chunk index entries are measured from the beginning of the file data.
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

Other chunks that contain string references shall store a 32-bit offset into the String Heap chunk content:

- The offset to the string is measured from the beginning of the String Heap chunk:
    - The minimum legal 32-bit offset is 8.
    - (Low Level): On parsing the chunk, the string offset is converted into an appropriate address by adding the the address at which the String Heap is located in memory.
- An offset value of 0 is zero meaning a null refence.
- An offset value between 1 and 7 is considered error.

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
| 0 | Name | `uint32` | Offset into String Heap for the achievement name |
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
| 0 | Description | `uint32` | Offset into String Heap for the description |
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
| Base + 8 | List | `{ int16, uint16, uint32 }[...]` | Zone ID, Message Attributes, Heap Offset |

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
| Base + 8 | List | `{ int16, uint16, uint32 }[...]` | Object ID, Message Attributes, Heap Offset |

**Notes:**

- This chunk is optional.
- To preserve backwards compatibility, the existing object messaging mechanism shall be retained.
    - When an object has an existing legacy text and an entry in the Object Messages chunk, the existing message shall be pushed first.
- Each record contains the Object ID, Attributes and offset in the String Heap chunk to the message text.
    - After loading, each offset is converted to the appropriate in-memory address.
 
- The Attributes word is based on the current in game messaging format, which reserves the uppermost two bits for the message label and the remainder as the overall length of the string.
    - Although string rendering will stop at a null byte, the engine knows that strings below a certain length will not require wrapping.
    - Knowing the length ahead of time allows for faster rendering.


