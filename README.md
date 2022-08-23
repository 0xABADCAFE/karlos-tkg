# karlos-tkg
Assets for an old mod made in around 1997/1998 for Alien Breed 3D 2, a game released for the Commodore Amiga 1200 and 4000 in 1996. The original version of the game required 4MB of Fast RAM. This mod likely requires more, but not more than 16MB.

## How to use this
In order to use this, you'll need to check the repository out into a location that's accessible from a real Amiga (or UAE) if you want to actually run the mod or edit any of it.

### Play the game
To run the game, you will need to install an engine. Either use your original tkg binary (in which case, tkgpatch is strongly advised), or use the latest stable build from https://github.com/mheyer32/alienbreed3d2

Drop your engine binary into the Game directory and make sure the KarlosTKG script is updated to invoke it after making the necessary assigns.

### Edit the game
The Editor directory contains the originally distributed editor tools for the game. These were released (along with their sources). However, the editors are generally written in AMOS and are not super friendly. It is recommended to install a copy of LevelEd 303 from http://aminet.net/package/game/edit/AB3Dedit

Run the Setup script which will perform the necessary assigns. This assumes that the Editor and Game directories remain together.

Assets are generally in IFF formats suitable for use in DPaint and PPaint. 

### Notes
The mod is unfinished. The original goals were:
- Retexture the original game to add more variation.
- Modify levels by adding new areas, secrets or changing existing areas either to improve the appearance or modify difficulty.
- Add new enemies, pickups and weapons.
- Improve sound effects. Music turned out to be a real challenge given the limitations of mod playback in the game.

In addition, it was also planned to make the game episodic. The original levels would become Episode 2, and Episode 1 would recreate the original Alien Breed 3D levels.
An entirely separate set of 16 deathmatch levels were planned. There are scripts in the Editor/Scripts directory that handle switching the editor and game between these episodes (episode 1 was never started). I haven't retested that these work and in any case running them successfully will make large scale changes to the repository tree.
