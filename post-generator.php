<?php
/**
 * post-generator.php
 *
 * Copyright (c) 2014-2016 "kento" Karim Rahimpur www.itthinx.com
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
 * @package generator
 * @since 1.0.0
 *
 * Plugin Name: Post Generator
 * Plugin URI: http://www.itthinx.com/
 * Description: A sample content generator for WordPress.
 * Version: 1.0.1
 * Author: itthinx
 * Author URI: http://www.itthinx.com
 * Donate-Link: http://www.itthinx.com
 * License: GPLv3
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}
define( 'POST_GENERATOR_PLUGIN_VERSION', '1.1.0' );
define( 'POST_GENERATOR_PLUGIN_DOMAIN', 'post-generator' );
define( 'POST_GENERATOR_PLUGIN_FILE', __FILE__ );
define( 'POST_GENERATOR_PLUGIN_URL', plugins_url( 'post-generator' ) );
define( 'POST_GENERATOR_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'POST_GENERATOR_INCLUDES_DIR', POST_GENERATOR_PLUGIN_DIR . '/includes' );
function post_generator_plugins_loaded() {
	require_once POST_GENERATOR_INCLUDES_DIR . '/class-post-generator-constants.php';
	require_once POST_GENERATOR_INCLUDES_DIR . '/class-post-generator-syllables.php';
	require_once POST_GENERATOR_INCLUDES_DIR . '/class-post-generator-data.php';
	require_once POST_GENERATOR_INCLUDES_DIR . '/class-post-generator.php';
}
add_action( 'plugins_loaded', 'post_generator_plugins_loaded' );
