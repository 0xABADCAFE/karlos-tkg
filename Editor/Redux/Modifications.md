# Modification Ideas

## Weapon behaviour modifications

### Encumbrance

Larger items should impose some restrictions on player movement when equipped:

- Allows Run
    - When enabled, player can still run while the item is equipped.
    - When disabled, player is restricted to walking.
    - Additional restrictions on speed in either mode.

- Allows Jump
    - When enabled, the player can still jump while the item is equipped.
    - Jetpack can be used for powered jump.
    
- Allows Crouch
    - When enabled, player can still crouch when the item is equipped.
    - When disabled, the crouching and equipping are mutually exclusive, e.g. if the player is already crouched, the item can not be equipped. Equally, if the player is already standing with the item equipped, crouching is impossible.

Achievements could be defined that reduce the penalty effects of encumrance.

- Increased strength, stamina


### Recoil

Impulse forces that affect player aim and velocity when firing:

- Knockback
    - Degree to which a shot pushes the player in the opposite direction. This should be reserved for heavier items.
    - Should be slight but be enough make narrow gantries etc. precarious places to launch rockets or grenades from.

- Spray
    - Degree to which a shot alters the player aim direction.
    - A single shot should have no real effect while continuous firing should result in an increasing level of random judder up to some per-weapon defined limit. 
    - This could be implemented via a cooldown counter is incremented for every shot fired while decrementing back to zero each game tick.
       - There would be no effect on the aim when the counter is zero and would increase some to some limit.
       - Each spray parameter should be per-weapon definable. 
       
Achievements could be be defined that reduce the penalty affect of recoil.

- Increased steadiness.


## Inventory Modifications

The specific selection of weapons could be restricted such that it's not possible to carry all of them at once.

- Only allow one heavy weapon to be carried at once, forcing the player to make tactical choices, e.g. rocket launcher, minigun, grenade launcher.
- Similar for assault weapons, e.g. plasma cannon or assault rifle.
- Allow the player to drop/swap, e.g. switch the carried weapon with an alternative of the same restriction class.

The specific mutually-excusive carry options should be definable. Note that some weapons have more than one mode and this should  be taken into consideration.

An achievement that allows the player to carry two items of a particular class might be beneficial for later in the game.


## Level Modifications

### Narrative Expansion

Allow messages to be attached to specific Zones, such that entering the zone for the first time displays the message. This removes the need to use objects for this purpose and allows for clues, hints etc.

### Water Modifications

- Introduce drowning and swimming mechanics.
- Event based water level modification. An event could allow the water level in some set of zones to be changed, giving the player the ability to flood or drain parts of a level.
    - Requires the ability to specificy the list of zones that define a floodable area.
    - Requires fill and drain rates
    - Trigger mechanisms, e.g. activation of a specific object in the level. 