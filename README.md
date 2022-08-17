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
