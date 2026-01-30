# Game Modification File

[Back](./SourceFormat.md)

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

## Common Types

The following data structures are used in multiple definitions:

### LevelDefs

The `LevelDefs` type is a string literal that is used to specify a set of Levels. The following conventions are used:

- Each distinct level is denoted by a single uppercase character A-P.
    - Level letter codes can occur in any order.
    - Level letter codes must occur only once each.

- Spaces and commas are permitted as separators for readability and are ignored.
- Asterisk is accepted as shorthand for every level.
    - If included, an asterisk must occur only once.
    - When an asterisk is used in conjuction with any letter code, the specific letters are considered as exclusions from the full set.

- Any other character classes are illegal.

**Examples:**

```
    // Below are valid definitions for the set of levels A, C and E:

    "ACE"
    "CEA"    // Order is irrelevent.
    "A C E"  // Spaces are ignored.
    "A,C,E," // Commas are ignored.
    "A CE, " // Any combination of spaces and commas are ignored.

    // Below are valid definitions for all levels:

    "ABCDEFGHIJKLMNOP"                  // Including any permutation, spaces or commas.
    "*"                                 // Preferred

    // Below are a valid examples of all levels except A, C and E:

    "BDFGHIJKLMNOP"              // Including any permutation, spaces or commas.
    "*ACE"                       // Including any permutation. Preferred.

    // Illegal examples:

    "ACEA"                       // Cannot specify a level code twice.
    "ACE1"                       // Illegal character class
    ""                           // Must not be empty.
    "**"                         // Asterisk may occur only once.
```

**Notes:**

- When dealing with the complete set of levels, or all levels excluding some specific subset, the asterisk notion is preferred as it ensures that any future expansion to the set of levels is accounted for.

### SupplyQuantity

The `SupplyQuantity` structure defines an amount of health, fuel and ammunition. These structures are used wherever something modifies with the player inventory.

**Structure:**

```
    {
        // All fields optional but at least one value is required.
        Health: <#count>,
        Fuel: <#count>,
        Ammo: {
            // Any of the LinkDefs enumerated PlayerAmmoTypes
            "<ammo type name>": <#count>,
        }
    }
```

**Notes:**

- Each field is optional, but the structure as a whole should not be empty.
- The interpretation of the values is context-specific.
    - Count values can be negative, the intention is to support incidents that might deplete some player inventory.

- The interpretation of a missing value is context-specific.

### Reward

The `Reward` structure defines a set of inventory modifications that can be applied as a bonus for completing certain objectives, achievements or collecting specific items.

**Structure:**

```
    {
        Description: "<text>",

        // Both the following are optional, but at least one must be present.
        ImmediateAdd: { SupplyQuantity },
        CarryLimitAdd: { SupplyQuantity }
    }
```

**Notes:**

- The `SupplyQuantity` values are added to the existing player totals:
    - `ImmediateAdd` is added to the current carry.
    - `CarryLimitAdd` is added to the current carry capacity.

- If both are included, `CarryLimitAdd` is processed before `ImmediateAdd`.

## Main Node Types

### DefaultInventoryLimits

The `DefaultInventoryLimits` node is a `SupplyQuantity` that sets the initial limits for player comsumables and ammunition when starting a new game:

**Structure:**

```
    DefaultInventoryLimits: { SupplyQuantity }
```

The initial limits defined here can be raised via rewards for completing objectives or finding special bonus items. The actual limits are saved in the player progress data when exiting the game.

**Notes:**

- Where the `SupplyQuantity` does not define a specific limit for some value, the internal default for that type is used:
    - Health: 32767
    - Fuel: 255
    - Any ammuition type: 32767

### SpecialAmmoBonuses

The optional `SpecialAmmoBonuses` node defines a set of `Reward` definitions that pertain to the collection of items that give any of the ammunition types enumerated in the `SpecialAmmoTypes` node imported from `LinkDefs`. This allows for the definition of one-off collectable objects in game, that can give the special ammo type on collection, triggering the associated `Reward` as a consequence.

**Structure:**

```
    SpecialAmmoBonuses: {
        // One per special ammo type
        "<special ammo type name>": { Reward },
    }
```

### WeaponAdjustment (TODO)

The optional `WeaponAdjustment` node defines additional per-weapon behaviours for the player arsenal. These can include:

- Offsets for the on-screen point of origin of visible projectiles when fired.
- Recoil Impulse: Degree to which firing the weapon knocks the player backward.
    - Should apply to heavier weapons only, e.g. Rockets.

- Recoil Spray: Degree to which firing the weapon disturbs the player forwards direction.
    - A random value within +/- the spray is added to the player pitch and yaw.
    - Should apply to automatic weapons, potentially rising to the limit with duration of fire.

- Burst limit: Length of time a weapon can be fired repeatedly before forcing a cooldown.
    - Makes most sense for rapid automatic and plasma weapons.

- Cooldown time: Length of time before a weapon can be fired after a cooldown is triggered.

### Achievements

The optional `Achievements` node defines an array of achievement defintions that may optionally include a `Reward` definition for completion of the achievement.

**Structure:**

