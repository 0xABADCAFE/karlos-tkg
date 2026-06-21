#!/usr/bin/sh
./builder/MakeGameProperties -b  Source -f redux_high.rson -g ../../../Game
./builder/MakeGameProperties -b  Source -f redux_low.rson -g ../../../Game
./builder/MakeLevelProperties -b Source/ -f level_A_high.rson -g ../../../Game
./builder/MakeLevelProperties -b Source/ -f level_B.rson -g ../../../Game
./builder/MakeLevelProperties -b Source/ -f level_H.rson -g ../../../Game
