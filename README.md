# GladiatusBattleSimulator
A Gladiatus battle simulator
This library uses the [GladiatusPlayerStatsAPI](https://github.com/DinoDevs/GladiatusPlayerStatsAPI) library to retrieve players' data.

## Usage

Simulating arena battles
```php
$results = arena_simulator(
	// Attacker player's info
	array(
		'country' => 'gr',
		'server' => '4',
		'name' => 'darkthanos',
		'id' => null
	),

	// Defender player's info
	array(
		'country' => 'gr',
		'server' => '4',
		'name' => 'greatapo',
		'id' => null
	),

	// Simulation parameters
	array(
		'life-mode' => 'current', // current / full / unlimited
		'simulates' => '10000' // do 10000 battles and return results
	),
);
```

Simulating turma battles
```php
$results = turma_arena_simulator(
	// attacker player's info
	array(
		'country' => 'gr',
		'server' => '4',
		'name' => 'darkthanos',
		'id' => null
	),

	// Defender player's info
	array(
		'country' => 'gr',
		'server' => '4',
		'name' => 'greatapo',
		'id' => null
	),

	// Simulation parameters
	array(
		'simulates' => '500' // do 500 battles and return results
	)
);
```

Note: The player `name` is case sensitive. One can use an id instead.

## Turma

Turma simulation is approximate since we are aware of the exact formular used. Here is what we have uncovered and how the simulator currently works:
```
Each fighter has a threat based on which he attracts hits (chance to be hit = threat / total threat from all players). A fighter's threat is increased with each attack/heal he does.

Every time a fighter hits another fighter, his threat is increased by: (if he is a healer, his initial threat is 0 no matter the item he wears)
2 * initial threat + 2 * damage

When a healer heals, his threat is increased by:
1 * heal done

For fighters in defense stance, the above formulas are exact. For fighters in attack/heal stance these formular slightly deviate for yet unknown reasons. If you want to research how turma works, use GCA's integrated dungeon/turma reports analyser.
```
