<?php
/*
 * Gladiatus Battle Simulator by Gladiatus Crazy Team
 * https://github.com/DinoDevs
 * https://www.facebook.com/GladiatusCrazyAddOn
 * Authors : GramThanos, GreatApo
 *
 * Gladiatus Data
 * Player Turma Arena Fight Simulate Lib
 */

/*
	Example Use
	
	$results = turma_arena_simulator(
		array(
			'country' => 'gr',
			'server' => '4',
			'name' => 'darkthanos', // case insensitive
			'id' => null
		),
		array(
			'country' => 'gr',
			'server' => '4',
			'name' => 'greatapo', // case insensitive
			'id' => null
		),
		array(
			'simulates' => '100', // [1-10000] default 500
		)
	);
*/

	/*
		Library
	*/
		// Load request player data library
		// https://github.com/DinoDevs/GladiatusPlayerStatsAPI
		require_once('request_playerData.php');
	
	/*
		Functions
	*/
		// Players
		function turma_arena_simulator_players($data) {
			$players = array();

			// For each player
			foreach ($data['players'] as $player){
				// Ignore left behind players
				if ($player['role']['isRoleOut']) {
					continue;
				}
				// Treat unknown players as dps
				if ($player['role']['isRoleUnknown']) {
					$player['role']['isRoleDps'] = true;
				}
				// Add on array
				array_push($players, $player);
			}

			return $players;
		}

		// Types Parse
		function turma_arena_simulator_types($players) {
			// Parse each player
			foreach ($players as &$player){

				// Tank Player
				if($player['role']['isRoleTank']){
					//$player['threat'] = 10 * $player['threat'];
					$player['isRoleOf'] = 'Tank';
				}
				// Healer Player
				else if($player['role']['isRoleHealer']){
					$player['isRoleOf'] = 'Healer';
					$player['threat'] = 0;
				}
				// Dps Player
				//else if($player['role']['isRoleDps']){
				else {
					$player['isRoleOf'] = 'DPS';
				}

				// Threat must be positive
				if($player['threat'] < 0){
					$player['threat'] = 0;
				}
			}

			// Return Player List
			return $players;
		}

		// Calculate Chances
		function turma_arena_simulator_calculate_chances($playersA, $playersB) {
			// For each player
			foreach ($playersA as $j => &$playerA) {

				// Level Factor
				$levelFactor = $playerA['level'] - 8; // This had problem on players < 8 LvL
				if($levelFactor<2)
					$levelFactor = 2;

				// Calculate Avoid Critical Chance Percent
				$playerA['avoid-critical-chance'] = round($playerA['avoid-critical-points'] * 52 / $levelFactor / 4);
				if ($playerA['avoid-critical-chance'] > 25) $playerA['avoid-critical-chance'] = 25;

				// Calculate Block Chance Percent
				//$playerA['block-chance'] = round($playerA['block-points'] * 52 / $levelFactor / 6);
				//if ($playerA['block-chance'] > 50) $playerA['block-chance'] = 50;

				// Calculate Critical Chance Percent
				$playerA['critical-chance'] = round($playerA['critical-points'] * 52 / $levelFactor / 5);

				// Calculate armor absorve
				$playerA['armor-absorve'] = array(
					floor($playerA['armor'] / 66) - floor(($playerA['armor'] - 66) / 660 + 1),
					floor($playerA['armor'] / 66) + floor($playerA['armor'] / 660)
				);

				$playerA['hit-chance'] = array();
				$playerA['double-hit-chance'] = array();
				$playerA['block-chance'] = array();

				// Block value will later change based on level difference
				$block_chance_static = round($playerA['block-points'] * 52 / $levelFactor / 6);

				// Enemy specific variables
				foreach ($playersB as $i => $playerB) {
					// Calculate hit chance
					$playerA['hit-chance'][$i] = floor($playerA['skill'] / ($playerA['skill'] + $playerB['agility']) * 100);
					
					// Calculate double hit chance
					$playerA['double-hit-chance'][$i] = $playerA['charisma'] - $playerB['charisma'];
					if ($playerA['double-hit-chance'][$i] < 0) {
						$playerA['double-hit-chance'][$i] = 0;
					} else if($playerA['double-hit-chance'] > 100) {
						$playerA['double-hit-chance'][$i] = 100;
					}

					// Calculate Block Chance Percent
					$playerA['block-chance'][$i] = $block_chance_static + max(0, $playerA['level'] - $playerB['level']) * 2;
					if ($playerA['block-chance'][$i] > 50) $playerA['block-chance'][$i] = 50;
				}
			}

			// Return Player List
			return $playersA;
		}
		
		// Simulate a hit
		function turma_arena_simulator_hit_simulation($playerA, $playerB) {
			// Single hit
			if (rand(0, 100) <= $playerA['hit-chance'][$playerB['index']]) {
				// Successful hit
				if (rand(0, 100) > $playerB['block-chance'][$playerA['index']]) {
					// Critical hit
					if (rand(0, 100) <= $playerA['critical-chance']) {
						// Successful
						if (rand(0, 100) > $playerB['avoid-critical-chance']) {
							$hit = array('Critical', 2 * rand($playerA['damage'][0], $playerA['damage'][1]) - rand($playerB['armor-absorve'][0], $playerB['armor-absorve'][1]) );
							if ($hit[1] < 0) $hit[1] = 0;
						// Avoided
						} else {
							// Avoided
							$hit = array('Avoided Critical', rand($playerA['damage'][0], $playerA['damage'][1]) - rand($playerB['armor-absorve'][0], $playerB['armor-absorve'][1]) );
							if ($hit[1] < 0) $hit[1] = 0;
						}
					// Normal hit
					} else {
						// Normal Hit Successfull
						$hit = array('Normal', rand($playerA['damage'][0], $playerA['damage'][1]) - rand($playerB['armor-absorve'][0], $playerB['armor-absorve'][1]) );
						if ($hit[1] < 0) $hit[1] = 0;
					}
				// Blocked
				} else {
					// Blocked
					$hit = array('Blocked', 0);
				}
			// Miss
			} else {
				// Miss
				$hit = array('Miss', 0);
			}
			
			// Give back the hit value
			return $hit;
		}

		// Simulate a heal
		function turma_arena_simulator_heal_simulation($player) {
			// Player's healing power
			$healing = $player['healing'];

			// Critical heal
			if(rand(0, 100) <= $player['critical-healing']){
				// Double heal
				$healing = 2 * $healing;
				return array('Critical', $healing);
			}
			// Normal heal
			else{
				return array('Normal', $healing);
			}
		}

	/*
		Arena Simulator Main Functions
	*/
		// Simulate a Battle
		function turma_arena_simulator_battle($attackers_stats, $defenders_stats, $battle_rounds = 50) {

			// Attackers Prepare
			foreach ($attackers_stats as $i => &$attacker_stats) {
				// Set full life points
				$attacker_stats['life'][0] = $attacker_stats['life'][1];
				// Save Index
				$attacker_stats['index'] = $i;
				// Initiate score
				$attacker_stats['score'] = array(
					'damage-done' => 0,
					'damage-taken' => 0,
					'healing-done' => 0,
					'healing-taken' => 0
				);
				$attacker_stats['last'] = array(
					'threat' => 0,
					'heal' => 0,
					'damage' => 0
				);
				// Set alive
				$attacker_stats['isAlive'] = true;
			}

			// Defenders Prepare
			foreach ($defenders_stats as $i => &$defender_stats) {
				// Set full life points
				$defender_stats['life'][0] = $defender_stats['life'][1];
				// Save Index
				$defender_stats['index'] = $i;
				// Initiate score
				$defender_stats['score'] = array(
					'damage-done' => 0,
					'damage-taken' => 0,
					'healing-done' => 0,
					'healing-taken' => 0
				);
				$defender_stats['last'] = array(
					'threat' => 0,
					'heal' => 0,
					'damage' => 0
				);
				// Set alive
				$defender_stats['isAlive'] = true;
			}

			//#DEBUG
			//echo "<table>\n";
			//echo "<tr><th>Battle Report</th><th></th></tr>\n";

			// Simulate Battle Rounds
			$rounds = 0;
			do {
				//#DEBUG
				//echo "<tr><th>Round ".($rounds+1)."</th><th></th></tr>\n";

				// Run a round
				$results = turma_arena_simulator_round($attackers_stats, $defenders_stats);
				$rounds++;

				// Set new data
				$attackers_stats = $results[0];
				$defenders_stats = $results[1];

				/*
				echo "<br><table><tr><th>A</th><th>Name</th><th>Damage Done</th><th>Damage Taken</th><th>Healing Done</th><th>Healing Taken</th><th>Life</th></tr>";
				foreach($attackers_stats as $player){
					echo "<tr><td>[".$player['index']."]</td><td>".$player['name']."</td><td>".$player['score']['damage-done']."</td><td>".$player['score']['damage-taken']."</td><td>".$player['score']['healing-done']."</td><td>".$player['score']['healing-taken']."</td><td>".round(($player['life'][0]/$player['life'][1])*100, 2)." %</td></tr>";
				}
				echo "<tr><th>D</th><th>Name</th><th>Damage Done</th><th>Damage Taken</th><th>Healing Done</th><th>Healing Taken</th><th>Threat</th><th>Life</th></tr>";
				foreach($defenders_stats as $player){
					echo "<tr><td>[".$player['index']."]</td><td>".$player['name']."</td><td>".$player['score']['damage-done']."</td><td>".$player['score']['damage-taken']."</td><td>".$player['score']['healing-done']."</td><td>".$player['score']['healing-taken']."</td><td>".round(($player['life'][0]/$player['life'][1])*100, 2)." %</td></tr>";
				}
				echo "</table><br><br>";
				*/

				// Stop if rounds complete or one of the teams is dead
			} while ($rounds < $battle_rounds && $results[2] > 0 && $results[3] > 0);

			// #DEBUG
			// echo "</table>\n";
			
			// Score Calculate
			$attackers_score = turma_arena_simulator_get_team_score($attackers_stats, $defenders_stats);
			$defenders_score = turma_arena_simulator_get_team_score($defenders_stats, $attackers_stats);

			/*
			#DEBUG
			echo "<br><br>\n############### RESULTS <br>\n";
			echo "<table><tr><th>A</th><th>Name</th><th>Damage Done</th><th>Damage Taken</th><th>Healing Done</th><th>Healing Taken</th><th>Life</th></tr>";
			foreach($attackers_stats as $player){
				echo "<tr><td>[".$player['index']."]</td><td>".$player['name']."</td><td>".$player['score']['damage-done']."</td><td>".$player['score']['damage-taken']."</td><td>".$player['score']['healing-done']."</td><td>".$player['score']['healing-taken']."</td><td>".round(($player['life'][0]/$player['life'][1])*100, 2)." %</td></tr>";
			}
			echo "<tr><th>D</th><th>Name</th><th>Damage Done</th><th>Damage Taken</th><th>Healing Done</th><th>Healing Taken</th><th>Life</th></tr>";
			foreach($defenders_stats as $player){
				echo "<tr><td>[".$player['index']."]</td><td>".$player['name']."</td><td>".$player['score']['damage-done']."</td><td>".$player['score']['damage-taken']."</td><td>".$player['score']['healing-done']."</td><td>".$player['score']['healing-taken']."</td><td>".round(($player['life'][0]/$player['life'][1])*100, 2)." %</td></tr>";
			}
			echo "</table>";
			
			echo "<br><br><table width=\"400px\"><tr><th>Player</th><th>Points</th></tr>";
			echo "<tr><td>".$attackers_stats[0]['name']."</td><td>".$attackers_score."</td></td></tr>";
			echo "<tr><td>".$defenders_stats[0]['name']."</td><td>".$defenders_score."</td></td></tr>";
			echo "</table><br><br>";

			die(':P Kill it with fire on first simulate !');
			*/

			// echo $attackers_score . ' vs ' . $defenders_score . '<br>';

			// Battle Draw
			if ($attackers_score == $defenders_score) {
				return 0;
			
			// Battle Won
			} else if ($attackers_score > $defenders_score) {
				return 1;

			// Battle Lost
			} else {
				return -1;
			}
		}

		// Simulate Turma Round
		function turma_arena_simulator_round($attackers, $defenders){
			// Initiate attackers' lists
			$alive_attackers = array();
			$active_attackers = array();
			foreach($attackers as $player){
				if($player['life'][0]>0){
					array_push($active_attackers, $player['index']);
					array_push($alive_attackers, $player);
				}
			}
			// Initiate defenders' lists
			$alive_defenders = array();
			$active_defenders = array();
			foreach($defenders as $player){
				if($player['life'][0]>0){
					array_push($active_defenders, $player['index']);
					array_push($alive_defenders, $player);
				}
			}

			// Get players left
			$numberof_attackers = count($active_attackers);
			$numberof_defenders = count($active_defenders);

			do{
				// Random Select player
				$selected = rand(0, $numberof_attackers+$numberof_defenders-1);

				// If attacker was selected
				if($selected < $numberof_attackers){
					// Make player's action
					$results = turma_arena_simulator_player_action($attackers[$active_attackers[$selected]], $alive_attackers, $alive_defenders);
					
					// Update Player
					$attackers[$results[0][0]['index']] = $results[0][0];
					// Delete player from active
					array_splice($active_attackers, $selected, 1);
					//unset($active_attackers[$selected]);

					// If teammates updates
					if(count($results[0]) > 1){
						// Update Player
						$attackers[$results[0][1]['index']] = $results[0][1];
						// Heal threat
						$attackers[$results[0][0]['index']]['last']['threat'] += $attackers[$results[0][0]['index']]['last']['heal'];
					}
					// Opponent updates
					else{
						// Update Player
						$defenders[$results[1][0]['index']] = $results[1][0];
						// Hit threat
						$attackers[$results[0][0]['index']]['last']['threat'] += 2 * ( $attackers[$results[0][0]['index']]['threat'] + $attackers[$results[0][0]['index']]['last']['damage'] );
					}
				}
				// If defender was selected
				else{
					$selected -= $numberof_attackers;
					// Make player's action
					$results = turma_arena_simulator_player_action($defenders[$active_defenders[$selected]], $alive_defenders, $alive_attackers);
					
					// Update Player
					$defenders[$results[0][0]['index']] = $results[0][0];
					// Delete player from active
					array_splice($active_defenders, $selected, 1);
					//unset($active_defenders[$selected]);

					// If teammates updates
					if(count($results[0]) > 1){
						// Update Player
						$defenders[$results[0][1]['index']] = $results[0][1];
						// Heal threat
						$defenders[$results[0][0]['index']]['last']['threat'] += $defenders[$results[0][0]['index']]['last']['heal'];
					}
					// Opponent updates
					else{
						// Update Player
						$attackers[$results[1][0]['index']] = $results[1][0];
						// Hit threat
						$defenders[$results[0][0]['index']]['last']['threat'] += 2 * ( $defenders[$results[0][0]['index']]['threat'] + $defenders[$results[0][0]['index']]['last']['damage'] );
					}
				}

				// Update alive lists and active list
				$alive_attackers = array();
				foreach($attackers as $player){
					// If player alive
					if($player['life'][0] > 0){
						array_push($alive_attackers, $player);
					}
					// If player died
					else if($player['isAlive']){
						$attackers[$player['index']]['isAlive'] = false;
						$key = array_search($player['index'], $active_attackers);
						//echo "<tr><td>++ Dead ++</td><td>".$player['name']."</td></tr>";
						if(false !== $key){
							// Delete player from active
							array_splice($active_attackers, $key, 1);
						}
					}
				}
				$alive_defenders = array();
				foreach($defenders as $player){
					// If player alive
					if($player['life'][0] > 0){
						array_push($alive_defenders, $player);
					}
					// If player died
					else if($player['isAlive']){
						$defenders[$player['index']]['isAlive'] = false;
						//echo "<tr><td>++ Dead ++</td><td>".$player['name']."</td></tr>";
						$key = array_search($player['index'], $active_defenders);
						if(false !== $key){
							// Delete player from active
							array_splice($active_defenders, $key, 1);
						}
					}
				}

				// Get players left
				$numberof_attackers = count($active_attackers);
				$numberof_defenders = count($active_defenders);

			} while($numberof_attackers > 0 && $numberof_defenders > 0);

			// Round Finish
			return array($attackers, $defenders, count($alive_attackers), count($alive_defenders));
		}

		// Simulate Turma Player Action
		function turma_arena_simulator_player_action($player, $teammates, $opponents){
			// If player is healer
			// he must try to heal the most wounded teammate
			if($player['isRoleOf'] == 'Healer'){
				// Get the most wounded teammate
				$wounded = turma_arena_simulator_most_wounded($teammates);
				if($wounded){
					return array(
						turma_arena_simulator_player_action_heal_player($player, $wounded),
						array()
					);
				}
			}

			// Player is going to attack
			// So select a player
			$opponent = turma_arena_simulator_get_random_threatbased_player($opponents);

			if($opponent['index'] == ''){
				//var_dump($opponent);
				//var_dump($opponent);
			}

			// Attack selected player
			$results = turma_arena_simulator_player_action_attack_player($player, $opponent);
			return array(
				array($results[0]),
				array($results[1])
			);
		}

		// Simulate Turma Player Action Heal Player
		function turma_arena_simulator_player_action_heal_player($healer, $player){
			// Get Healer's healing
			$healing = turma_arena_simulator_heal_simulation($healer);
			// If healing too much cap it
			if($healing[1] > $player['life'][1] - $player['life'][0]){
				$healing[1] = $player['life'][1] - $player['life'][0];
			}

			// Heal player
			$player['life'][0] += $healing[1];

			// Save Scores
			$player['score']['healing-taken'] += $healing[1];
			$player['last']['heal'] = $healing[1];
			$healer['score']['healing-done'] += $healing[1];

			//echo "<tr><td>".$healer['name']." heals ".$player['name']."</td><td>".$healing[1]." [".$healing[0]."]</td></tr>\n";

			return array($healer, $player);
		}

		// Simulate Turma Player Action Attack Player
		function turma_arena_simulator_player_action_attack_player($attacker, $defender){
			//var_dump($defender);

			// Get first hit
			$first_hit = turma_arena_simulator_hit_simulation($attacker, $defender);
			$second_hit = false;

			// If player died cap the hit
			if($defender['life'][0] - $first_hit[1] < 0){
				$first_hit[1] = $defender['life'][0];
			}

			// Else try to hit him again
			else if(rand(0, 100) <= $attacker['double-hit-chance'][$defender['index']]){
				// Get second hit
				$second_hit = turma_arena_simulator_hit_simulation($attacker, $defender);

				// If player died cap the hit
				if($defender['life'][0] - $first_hit[1] - $second_hit[1] < 0){
					$second_hit[1] = $defender['life'][0] - $first_hit[1];
				}
			}

			// Damage Defender
			$defender['life'][0] -= $first_hit[1];
			if($second_hit){
				$defender['life'][0] -= $second_hit[1];
			}

			// Save Score
			$defender['score']['damage-taken'] += $first_hit[1];
			$attacker['score']['damage-done'] += $first_hit[1];
			$attacker['last']['damage'] = $first_hit[1];
			if($second_hit){
				$defender['score']['damage-taken'] += $second_hit[1];
				$attacker['score']['damage-done'] += $second_hit[1];
				$attacker['last']['damage'] += $attacker['threat'] + $second_hit[1];
			}

			/*
			#DEBUG
			echo "<tr><td>".$attacker['name']." hits ".$defender['name']."</td><td>".(($first_hit[1]>0)?$first_hit[1]." [".$first_hit[0]."]":$first_hit[0])."</td></tr>\n";
			if($second_hit){
				echo "<tr><td>".$attacker['name']." hits ".$defender['name']."</td><td>".(($second_hit[1]>0)?$second_hit[1]." [".$second_hit[0]."]":$second_hit[0])."</td></tr>\n";
			}
			if($defender['life'][0] == 0){
				echo "<tr><td></td><td>".$defender['name']." died</td></tr>\n";
			}
			*/

			return array($attacker, $defender);
		}

		// Simulate Turma Get Most Wounded
		function turma_arena_simulator_most_wounded($players){
			// Wounded Player
			$most_wounded = false;
			$wound = 0;

			// For each player
			foreach($players as $player){
				// If player has a bigger wound
				if($player['life'][1] - $player['life'][0] > $wound){
					// Save player
					$most_wounded = $player;
					// Save wound size
					$wound = $player['life'][1] - $player['life'][0];
				}
			}

			// Return most wounded
			return $most_wounded;
		}

		// Simulate Turma Get Random Threat based Player
		function turma_arena_simulator_get_random_threatbased_player($players){
			// Prepare for selection
			
			// Calculate total threat
			$threat_sum = 0;
			foreach ($players as $player){
				//$threat_sum += $player['threat'];
				
				if ( $player['last']['threat'] == 0){
					$threat_sum += $player['threat'];
				}else{
					$threat_sum += $player['last']['threat'];
				}
			}

			// Make a random threat number
			$selected = rand(0, $threat_sum);

			// Get selected player
			foreach ($players as $player){
				if ( $player['last']['threat'] == 0){
					$temp_threat = $player['threat'];
				}else{
					$temp_threat = $player['last']['threat'];
				}
				
				if($selected <= $temp_threat){
					// Return selected player 
					return $player;
				}
				$selected -= $temp_threat;
			}

			// WTF moment ... ok, return last.
			return end($players);
		}

		// Score calculate
		function turma_arena_simulator_get_team_score($players, $opponents){
			// Score start from zero
			$score = 0;

			// Get Points from hits and heals
			foreach($players as $player){
				$score += $player['score']['damage-done'];
				$score += round($player['score']['healing-done']/2, 0);
			}

			// Get points for killing opponents
			foreach ($opponents as $player) {
				if($player['life'][0] <= 0){
					$score += $player['life'][0];
				}
			}

			// Return calculated score
			return $score;
		}

		// Parse given stats
		function turma_arena_checkPlayerStats($players){
			// Parse each player
			foreach ($players['players'] as $player){
				if(
					$player['level'] < 1 || 
					!isset($player['life']) || 
					$player['life'][0] < 1 || 
					$player['life'][1] < 1 || 
					$player['skill'] < 1 || 
					$player['agility'] < 1 || 
					$player['charisma'] < 1 || 
					$player['intelligence'] < 1 || 
					$player['armor'] < 0 || 
					$player['damage'][0] < 1 || 
					$player['damage'][1] < 1 || 
					$player['healing'] < 0 || 
					$player['threat'] < 0 || 
					$player['avoid-critical-points'] < 0 || 
					$player['block-points'] < 0 || 
					$player['critical-points'] < 0 || 
					$player['critical-healing'] < 0 ||
					!isset($player['role']) ||
					!(
						$player['role']['isRoleTank'] ||
						$player['role']['isRoleDps'] ||
						$player['role']['isRoleHealer'] ||
						$player['role']['isRoleOut']
					)
				){
					return array(
						'error' => true
					);
				}
			}

			// Return Player List
			return $players;
		}

		// Simulate Turma Battles
		function turma_arena_simulator($attacker, $defender, $options=array()) {

			/*
				ATTACKER CUSTOM
			 */
				// If attacker custom player stats
				if (isset($attacker['custom'])) {
					// Check player's data
					$attacker_stats = turma_arena_checkPlayerStats($attacker);
					// Attacker player not found
					if (isset($attacker_stats['error'])) {
						return array('error' => true, 'message' => 'Player stats error.');
					}
				}
			/*
				ATTACKER PLAYER
			 */
				// If attacker is real player
				else {
					// Get players' data
					$attacker_stats = getPlayerData(
						array(
							'country' => $attacker['country'],
							'server' => $attacker['server'],
							'name' => $attacker['name'],
							'id' => $attacker['id']
						),
						array('turma' => true)
					);
					// Attacker player not found
					if (isset($attacker_stats['error']) || !isset($attacker_stats['turma'])) {
						return array('error' => true, 'message' => 'Attacker player was not found.');
					}
					$attacker_stats = $attacker_stats['turma'];
				}

			/*
				DEFENDER CUSTOM
			 */
				// If defender custom player stats
				if (isset($defender['custom'])) {
					// Check player's data
					$defender_stats = turma_arena_checkPlayerStats($defender);
					// Defender player not found
					if (isset($defender_stats['error'])) {
						return array('error' => true, 'message' => 'Player stats error.');
					}
				}
			/*
				DEFENDER PLAYER
			 */
				// If defender is real player
				else {
					$defender_stats = getPlayerData(
						array(
							'country' => $defender['country'],
							'server' => $defender['server'],
							'name' => $defender['name'],
							'id' => $defender['id']
						),
						array('turma' => true)
					);
					// Defender player not found
					if (isset($defender_stats['error']) || !isset($defender_stats['turma'])) {
						return array('error' => true, 'message' => 'Defender player was not found.');
					}
					$defender_stats = $defender_stats['turma'];
				}

			/*
				OPTIONS PARSE
			 */
				
				// Simulations
				if(!isset($options['simulates']) || !is_numeric($options['simulates']) || $options['simulates'] <= 0) $options['simulates'] = 50;
				if ($options['simulates'] > 10000) {
					$options['simulates'] = 10000;
				}

			/*
				CALCULATE MORE STATS
			 */
				
				// Get players
				$attacker_stats = turma_arena_simulator_players($attacker_stats);
				$defender_stats = turma_arena_simulator_players($defender_stats);

				// Types
				$attacker_stats = turma_arena_simulator_types($attacker_stats);
				$defender_stats = turma_arena_simulator_types($defender_stats);
			
				// Calculate Chances
				$attacker_stats = turma_arena_simulator_calculate_chances($attacker_stats, $defender_stats);
				$defender_stats = turma_arena_simulator_calculate_chances($defender_stats, $attacker_stats);

				/*
				#DEBUG
				// Turma Level Diff ?
				echo "<b>*Beta* Turma Arena Simulator v0.2</b><br>";
				echo "<b>by Gladiatus Crazy Team</b><br><br><br>";

				echo "<table><tr><th>A</th><th>Name</th><th>Type</th><th>Threat</th></tr>";
				foreach ($attacker_stats as $i => $player) {
					echo "<tr><td>[".$i."]</td><td>".$player['name']."</td><td>".$player['isRoleOf']."</td><td>".$player['threat']."</td></tr>";
				}
				echo "<tr><th>D</th><th>Name</th><th>Type</th><th>Threat</th></tr>";
				foreach ($defender_stats as $i => $player) {
					echo "<tr><td>[".$i."]</td><td>".$player['name']."</td><td>".$player['isRoleOf']."</td><td>".$player['threat']."</td></tr>";
				}
				echo "</table><br><br>";
				*/

			/*
				SIMULATE FIGHTS
			 */
			
				// Simulate x fights
				$wins = 0;
				$draws = 0;
				$fights = 0;

				while ($fights < $options['simulates']) {
					// Simulate a battle
					$result = turma_arena_simulator_battle($attacker_stats, $defender_stats);
					
					// Attacker won battle
					if ($result == 1) {
						$wins++;
					} else if ($result == 0) {
						$draws++;
					}
					
					// Fight x was finished
					$fights++;
				}

			/*
				EXPORT RESULTS
			 */

				$results = array(
					'win-chance' => (round($wins / $fights * 10000) / 100),
					'lose-chance' => (round(($fights-$wins-$draws) / $fights * 10000) / 100),
					'draw-chance' => (round($draws / $fights * 10000) / 100),
					'details' => array(
						'fights' => $fights,
						'wins' => $wins,
						'loses' => $fights-$wins-$draws,
						'draws' => $draws
					)
				);

			/*
				RETURN RESULTS
			 */
			
				// Print data in json format
				return $results;
		}
