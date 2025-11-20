#!/usr/bin/sh
./MakeModProperties -i ../ActiveData/ModProperties/game.props.json -o ../../Game/HighSpec/Includes/game.props
./MakeModProperties -i ../ActiveData/ModProperties/game.props.low.json -o ../../Game/LowSpec/Includes/game.props
