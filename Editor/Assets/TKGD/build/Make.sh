#!/usr/bin/sh
./MakeGameProperties -b ../Source -f redux_high.rson -g ../../../../Game
./MakeGameProperties -b ../Source -f redux_low.rson -g ../../../../Game
./MakeLevelProperties -b ../Source/ -f level_A_high.rson -g ../../../../Game
./MakeLevelProperties -b ../Source/ -f level_B.rson -g ../../../../Game
./MakeLevelProperties -b ../Source/ -f level_H.rson -g ../../../../Game