```
    Achievements: [
        {
            Description: "<text>",
            Rule: "<enumerated rule name>",

            // Parameters are key-value pairs that depend on Rule type
            Params: {
                "<key>": <value>,
            },

            // Reward is optional, only included for achievements that have bonuses for completion.
            Reward: { Reward }
        },
    ]
```

The following rules are defined:

#### Achievement Rule: Collected

The `Collected` rule is checked when the player collects some inventory consumable such as health, fuel or ammunition. This rule defines the following parameters:

```
    Params: {
        Type: "<consumable name>",
        Count: <#count>
    }
```

Valid values for the consumable name are any of the `LinkDefs` enumerated `PlayerAmmoTypes`, `SpecialAmmoTypes`, `Health` and `Fuel`.

**Example:**

```
    {
        // Triggered once the player has collected at least 800 bullets
        // increase the carry limit by 40.

        Description: "Items: Top Brass (800/800)",
        Rule: "Collected",
        Params: {
            Type: "Bullet",
            Count: 800
        },
        Reward: {
            // Carry limit upgrades are always applied first
            Description: "Bullets +40, Carry +40",
            ImmediateAdd: {
                Ammo: {
                    "Bullet": 40,
                }
            },
            CarryLimitAdd: {
                Ammo: {
                    "Bullet": 40,
                }
            }
        }
    }
```

#### Achievement Rule: KillCount

The `KillCount` rule is checked whenever an alien is killed by the player. This rule defines the following parameters:

```
    Params: {
        Alien: "<alien name>",
        Count: <#count>
    }
```

Valid values for the alien name are any of the `LinkDefs` enumerated `AlienTypes`. There are no restrictions to the number of `KillCount` achievements for a specific alien type.

**Example:**

```
    {
        Description: "Kills: Endangered Species (Pest control 100/200)",
        Rule: "KillCount",
        Params: {
            Alien: "Beast",
            Count: 100
        },
        Reward: {
            Description: "Blaster +40, Carry +40",
            ImmediateAdd: {
                Ammo: {
                    "Blaster": 40,
                }
            },
            CarryLimitAdd: {
                Ammo: {
                    "Blaster": 40,
                }
            }
        }
    }
```

#### Achievement Rule: GroupKillCount

The `GroupKillCount` rule is checked whenever an alien is killed by the player. This rule defines the following parameters:

```
    Params: {
        Aliens: [
            // Multiple entries
            "<alien name>",
        ],
        Count: <#count>
    }
```

Valid values for the alien name are any of the `LinkDefs` enumerated `AlienTypes`. There are no restrictions to the number of `GroupKillCount` achievements for a specific alien type.

Killing any of the specified aliens counts towards the achievement.

**Example:**

```
    {
        Description: "Kills: Red's Dead, Baby (Other red things, 25/50)",
        Rule: "GroupKillCount",
        Params: {
            Aliens: [
                "ShotgunGuard",
                "RedDemon",
                "InsectBoss"
            ],
            Count: 25
        }
        // No particular reward for this one.
    },
```

#### Achievement Rule: PlayerDied

The `PlayerDied` rule is checked whenever the player dies. This rule defines the following parameters:

```
    Params: {
        Levels: "<LevelDefs>",
        Count: <#count>,
        Overall: <bool>
    }
```

The game separately tracks the number of times the player died in each level. The `Levels` field is a `LevelDefs` string specifies which levels the rule applies to. This allows the definition of specific achievements for dying in a particular level or set of levels.

The `Overall` flag specifies whether or not the required `Count` limit is tested against the death count any single level in the set or the sum total death count for all of the levels in the set.

**Example:**

```
    {
        // First time killed, any level.
        Description: "Died: 'Tis but a scratch!",
        Rule: "PlayerDied",
        Params: {
            Levels: "*",
            Count: 1,
            Overall: false
        }
        // No reward defined here, just shame :)
    },
```

#### Achievement Rule: TimeImproved

The `TimeImproved` rule is checked whenever the player completes a level. This rule defines the following parameters:

```
    Params: {
        Levels: "<LevelDefs>",
        Count: <#count>,
        Overall: <bool>
    }
```

The game separately tracks the shortest time the player has completed each level and the number of times it has been improved. This allows the definition of specific achievements for beating a past time in a particular level or set of levels.

The `Overall` flag specifies whether or not the required `Count` limit is tested against the improvement count of any single level in the mask or the sum total improvement count for all of the levels in the mask.

**Example:**

```
    {
        Description: "Again: Action Replay (Beat any previous level time)",
        Rule: "TimeImproved",
        Params: {
            Levels: "*",
            Count: 1,
            Overall: true
        }
    },
```

#### Achievement Rule: ZoneFound

The `ZoneFound` rule is checked whenever the player enters a given Zone in a particular Level for the first time. This rule defines the following parameters:

```
    Params: {
        Level: "<level code>",
        Zone: <#zone>
    }
```

The Level field refers to a single, specific level and must contain only a single letter A-P.

**Example:**

```
    {
        // Yeah good luck triggering this one.

        Description: "Overflow!",
        Rule: "ZoneFound",
        Params: {
            Level: "A",
            Zone: 256
        }
    }
```
