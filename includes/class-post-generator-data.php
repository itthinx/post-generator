<?php
/**
 * class-post-generator-data.php
 *
 * Copyright (c) 2017 "kento" Karim Rahimpur www.itthinx.com
 *
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 *
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * This header and all notices must be kept intact.
 *
 * @author itthinx
 * @package post-generator
 * @since 1.0.0
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post content generation functions.
 */
class Post_Generator_Data {

	/**
	 * Produce a username.
	 * @return string
	 */
	public function get_username() {
		global $wpdb;
		if ( $max_user_id = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->users" ) ) {
			$suffix = '' . ( intval( $max_user_id ) + 1 );
		} else {
			$t = time();
			$r = rand();
			$suffix = "$t-$r";
		}
		return "user-$suffix";
	}

	/**
	 * Returns a randomly constructed word based on our syllables.
	 * First letter is uppercase.
	 * 
	 * @param number $min minimum number of syllables (default 1)
	 * @param number $max maximum number of syllables (default 5)
	 * @return string
	 */
	public function get_uc_random_word( $min = 1, $max = 5 ) {
		return ucfirst( $this->get_random_word( $min, $max) );
	}

	/**
	 * Generate random content based on our syllables.
	 * @param number $min minimum number of words
	 * @param number $max maximum number of words
	 * @param number $min_syllables minimum numer of syllables per word
	 * @param number $max_syllables maximum number of syllables per word
	 * @return string
	 */
	public function get_random_content( $min = 1, $max = 50, $min_syllables = 1, $max_syllables = 5 ) {
		$content = $this->get_uc_random_word( $min_syllables, $max_syllables );
		$n = rand( $min, $max );
		for( $i = 2; $i < $n; $i++ ) {
			$content .= ' ' . $this->get_random_word( $min_syllables, $max_syllables );
		}
		$content .= '.';
		return $content;
	}

	/**
	 * Returns a randomly constructed word based on our syllables.
	 *
	 * @param number $min minimum number of syllables (default 1)
	 * @param number $max maximum number of syllables (default 5)
	 * @return string
	 */
	public function get_random_word( $min = 1, $max = 5 ) {
		$result = '';
		$syllables = WC_Order_Generator_Syllables::get_syllables();
		$n = rand( $min, $max );
		for ( $i=0; $i < $n; $i++ ) {
			$result .= $syllables[rand( 0, count( $syllables ) - 1 )];
		}
		return $result;
	}

	/**
	 * Constructs a random first name.
	 * @return string
	 */
	public function get_first_name() {
		return $this->get_uc_random_word( 1, 3 );
	}

	/**
	 * Constructs a random last name.
	 * @return string
	 */
	public function get_last_name() {
		return $this->get_uc_random_word( 2, 6 );
	}

	/**
	 * Creates a new user and returns the user ID or null on failure.
	 * @return int or null
	 */
	public function create_user() {
		global $wpdb;

		$user_id = null;

		$username = $this->get_username();
		$user = array(
			'user_login'    => $username,
			'user_pass'     => $username,
			'user_email'    => $username . '@example.com',
			'first_name'    => $this->get_first_name(),
			'last_name'     => $this->get_last_name(),
			'role'          => 'subscriber'
		);

		$inserted_user_id = wp_insert_user( $user );
		if ( !( $inserted_user_id instanceof WP_Error ) ) {
			$user_id      = $inserted_user_id;
		}
		return $user_id;
	}

	/**
	 * Get a random user id or create a new one, depending on the probability $p indicated.
	 * @param real $p default 0.5
	 * @return number|NULL
	 */
	public function create_or_get_random_user_id( $p = 0.5 ) {
		if ( ( rand( 1, 10 ) / 10.0 ) <= $p ) {
			$user_id = $this->create_user();
		} else {
			$user_id = $this->get_random_user_id();
		}
		return $user_id;
	}

	/**
	 * Returns the ID of a random user or null on failure.
	 * @return NULL|number
	 */
	public function get_random_user_id() {
		global $wpdb;
		$user_id = null;
		if ( $max_user_id = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->users" ) ) {
			$max_user_id = intval( $max_user_id );
			for( $i = 0; $i < $max_user_id; $i++ ) {
				$maybe_user_id = rand( 1, $max_user_id );
				if ( $user = get_user_by( 'id', $maybe_user_id ) ) {
					$user_id = $user->ID;
					break;
				}
			}
		}
		return $user_id;
	}

	/**
	 * Returns the id of a group selected randomly.
	 * If Groups is not active, it will return null.
	 *
	 * @return int or null
	 */
	public function get_random_group_id() {
		return array_shift( $this->get_random_group_ids() );
	}

	/**
	 * Returns an array of randomly selected group ids.
	 * If Groups is not active, it will return an empty array.
	 * The number of desired entries is a maximum, and will be reached
	 * if there are at least as many different groups.
	 *
	 * @param $min int minimum number of desired entries
	 * @param $max int maximum number of desired entries
	 * @return array or null
	 */
	public function get_random_group_ids( $min = 1, $max = 1 ) {
		$result = array();
		$min = min( $min, $max );
		$max = max( $min, $max );
		$min = max( 1, $min );
		if ( $max < $min ) {
			$max = $min;
		}
		if ( class_exists( 'Groups_Group' ) ) {
			$group_ids = Groups_Group::get_groups( array( 'fields' => 'group_id' ) );
			$n = rand( $min, $max );
			for ( $i = 1; $i < $n ; $i++ ) {
				if ( count( $group_ids ) > 0 ) {
					$k = rand( 0, count( $group_ids ) - 1 );
					$result[] = $group_ids[$k]->group_id;
					unset( $group_ids[$k] );
				} else {
					break;
				}
			}
		}
		return $result;
	}
}
