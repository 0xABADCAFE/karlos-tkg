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

## DefaultInventoryLimits

The `DefaultInventoryLimits` node sets the initial limits for player comsumables and ammunition when starting a new game:

**Example:**

```
    DefaultInventoryLimits: {
        MaxHealth: <#count>,
        MaxJetpackFuel: <#count>,
        MaxAmmo: {
            // Initial limits for each of the Import->LinkDefs->PlayerAmmoTypes
            // and Import->LinkDefs->SpecialAmmoTypes, etc.
            "<name>": <#count>,
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
            AddHealth: <#count>,
            AddJetpackFuel: <#count>,
            AddAmmo: {
                "<name>": <#count>,
            },
        },
        CarryLimit: {
            // Carry limit bonuses (if any)
            AddHealth: <#count>,
            AddJetpackFuel: <#count>,
            AddAmmo: {
                "<name>": <#count>,
            }
        }
    }
```

All fields are optional except for the `Description` and at least one immediate or carry limit modification.

## SpecialAmmoBonuses

The optional `SpecialAmmoBonuses` node defines a set of `Reward` definitions that pertain to the collection of items that give any of the ammunition types enumerated in the `SpecialAmmoTypes` node imported from `LinkDefs`. This allows for the definition of one-off collectable objects in game, that can give the special ammo type on collection, triggering the associated Reward as a consequence.

**Example:**

The Asset structure is a simple list of Special Ammo Name => Reward data

```
    SpecialAmmoBonuses: {
        "<Special Ammo Type>": {
            <Reward Definition>
        },
    }

```

## Achievements

The optional `Achievements` node defines an array of achievement defintions that may optionally include a `Reward` definition for completion of the achievement.

**Structure:**

```
    Achievements: [
        {
            Description: "<text>",
            Rule: "<enumerated rule name>",

            // Parameters are key-value pairs that depend on rule type
            Params: {
                "<key>": <value>,
            },

            // Reward is optional, only included for achievements that have bonuses for completion.
            Reward: {
                <Reward definition>
            }
        },
    ]
```

The following rules are defined:

### StuffCollected

The `StuffCollected` rule is checked when the player collects some inventory consumable such as health, fuel or ammunition. This rule defines the following parameters:

```
    Params: {
        Stuff: "<consumable name>",
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
        Rule: "StuffCollected",
        Params: {
            Stuff: "Bullet",
            Count: 800
        },
        Reward: {
            // Carry limit upgrades are always applied first
            Description: "Bullets +40, Carry +40",
            Immediate: {
                AddAmmo: {
                    "Bullet": 40,
                }
            },
            CarryLimit: {
                AddAmmo: {
                    "Bullet": 40,
                }
            }
        }
    }
```

### KillCount

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
            Immediate: {
                AddAmmo: {
                    "Blaster": 40,
                }
            },
            CarryLimit: {
                AddAmmo: {
                    "Blaster": 40,
                }
            }
        }
    }
```

### GroupKillCount

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

### PlayerDied

The `PlayerDied` rule is checked whenever the player dies. This rule defines the following parameters:

```
    Params: {
        LevelMask: <#mask>,
        Count: <#count>,
        Overall: <bool>
    }
```

The game separately tracks the number of times the player died in each level. The `LevelMask` field is a bitmap of the Level Numbers the rule applies to. This allows the definition of specific achievements for dying in a particular level or set of levels.

The `Overall` flag specifies whether or not the required `Count` limit is tested against the death count any single level in the mask or the sum total death count for all of the levels in the mask.

**Example:**

```
    {
        // First time killed, any level."levelMask"
        Description: "Died: 'Tis but a scratch!",
        Rule: "PlayerDied",
        Params: {
            LevelMask: 65535,
            Count: 1,
            Overall: false
        }
    },
```

### TimeImproved

The `TimeImproved` rule is checked whenever the player completes a level. This rule defines the following parameters:

```
    Params: {
        LevelMask: <#mask>,
        Count: <#count>,
        Overall: <bool>
    }
```

The game separately tracks the shortest time the player has completed each level and the number of times it has been improved. The `LevelMask` field is a bitmap of the Level Numbers the rule applies to. This allows the definition of specific achievements for beating a past time in a particular level or set of levels.

The `Overall` flag specifies whether or not the required `Count` limit is tested against the improvement count of any single level in the mask or the sum total improvement count for all of the levels in the mask.

**Example:**

```
    {
        Description: "Again: Action Replay (Beat any previous level time)",
        Rule: "TimeImproved",
        Params: {
            LevelMask: 65535,
            Count: 1,
            Overall: true
        }
    },
```

### ZoneFound

The `ZoneFound` rule is checked whenever the player enters a given Zone for the first time. This rule defines the following parameters:

```
    Params: {
        Level: <#level>,
        Zone: <#zone>
    }
```

**Example:**

```
    {
        Description: "Overflow!",
        Rule: "ZoneFound",
        Params: {
            Level: 1,
            Zone: 256
        }
    }
```
