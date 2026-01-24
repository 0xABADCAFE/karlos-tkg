# Modification Source Format

This document describes the text format used to define the behavioural modification asset files used by the TKG engine.

## Basic Syntax

In order to provide a convenient, human-editable and structured way of representing the game modification data, the syntax used is based on JSON with the following modifications / relaxations:

- Syntax differentiates between _identifier_ names and _key_ names:
    - An _identifier_ is a structural member of some data type.
    - A _key_ is a string that is mapped to some other value.

- Identifier names may only contain letters, digits and underscore characters.
    - String enclosure quotes are optional.
    - Identifier names must be placed on their own line.

- Key names may contain any valid characters.
    - String enclosure quotes are mandatory.
    - Multiple, comma separated "key": value pairs can be placed on the same line but is discouraged.

- Supports line comments beginning with `//`.
- Permits a trailing comma after the final element of an array or tuple.

**Example:**

```
{
    // A tuple of name:quantity pairs, with a trailing comma.
    FruitBowl: {
        "Oranges": 5,
        "Apples": 3,
    },
}
```

## Import Support

Each document can contain a root-level `Import` node, which is used to import definitions from another file to help avoid duplication and support single-point-of-definition. The `Import` node contains a key/value list of identifier/path pairs. On parsing, the file indicated by the path is loaded and parsed. The parsed content of that file is assigned to the corresponding identifier within the `Import` node:

**Example:**

Before parsing:

`main.rson`

```
{
    Import: {
        Fruit: "common/fruit.rson",
    },
}
```

`common/fruit.rson`

```
{
    // Enumerated fruit
    "Apple": 0,
    "Banana": 1,
    "Pear": 2,
    "Orange": 3,
}
```

After parsing:

```
{
    Import: {
        Fruit: {
            "Apple": 0,
            "Banana": 1,
            "Pear": 2,
            "Orange": 3,
        },
    },
}
```

The process is applied recursively so that files which are imported may contain their own import definitions.

- An `Import` node can only appear in the root level of the document structure if it is to be processed as an import.
- A file can import some other file multiple times when the contents are assigned to different keys.
- Imports should not be self-referential or result in circular inclusion.

## Document Header

The main document file includes a `Header` node. This specifies what type of modification data to expect, an optional description, the version of the file and the version of the engine required to load it successfully.

**Example:**

```
{
    Header: {
        Type:"<file type>",
        Description: "<optional description>",
        Version: "<major>.<minor>",
        Requires: "<major>.<minor>",
    },

}
```

At the time of writing, the following are supported for the `Type` field:

- `Game` Main game modification definitions.
- `Level` Level modification definitons.

The `Version` field components are expected to be in the range 0 - 65535.

## LinkDefs Import

Since the Game Modification files are an optional modding extension to the original tooling, it is necessary to redefine several key data classes so that the modification files are aligned with the original `test.lnk` data, for example:

- Alien Names
- Weapon Names
- Ammunition Names

These definitions allow the various types to be referred to by name, rather than numerical values. To ensure a single point of definition, these are defined in a common import file:

`common/linkdefs.rson`

```
{
    AlienTypes: {
        // The game link file defines up 20 alien types, enumerated 0-19.
        // This node defines names to each type that are then used in the rest of the file.
        "<name>": <id>,
    },
    PlayerAmmoTypes: {
        // The game link file defines up to 20 ammunition types, enumerated 0-19. These are
        // shared between aliens and the player and any 10 of these are assignable to
        // the weapons used by the player. This node defines names for those used by player
        // weapons.
        // It is worth noting that a pickup can award any amount of any of the 20 ammunition
        // types.
        "<name>": <id>,
    },
    SpecialAmmoTypes: {
        // Since the Player can not use the other 10 ammunition types directly and a pickup
        // can give any of the 20 defined types, we can repurpose the other types for special
        // collectables.
        "<name>": <id>,
    },
    // Other lookups
}
```
This file is imported into a modification file using the following standard `Import` node definition:

```
{
    Import: {
        LinkDefs: "common/linkdefs.rson",
    },
}
```

# Game Modification File

The main game modification file lays out various game-wide rules that modify game behaviour. As a primary asset, this must include the correct header and must import the `LinkDefs` node.

**Example:**

```
{
    Header: {
        Type: "Game",
        Description: "Example Game Modification",
        Version: "1.0",
        Requires: "1.13",
    },
    Import: {
        LinkDefs: "common/linkdefs.rson",
    },

    // Remaining definitions
}
```

## DefaultInventoryLimits

The `DefaultInventoryLimits` node sets the initial limits for player comsumables and ammunition when starting a new game:

**Example:**

```
    DefaultInventoryLimits: {
        MaxHealth: <count>,
        MaxJetpackFuel: <count>,
        MaxAmmo: {
            // Initial limits for each of the Import->LinkDefs->PlayerAmmoTypes
            // and Import->LinkDefs->SpecialAmmoTypes
            "<name>": <count>,
        },
    },

```

The initial limits defined here can be raised via rewards for completing objectives or finding special bonus items. The actual limits are saved in the player progress data when exiting the game.

**Notes:**

- If the `MaxHealth` limit is ommitted, the internal default value of 32767 is used.
- If the `MaxFuel` limit is ommitted, the internal default of 255 is used.
- If no limit is defined for any ammo type, the internal default value of 32767 is used.

## Reward

The `Reward` node defines a set of inventory modifications that can be applied as a bonus for completing certain objectives, achievements or collecting specific items.

- Reward nodes are defined within the context of larger structures, e.g. achievements.
- Reward nodes may contain both immediate and carry limit bonuses.
- If a carry limit bonus and an immediate bonus are included for the same inventory item, the carry bonus is applied first.

**Example:**

```
    {
        Description: "<text>",
        Immediate: {
            // Immediate bonuses (if any)
            AddHealth: <count>,
            AddJetpackFuel: <count>,
            AddAmmo: {
                "<name>": <count>,
            },
        },
        CarryLimit: {
            // Carry limit bonuses (if any)
            AddHealth: <count>,
            AddJetpackFuel: <count>,
            AddAmmo: {
                "<name>": <count>,
            }
        }
    }
```

All fields are optional except for the `Description` and at least one immediate or carry limit modification.

## SpecialAmmoBonuses

The optional `SpecialAmmoBonuses` node defines a set of `Reward` definitions that pertain to the collection of items that give any of the ammunition types enumerated in the `SpecialAmmoTypes` node imported from `LinkDefs`. This allows for the definition of one-off collectable objects in game, that can give the special ammo type on collection, triggering the associated Reward as a consequence.

**Asset Structure:**

The Asset structure is a simple list of Special Ammo Name => Reward data

```
    SpecialAmmoBonuses: {
        "<Special Ammo Type>": {
            <Reward Definition>
        },
    }

```

# Level Modification File

The level modification file lays out various level-specific modifications that apply to a specific level. As a primary asset, this must include the correct header and must import the LinkDefs node.

**Example:**

```
{
    Header: {
        Type: "Level",
        Description: "New Level A",
        Version: "1.0",
        Requires: "1.13",
    },
    Import: {
        LinkDefs: "common/linkdefs.rson",
    },

    // Remaining definitions
}
```
