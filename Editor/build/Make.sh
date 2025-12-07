#!/usr/bin/sh
./MakeModProperties -i ../Assets/ModProperties/game.props.json -o ../../Game/HighSpec/Includes/game.props
./MakeModProperties -i ../Assets/ModProperties/game.props.low.json -o ../../Game/LowSpec/Includes/game.props
