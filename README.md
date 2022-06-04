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
