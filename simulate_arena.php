<?php
/*
 * Gladiatus Battle Simulator by Gladiatus Crazy Team
 * https://github.com/DinoDevs
 * https://www.facebook.com/GladiatusCrazyAddOn
 * Authors : GramThanos, GreatApo
 *
 * Gladiatus Data
 * Player Arena Fight Simulate Lib
 */

/*
	Example Use
	
	$results = arena_simulator(
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
			'life-mode' => 'current', // ['current', 'full', 'unlimited'] default 'current'
			'simulates' => '10000' // [1-10000] default 500
		),
	);
*/

	/*
		Library
	*/
		// Load request player data library
		// https://github.com/DinoDevs/GladiatusPlayerStatsAPI
		require_once('request_playerData.php');
	
	
	/*
		Constants
	*/
		const ARENA_SIMULATOR_HIT_NORMAL = 1;
		const ARENA_SIMULATOR_HIT_CRITICAL = 2;
		const ARENA_SIMULATOR_HIT_AVOIDED_CRITICAL = 3;
		const ARENA_SIMULATOR_HIT_BLOCKED = 4;
		const ARENA_SIMULATOR_HIT_MISSED = 5;

	/*
		Functions
	*/
		const ARENA_SIMULATOR_REPORT_ATTACKER = 1;
		const ARENA_SIMULATOR_REPORT_DEFENDER = 2;
		const ARENA_SIMULATOR_REPORT_ACTION_HIT = 1;
		const ARENA_SIMULATOR_REPORT_ACTION_KILL = 2;

		// Simulate a Battle
		function arena_simulator_battle($attacker_stats, $defender_stats, $life_mode = 'current', $battle_rounds = 15, &$reports = null) {
			// Select Players Life
			// current
			$attacker_life = $attacker_stats['life'][0];
			$defender_life = $defender_stats['life'][0];
			if ($life_mode == 'full') {
				$attacker_life = $attacker_stats['life'][1];
				$defender_life = $defender_stats['life'][1];
			} else if ($life_mode == 'ignore') {
				$attacker_life = INF;
				$defender_life = INF;
			}

			// Prepare report
			$reporting = is_null($reports) ? false : true;
			$report = $reporting ? null : array();

			// Report results on fist item (so leave it blank)
			if ($reporting) $report[] = array();

			$score_attacker = 0;
			$score_defender = 0;
			$rounds = 0;
			while ($rounds < $battle_rounds && $attacker_life > 0 && $defender_life > 0) {
				$report_round = $reporting ? null : array();

				// Attacker
					// Single hit
					$hit = arena_simulator_hit_simulation($attacker_stats, $defender_stats);
					$score_attacker += $hit[1];
					$defender_life -= $hit[1];
					// Stop if dead
					if ($defender_life <= 0) {
						$score_attacker += $defender_life;
						if ($reporting) {
							$report_round[] = array(ARENA_SIMULATOR_REPORT_ATTACKER, ARENA_SIMULATOR_REPORT_ACTION_KILL, $hit[0], $hit[1] + $defender_life);
							$report[] = $report_round;
						}
						break;
					}
					else {
						if ($reporting) $report_round[] = array(ARENA_SIMULATOR_REPORT_ATTACKER, ARENA_SIMULATOR_REPORT_ACTION_HIT, $hit[0], $hit[1]);
					}
					
					// Double hit
					if (rand(0, 100) <= $attacker_stats['double-hit-chance']) {
						$hit = arena_simulator_hit_simulation($attacker_stats, $defender_stats);
						$score_attacker += $hit[1];
						$defender_life -= $hit[1];
						// Stop if dead
						if($defender_life <= 0) {
							$score_attacker += $defender_life;
							if ($reporting) {
								$report_round[] = array(ARENA_SIMULATOR_REPORT_ATTACKER, ARENA_SIMULATOR_REPORT_ACTION_KILL, $hit[0], $hit[1] + $defender_life);
								$report[] = $report_round;
							}
							break;
						}
						else {
							if ($reporting) $report_round[] = array(ARENA_SIMULATOR_REPORT_ATTACKER, ARENA_SIMULATOR_REPORT_ACTION_HIT, $hit[0], $hit[1]);
						}
					}
					
				// Defender
					// Single hit
					$hit = arena_simulator_hit_simulation($defender_stats, $attacker_stats);
					$score_defender += $hit[1];
					$attacker_life -= $hit[1];
					// Stop if dead
					if ($attacker_life <= 0) {
						$score_defender += $attacker_life;
						if ($reporting) {
							$report_round[] = array(ARENA_SIMULATOR_REPORT_DEFENDER, ARENA_SIMULATOR_REPORT_ACTION_KILL, $hit[0], $hit[1] + $attacker_life);
							$report[] = $report_round;
						}
						break;
					}
					else {
						if ($reporting) $report_round[] = array(ARENA_SIMULATOR_REPORT_DEFENDER, ARENA_SIMULATOR_REPORT_ACTION_HIT, $hit[0], $hit[1]);
					}
					
					// Double hit
					if (rand(0, 100) <= $defender_stats['double-hit-chance']) {
						$hit = arena_simulator_hit_simulation($defender_stats, $attacker_stats);
						$score_defender += $hit[1];
						$attacker_life -= $hit[1];
						// Stop if dead
						if ($attacker_life <= 0) {
							$score_defender += $attacker_life;
							if ($reporting) {
								$report_round[] = array(ARENA_SIMULATOR_REPORT_DEFENDER, ARENA_SIMULATOR_REPORT_ACTION_KILL, $hit[0], $hit[1] + $attacker_life);
								$report[] = $report_round;
							}
							break;
						}
						else {
							if ($reporting) $report_round[] = array(ARENA_SIMULATOR_REPORT_DEFENDER, ARENA_SIMULATOR_REPORT_ACTION_HIT, $hit[0], $hit[1]);
						}
					}
				
				// Report round
				if ($reporting) $report[] = $report_round;

				// Round x was finished
				$rounds++;
			}
			
			if ($attacker_life < 0) $attacker_life = 0;
			if ($defender_life < 0) $defender_life = 0;
			$score = $score_attacker - $score_defender;

			// Report battle
			$report[0] = array(
				// Attacker
				array($score_attacker, $attacker_life),
				// Defender
				array($score_defender, $defender_life)
			);
			if ($reporting) $reports[] = $report;

			// Battle Won
			if ($defender_life <= 0 || $score > 0) {
				return 1;
			
			// Battle Draw
			} else if ($score == 0) {
				return 0;

			// Battle Lost
			} else {
				return -1;
			}
		}

		// Simulate a hit
		function arena_simulator_hit_simulation($playerA,$playerB) {
			// Single hit
			if (rand(0, 100) <= $playerA['hit-chance']) {
				// Critical hit
				if (rand(0, 100) <= $playerA['hit-chance']) {
					// Successful
					if (rand(0, 100) > $playerB['critical-chance']) {
						$hit = array(ARENA_SIMULATOR_HIT_CRITICAL, 2 * rand($playerA['damage'][0], $playerA['damage'][1]) - rand($playerB['armor-absorve'][0], $playerB['armor-absorve'][1]) );
						if ($hit[1] < 0) $hit[1] = 0;
					// Avoided
					} else {
						// Avoided
						$hit = array(ARENA_SIMULATOR_HIT_AVOIDED_CRITICAL, rand($playerA['damage'][0], $playerA['damage'][1]) - rand($playerB['armor-absorve'][0], $playerB['armor-absorve'][1]) );
						if ($hit[1] < 0) $hit[1] = 0;
					}
				}
				// Normal hit
				else {
					// Successful
					if (rand(0, 100) > $playerB['block-chance']) {
						$hit = array(ARENA_SIMULATOR_HIT_NORMAL, rand($playerA['damage'][0], $playerA['damage'][1]) - rand($playerB['armor-absorve'][0], $playerB['armor-absorve'][1]) );
						if ($hit[1] < 0) $hit[1] = 0;
					// Blocked
					} else {
						$hit = array(ARENA_SIMULATOR_HIT_BLOCKED, rand($playerA['damage'][0], $playerA['damage'][1]) / 2 - rand($playerB['armor-absorve'][0], $playerB['armor-absorve'][1]));
						if ($hit[1] < 0) $hit[1] = 0;
					}
				}

			// Miss
			} else {
				// Miss
				$hit = array(ARENA_SIMULATOR_HIT_MISSED, 0);
			}
			
			// Give back the hit value
			return $hit;
		}

		// Calculate Chances
		function arena_simulator_calculate_chances($playerA, $playerB) {
			// Make negative values zero
			if ($playerA['avoid-critical-points'] < 0) $playerA['avoid-critical-points'] = 0;
			if ($playerB['avoid-critical-points'] < 0) $playerB['avoid-critical-points'] = 0;
			if ($playerA['block-points'] < 0) $playerA['block-points'] = 0;
			if ($playerB['block-points'] < 0) $playerB['block-points'] = 0;
			if ($playerA['critical-points'] < 0) $playerA['critical-points'] = 0;
			if ($playerB['critical-points'] < 0) $playerB['critical-points'] = 0;

			// Level Factor
			$levelFactorA = $playerA['level'] - 8;
			if($levelFactorA < 2) $levelFactorA = 2;

			// Calculate Avoid Critical Chance Percent
			$playerA['avoid-critical-chance'] = round($playerA['avoid-critical-points'] * 52 / $levelFactorA / 4);
			if ($playerA['avoid-critical-chance'] > 25) $playerA['avoid-critical-chance'] = 25;

			// Calculate Block Chance Percent
			$playerA['block-chance'] = round($playerA['block-points'] * 52 / $levelFactorA / 6) + max(0, $playerA['level'] - $playerB['level']) * 2;
			if ($playerA['block-chance'] > 50) $playerA['block-chance'] = 50;

			// Calculate Critical Chance Percent
			$playerA['critical-chance'] = round($playerA['critical-points'] * 52 / $levelFactorA / 5);
			if ($playerA['critical-chance'] > 50) $playerA['critical-chance'] = 50;

			// Calculate hit chance
			$playerA['hit-chance'] = floor($playerA['skill'] / ($playerA['skill'] + $playerB['agility']) * 100);
			
			// Calculate double hit chance
			$playerA['double-hit-chance'] = round($playerA['charisma'] * $playerA['skill'] / ($playerB['agility'] * $playerB['intelligence']) * 10);
			
			// God Buffs
			if ($playerA['buffs']['minerva'] || $playerB['buffs']['minerva']) $playerA['double-hit-chance'] = 0;
			if ($playerA['buffs']['mars'] || $playerB['buffs']['mars']) $playerA['critical-chance'] = 0;
			if ($playerA['buffs']['apollo']) $playerA['block-chance'] += 15;
			// Pacts Buffs
			if ($playerA['buffs']['honour_veteran']) $playerA['critical-chance'] += 10;
			if ($playerB['buffs']['honour_destroyer']) {
				$playerA['armor'] -= $playerB['level'] * 15;
				if ($playerA['armor'] < 0) $playerA['armor'] = 0;
			}

			// Calculate armor absorve
			$playerA['armor-absorve'] = array(
				floor($playerA['armor'] / 66) - floor(($playerA['armor'] - 66) / 660 + 1),
				floor($playerA['armor'] / 66) + floor($playerA['armor'] / 660)
			);

			return $playerA;
		}

		// Parse given stats
		function arena_checkPlayerStats($player) {
			// Check for errors
			if(
				$player['level'] < 1 || 
				$player['life'][0] < 1 || 
				$player['life'][1] < 1 || 
				$player['skill'] < 1 || 
				$player['agility'] < 1 || 
				$player['charisma'] < 1 || 
				$player['intelligence'] < 1 || 
				$player['armor'] < 0 || 
				$player['damage'][0] < 1 || 
				$player['damage'][1] < 1
			){
				return array(
					'error' => true
				);
			}

			// Check buffs
			$buffs = array(
				'minerva' => false,
				'mars' => false,
				'apollo' => false,
				'honour_veteran' => false,
				'honour_destroyer' => false
			);
			if (isset($player['buffs'])) {
				$buffs['minerva'] = (isset($player['buffs']['minerva']) && $player['buffs']['minerva'] ? true : false);
				$buffs['mars'] = (isset($player['buffs']['mars']) && $player['buffs']['mars'] ? true : false);
				$buffs['apollo'] = (isset($player['buffs']['apollo']) && $player['buffs']['apollo'] ? true : false);
				$buffs['honour_veteran'] = (isset($player['buffs']['honour_veteran']) && $player['buffs']['honour_veteran'] ? true : false);
				$buffs['honour_destroyer'] = (isset($player['buffs']['honour_destroyer']) && $player['buffs']['honour_destroyer'] ? true : false);
			}

			// Parse results
			$results = array(
				'stats' => array(
					'level' => $player['level'],
					'life' => $player['life'],
					'skill' => $player['skill'],
					'agility' => $player['agility'],
					'charisma' => $player['charisma'],
					'intelligence' => $player['intelligence'],
					'armor' => $player['armor'],
					'damage' => $player['damage'],
					'avoid-critical-points' => $player['avoid-critical-points'],
					'block-points' => $player['block-points'],
					'critical-points' => $player['critical-points'],
					'buffs' => $buffs
				)
			);
			
			// Return data
			return $results;
		}

		// Simulate Battles
		function arena_simulator($attacker, $defender, $options=array()) {

			/*
				ATTACKER
			 */
				// If attacker custom player stats
				if (isset($attacker['custom'])) {
					// Get players' data
					$attacker_stats = arena_checkPlayerStats($attacker);
					// Attacker player not found
					if (isset($attacker_stats['error']) || !isset($attacker_stats['stats'])) {
						return array('error' => true, 'message' => 'Player stats error.');
					}
					$attacker_stats = $attacker_stats['stats'];
				}
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
						array(
							'profile' => true
						)
					);
					// Attacker player error
					if (isset($attacker_stats['error']) || !isset($attacker_stats['profile'])) {
						// Attacker server backup
						if (isset($attacker_stats['backup'])) {
							return array('error' => true, 'message' => 'Attacker server is on backup mode.');
						}
						// Attacker player not found
						else {
							return array('error' => true, 'message' => 'Attacker player was not found.');
						}
					}
					if (isset($options['info']))
						$attacker_info = $attacker_stats;
					$attacker_stats = $attacker_stats['profile'];
				}


			/*
				DEFENDER
			 */
				// If defender custom player stats
				if (isset($defender['custom'])) {
					// Get players' data
					$defender_stats = arena_checkPlayerStats($defender);
					// Attacker player not found
					if (isset($defender_stats['error']) || !isset($defender_stats['stats'])) {
						return array('error' => true, 'message' => 'Player stats error.');
					}
					$defender_stats = $defender_stats['stats'];
				}
				// If defender is real player
				else {
					$defender_stats = getPlayerData(
						array(
							'country' => $defender['country'],
							'server' => $defender['server'],
							'name' => $defender['name'],
							'id' => $defender['id']
						),
						array(
							'profile' => true
						)
					);
					// Defender player error
					if (isset($defender_stats['error']) || !isset($defender_stats['profile'])) {
						// Defender server backup
						if (isset($defender_stats['backup'])) {
							return array('error' => true, 'message' => 'Defender server is on backup mode.');
						}
						// Defender player not found
						else {
							return array('error' => true, 'message' => 'Defender player was not found.');
						}
					}
					if (isset($options['info']))
						$defender_info = $defender_stats;
					$defender_stats = $defender_stats['profile'];
				}


			/*
				OPTIONS PARSE
			 */
				// Get life mode
				if (!isset($options['life-mode'])) {
					$life_mode = 'current';
				}
				else {
					switch ($options['life-mode']) {
						case 'full': $life_mode = 'full';break;
						case 'unlimited': $life_mode = 'unlimited';break;
						default: $life_mode = 'current';
					}
				}
				
				// Simulations
				if ((!isset($options['simulates']) || !is_numeric($options['simulates'])) || $options['simulates'] <= 0) $options['simulates'] = 500;
				if ($options['simulates'] > 10000) $options['simulates'] = 10000;

				// Rounds
				$rounds = 15;
				if (
					isset($options['rounds']) && is_numeric($options['rounds']) &&
					$options['rounds'] > 0 && $options['rounds'] <= 50
				) {
					$rounds = $options['rounds'];
				}


			/*
				CALCULATE MORE STATS
			 */
				// Calculate Chances
				$attacker_stats = arena_simulator_calculate_chances($attacker_stats, $defender_stats);
				$defender_stats = arena_simulator_calculate_chances($defender_stats, $attacker_stats);
				
				// Add internal block (level dif +2% block)
				/*
				$levelDifference = $attacker_stats['level'] - $defender_stats['level'];
				if ($levelDifference > 0) {
					$attacker_stats['block-chance'] = $attacker_stats['block-chance'] + $levelDifference * 2;
					if ($attacker_stats['block-chance'] > 50) {
						$attacker_stats['block-chance'] = 50;
					}
				} else {
					$defender_stats['block-chance'] = $defender_stats['block-chance'] + $levelDifference * 2;
					if ($defender_stats['block-chance'] > 50) {
						$defender_stats['block-chance'] = 50;
					}
				}
				*/

			/*
				SIMULATE FIGHTS
			 */
			
				// Simulate x fights
				$wins = 0;
				$draws = 0;
				$fights = 0;

				$reports = ($options['simulates'] <= 5) ? array() : null;

				while ($fights < $options['simulates']) {
					// Simulate a battle
					$result = arena_simulator_battle($attacker_stats, $defender_stats, $life_mode, $rounds, $reports);
					
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
				
				if (isset($options['info'])) {
					$results['info'] = array(
						'attacker' => array(
							'name' => $attacker_info['name'],
							'id' => $attacker_info['id'],
							'guild' => isset($attacker_info['guild']) ? $attacker_info['guild']['name'] : false,
							'guild_id' => isset($attacker_info['guild']) ? $attacker_info['guild']['id'] : false,
							'level' =>  $attacker_stats['level']
						),
						'defender' => array(
							'name' => $defender_info['name'],
							'id' => $defender_info['id'],
							'guild' => isset($defender_info['guild']) ? $defender_info['guild']['name'] : false,
							'guild_id' => isset($defender_info['guild']) ? $defender_info['guild']['id'] : false,
							'level' =>  $defender_stats['level']
						)
					);
				}

				if (!is_null($reports)) {
					$results['reports'] = $reports;
				}

			/*
				RETURN RESULTS
			 */
			
				// Print data in json format
				return $results;
		}
