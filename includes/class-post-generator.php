<?php
/**
 * class-post-generator-syllables.php
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
 * Generator.
 */
class Post_Generator {

	const MAX_PER_RUN        = 100;
	const DEFAULT_PER_RUN    = 10;
	const IMAGE_WIDTH        = 512;
	const IMAGE_HEIGHT       = 512;
	const DEFAULT_POST_TYPE  = 'post';
	const DEFAULT_LIMIT      = 10000;
	// 	const Post_Generator_Constants::$contents   = Post_Generator_Constants::$contents;
	// 	const Post_Generator_Constants::$titles     = Post_Generator_Constants::$titles;
	const DEFAULT_MIN_GROUPS = 0;
	const DEFAULT_MAX_GROUPS = 0;
	const DEFAULT_MIN_COMMENTS = 0;
	const DEFAULT_MAX_COMMENTS = 0;
	const DEFAULT_NEW_USER_PROBABILITY = 0.5;
	const SCALE = 2;

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// register_activation_hook(__FILE__, array( __CLASS__,'activate' ) );
		// register_deactivation_hook(__FILE__,  array( __CLASS__,'deactivate' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		if ( is_admin() ) {
			add_filter( 'plugin_action_links_'. plugin_basename( __FILE__ ), array( __CLASS__, 'admin_settings_link' ) );
		}
		add_action( 'init', array( __CLASS__, 'wp_init' ) );
	}

	/**
	 * Does nothing for now.
	 */
	public static function activate() {
	}

	/**
	 * Does nothing for now.
	 */
	public static function deactivate() {
	}

	/**
	 * Add the Generator menu item.
	 */
	public static function admin_menu() {
		$page = add_menu_page(
			__( 'Post Generator', 'post-generator' ),
			__( 'Post Generator', 'post-generator' ),
			'install_plugins',
			'post-generator',
			array( __CLASS__, 'admin' )
		);
		add_action( 'load-' . $page, array( __CLASS__, 'load' ) );
	}

	/**
	 * Registers the plugin's script and style.
	 */
	public static function load() {
		wp_register_script( 'generator', POST_GENERATOR_PLUGIN_URL . '/js/generator.js', array( 'jquery' ), POST_GENERATOR_PLUGIN_VERSION, true );
		wp_register_style( 'generator', POST_GENERATOR_PLUGIN_URL . '/css/generator.css', array(), POST_GENERATOR_PLUGIN_VERSION );
	}

	/**
	 * Adds plugin links.
	 *
	 * @param array $links
	 * @param array $links with additional links
	 */
	public static function admin_settings_link( $links ) {
		$links[] = '<a href="' . get_admin_url( null, 'admin.php?page=post-generator' ) . '">' . __( 'Generator', 'post-generator' ) . '</a>';
		return $links;
	}

	/**
	 * AJAX request handler.
	 *
	 * If a valid generator request is recognized,
	 * it runs a generation cycle and then produces the JSON-encoded response
	 * containing the current number of published posts held in the 'total'
	 * property.
	 */
	public static function wp_init() {
		if (
			isset( $_REQUEST['generator'] ) &&
			wp_verify_nonce( $_REQUEST['generator'], 'post-generator-js' )
			) {
				// run generator
				$per_run = get_option( 'post-generator-per-run', self::DEFAULT_PER_RUN );
				self::run( $per_run );
				$post_type = get_option( 'post-generator-post-type', self::DEFAULT_POST_TYPE );
				$n_posts = self::get_post_count( $post_type );
				$result = array( 'total' => $n_posts );
				echo json_encode( $result );
				exit;
			}
	}

	/**
	 * Renders the admin section with the generation UI.
	 */
	public static function admin() {
		if ( !current_user_can( 'install_plugins' ) ) {
			wp_die( __( 'Access denied.', 'post-generator' ) );
		}

		wp_enqueue_script( 'generator' );
		wp_enqueue_style( 'generator' );

		if ( isset( $_POST['action'] ) && ( $_POST['action'] == 'save' ) && wp_verify_nonce( $_POST['generator'], 'admin' ) ) {

			$post_type  = !empty( $_POST['post_type'] ) ? $_POST['post_type'] : self::DEFAULT_POST_TYPE;
			$limit      = !empty( $_POST['limit'] ) ? intval( trim( $_POST['limit'] ) ) : self::DEFAULT_LIMIT;
			$per_run    = !empty( $_POST['per_run'] ) ? intval( trim( $_POST['per_run'] ) ) : self::DEFAULT_PER_RUN;
			$titles     = !empty( $_POST['titles'] ) ? $_POST['titles'] : '';
			$contents   = !empty( $_POST['contents'] ) ? $_POST['contents'] : '';

			$min_groups = !empty( $_POST['min-groups'] ) ? intval( trim( $_POST['min-groups'] ) ) : self::DEFAULT_MIN_GROUPS;
			$max_groups = !empty( $_POST['max-groups'] ) ? intval( trim( $_POST['max-groups'] ) ) : self::DEFAULT_MAX_GROUPS;
			$min_groups = min( $min_groups, $max_groups );
			$max_groups = max( $min_groups, $max_groups );
			$min_groups = max( 0, $min_groups );
			$max_groups = max( $min_groups, $max_groups );

			$min_comments = !empty( $_POST['min-comments'] ) ? intval( trim( $_POST['min-comments'] ) ) : self::DEFAULT_MIN_COMMENTS;
			$max_comments = !empty( $_POST['max-comments'] ) ? intval( trim( $_POST['max-comments'] ) ) : self::DEFAULT_MAX_COMMENTS;
			$min_comments = min( $min_comments, $max_comments );
			$max_comments = max( $min_comments, $max_comments );
			$min_comments = max( 0, $min_comments );
			$max_comments = max( $min_comments, $max_comments );

			$new_user_probability = !empty( $_POST['new-user-probability'] ) ? bcadd( '0', trim( $_POST['new-user-probability'] ), self::SCALE ) : self::DEFAULT_NEW_USER_PROBABILITY;
			if ( bccomp( '0', $new_user_probability, self::SCALE ) > 0 ) {
				$new_user_probability = '0.0';
			}
			if ( bccomp( '1', $new_user_probability, self::SCALE ) < 0 ) {
				$new_user_probability = '1.0';
			}

			delete_option( 'post-generator-post-type' );
			add_option( 'post-generator-post-type', $post_type, null, 'no' );

			if ( $limit < 0 ) {
				$limit = self::DEFAULT_LIMIT;
			}
			delete_option( 'post-generator-limit' );
			add_option( 'post-generator-limit', $limit, null, 'no' );

			if ( $per_run < 0 ) {
				$per_run = self::DEFAULT_PER_RUN;
			}
			if ( $per_run > self::MAX_PER_RUN ) {
				$per_run = self::MAX_PER_RUN;
			}
			delete_option( 'post-generator-per-run' );
			add_option( 'post-generator-per-run', $per_run, null, 'no' );

			delete_option( 'post-generator-titles' );
			add_option( 'post-generator-title', $titles, null, 'no' );

			delete_option( 'post-generator-contents' );
			add_option( 'post-generator-contents', $contents, null, 'no' );

			delete_option( 'post-generator-min-groups' );
			add_option( 'post-generator-min-groups', $min_groups, null, 'no' );
			delete_option( 'post-generator-max-groups' );
			add_option( 'post-generator-max-groups', $max_groups, null, 'no' );
			delete_option( 'post-generator-min-comments' );
			add_option( 'post-generator-min-comments', $min_comments, null, 'no' );
			delete_option( 'post-generator-max-comments' );
			add_option( 'post-generator-max-comments', $max_comments, null, 'no' );
			delete_option( 'post-generator-new-user-probability' );
			add_option( 'post-generator-new-user-probability', $new_user_probability, null, 'no' );
		} else if ( isset( $_POST['action'] ) && ( $_POST['action'] == 'generate' ) && wp_verify_nonce( $_POST['post-generate'], 'admin' ) ) {
			$max = isset( $_POST['max'] ) ? intval( $_POST['max'] ) : 0;
			if ( $max > 0 ) {
				for ( $i = 1; $i <= $max ; $i++ ) {
					self::create_post();
				}
			}
		} else if ( isset( $_POST['action'] ) && ( $_POST['action'] == 'reset' ) && wp_verify_nonce( $_POST['post-generator-reset'], 'admin' ) ) {

			delete_option( 'post-generator-post-type' );
			add_option( 'post-generator-post-type', self::DEFAULT_POST_TYPE, null, 'no' );

			delete_option( 'post-generator-limit' );
			add_option( 'post-generator-limit', self::DEFAULT_LIMIT, null, 'no' );

			delete_option( 'post-generator-per-run' );
			add_option( 'post-generator-per-run', self::DEFAULT_PER_RUN, null, 'no' );

			delete_option( 'post-generator-titles' );
			add_option( 'post-generator-title', Post_Generator_Constants::$titles, null, 'no' );

			delete_option( 'post-generator-contents' );
			add_option( 'post-generator-contents', Post_Generator_Constants::$contents, null, 'no' );

			delete_option( 'post-generator-min-groups' );
			add_option( 'post-generator-min-groups', self::DEFAULT_MIN_GROUPS, null, 'no' );

			delete_option( 'post-generator-max-groups' );
			add_option( 'post-generator-max-groups', self::DEFAULT_MAX_GROUPS, null, 'no' );

			delete_option( 'post-generator-min-comments' );
			add_option( 'post-generator-min-comments', self::DEFAULT_MIN_COMMENTS, null, 'no' );

			delete_option( 'post-generator-max-comments' );
			add_option( 'post-generator-max-comments', self::DEFAULT_MAX_COMMENTS, null, 'no' );

			delete_option( 'post-generator-new-user-probability' );
			add_option( 'post-generator-new-user-probability', self::DEFAULT_NEW_USER_PROBABILITY, null, 'no' );
		}

		$post_type    = get_option( 'post-generator-post-type', self::DEFAULT_POST_TYPE );
		$limit        = get_option( 'post-generator-limit', self::DEFAULT_LIMIT );
		$per_run      = get_option( 'post-generator-per-run', self::DEFAULT_PER_RUN );
		$titles       = stripslashes( get_option( 'post-generator-titles', Post_Generator_Constants::$titles ) );
		$contents     = stripslashes( get_option( 'post-generator-contents', Post_Generator_Constants::$contents ) );
		$min_groups   = get_option( 'post-generator-min-groups', self::DEFAULT_MIN_GROUPS );
		$max_groups   = get_option( 'post-generator-max-groups', self::DEFAULT_MAX_GROUPS );
		$min_comments = get_option( 'post-generator-min-comments', self::DEFAULT_MIN_COMMENTS );
		$max_comments = get_option( 'post-generator-max-comments', self::DEFAULT_MAX_COMMENTS );
		$new_user_probability = get_option( 'post-generator-new-user-probability', self::DEFAULT_NEW_USER_PROBABILITY );

		$titles = explode( "\n", $titles );
		sort( $titles );
		$titles = trim( implode( "\n", $titles ) );

		echo '<h1>';
		echo __( 'Generator', 'post-generator' );
		echo '</h1>';

		echo '<div class="generator-admin" style="margin-right:1em;">';

		echo '<div>';
		echo __( 'This produces demo posts for testing purposes.', 'post-generator' );
		echo ' ';
		echo __( 'It is <strong>NOT</strong> recommended to use this on a production site.', 'post-generator' );
		echo ' ';
		echo __( 'The plugin will <strong>NOT</strong> clean up the data it has created.', 'post-generator' );
		echo ' ';
		echo __( 'The plugin will create a <em>generator</em> user in the role of an <em>Editor</em>.', 'post-generator' );
		echo '</div>';

		echo '<div class="settings">';
		echo '<form name="settings" method="post" action="">';
		echo '<div>';

		echo '<p>';
		echo __( 'The continuous generator runs at most once per second, creating up to the indicated number of posts per run.', 'post-generator' );
		echo ' ';
		echo __( 'The continuous generator will try to create new posts until stopped, or the total number of posts reaches the indicated limit.', 'post-generator' );
		echo '</p>';

		echo '<p>';
		echo '<label>';
		echo __( 'Post Type', 'post-generator' );
		echo ' ';
		$post_types = get_post_types( array( 'public' => true ) );
		unset( $post_types['attachment'] );
		echo '<select name="post_type">';
		foreach( $post_types as $a_post_type ) {
			$post_type_object = get_post_type_object( $a_post_type );
			$label = $a_post_type;
			$labels = isset( $post_type_object->labels ) ? $post_type_object->labels : null;
			if ( ( $labels !== null ) && isset( $labels->singular_name ) ) {
				$label = __( $labels->singular_name );
			}
			$selected = ( $a_post_type == $post_type ? ' selected="selected" ' : '' );
			printf( '<option value="%s" %s>%s</option>', esc_attr( $a_post_type ), $selected, esc_html( $label ) );
		}
		echo '</select>';
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label>';
		echo __( 'Limit', 'post-generator' );
		echo ' ';
		echo sprintf( '<input type="text" name="limit" value="%d" />', $limit );
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label>';
		echo __( 'Per Run', 'post-generator' );
		echo ' ';
		echo sprintf( '<input type="text" name="per_run" value="%d" />', $per_run );
		echo ' ';
		echo sprintf( __( 'Maximum %d', 'post-generator' ), self::MAX_PER_RUN );
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label>';
		echo __( 'Titles', 'post-generator' );
		echo '<br/>';
		echo '<textarea name="titles" style="height:10em;width:90%;">';
		echo htmlentities( $titles );
		echo '</textarea>';
		echo '</label>';
		echo '</p>';

		echo '<p>';
		echo '<label>';
		echo __( 'Contents', 'post-generator' );
		echo '<br/>';
		echo '<textarea name="contents" style="height:20em;width:90%;">';
		echo htmlentities( $contents );
		echo '</textarea>';
		echo '</label>';
		echo '</p>';

		echo '<h2>';
		echo __( 'Comments', 'post-generator' );
		echo '</h2>';
		echo __( 'Indicate a number higher than 0 in the <em>Maximum</em> field to generate comments on generated posts.', 'post-generator' );
		echo '<p>';
		echo '<label>';
		echo __( 'Minimum', 'post-generator' );
		echo ' ';
		printf( '<input type="number" name="min-comments" value="%d" />', $min_comments );
		echo '</label>';
		echo '<br/>';
		echo '<label>';
		echo __( 'Maximum', 'post-generator' );
		echo ' ';
		printf( '<input type="number" name="max-comments" value="%d" />', $max_comments );
		echo '</label>';
		echo '<br/>';
		echo '<label>';
		echo __( 'Probability to create a new user', 'post-generator' );
		echo ' ';
		printf( '<input type="text" name="new-user-probability" value="%s" />', $new_user_probability );
		echo '</label>';
		echo '</p>';

		echo '<h2>';
		echo __( 'Groups', 'post-generator' );
		echo '</h2>';
		if ( class_exists( 'Groups_Group' ) ) {
			echo '<p>';
			echo __( 'To restrict access to generated posts randomly with one or more groups, indicate a number higher than 0 in the <em>Maximum</em> field.', 'post-generator' );
			echo '</p>';
			echo '<p>';
			echo '<label>';
			echo __( 'Minimum', 'post-generator' );
			echo ' ';
			printf( '<input type="number" name="min-groups" value="%d" />', $min_groups );
			echo '</label>';
			echo '<br/>';
			echo '<label>';
			echo __( 'Maximum', 'post-generator' );
			echo ' ';
			printf( '<input type="number" name="max-groups" value="%d" />', $max_groups );
			echo '</label>';
			echo '</p>';
		} else {
			echo '<p>';
			printf(
				__( 'To restrict access to generated posts randomly with one or more groups, you must install and activate the %s plugin.', 'post-generator' ),
				'<a href="https://wordpress.org/plugins/groups/">Groups</a>'
			);
			echo '</p>';
		}

		wp_nonce_field( 'admin', 'generator', true, true );

		echo '<div class="buttons">';
		echo sprintf( '<input class="button button-primary" type="submit" name="submit" value="%s" />', __( 'Save', 'post-generator' ) );
		echo '<input type="hidden" name="action" value="save" />';
		echo '</div>';

		echo '</div>';
		echo '</form>';
		echo '</div>';

		echo '<h2>';
		echo __( 'Reset', 'post-generator' );
		echo '</h2>';

		echo '<div class="reset">';
		echo '<form name="reset" method="post" action="">';
		echo '<div>';

		echo '<p>';
		echo __( 'Reset to defaults', 'post-generator' );
		echo '</p>';

		wp_nonce_field( 'admin', 'post-generator-reset', true, true );

		echo '<div class="buttons">';
		echo sprintf( '<input class="button button-primary" type="submit" name="submit" value="%s" />', __( 'Reset', 'post-generator' ) );
		echo '<input type="hidden" name="action" value="reset" />';
		echo '</div>';

		echo '</div>';
		echo '</form>';
		echo '</div>';

		echo '<h2>';
		echo __( 'Single Run', 'post-generator' );
		echo '</h2>';

		echo '<div class="generate">';
		echo '<form name="generate" method="post" action="">';
		echo '<div>';

		echo '<p>';
		echo '<label>';
		echo __( 'Generate up to &hellip;', 'post-generator' );
		echo ' ';
		echo '<input type="text" name="max" value="1" />';
		echo '</label>';
		echo '</p>';

		wp_nonce_field( 'admin', 'post-generate', true, true );

		echo '<div class="buttons">';
		echo sprintf( '<input class="button button-primary" type="submit" name="submit" value="%s" />', __( 'Run', 'post-generator' ) );
		echo '<input type="hidden" name="action" value="generate" />';
		echo '</div>';

		echo '</div>';
		echo '</form>';
		echo '</div>';

		echo '<h2>';
		echo __( 'Continuous AJAX Run', 'post-generator' );
		echo '</h2>';

		echo '<div class="buttons">';
		echo sprintf( '<input class="button" type="button" id="generator-run" name="generator-run" value="%s" />', __( 'Run', 'post-generator' ) );
		echo ' ';
		echo sprintf( '<input class="button" type="button" id="generator-stop" name="generator-stop" value="%s" />', __( 'Stop', 'post-generator' ) );
		echo '</div>';

		echo '<div id="generator-status"></div>';
		echo '<div id="generator-update"></div>';
		echo '<div id="generator-blinker"></div>';

		$js_nonce = wp_create_nonce( 'post-generator-js' );

		echo '<script type="text/javascript">';
		echo 'if ( typeof jQuery !== "undefined" ) {';
		echo 'jQuery(document).ready(function(){';
		echo sprintf( 'ixpostgen.limit = %d;', $limit );
		echo 'jQuery("#generator-run").click(function(e){';
		echo 'e.stopPropagation();';
		echo sprintf(
				'ixpostgen.start("%s");',
				add_query_arg(
						array(
								'generator' => $js_nonce
						),
						admin_url( 'admin-ajax.php' )
						)
				);
		echo '});'; // run click
		echo 'jQuery("#generator-stop").click(function(e){';
		echo 'e.stopPropagation();';
		echo 'ixpostgen.stop();';
		echo '});'; // stop click
		echo '});'; // ready
		echo '}';
		echo '</script>';

		echo '</div>'; // .generator-admin

	}

	/**
	 * Post generation cycle.
	 */
	public static function run( $n = self::MAX_PER_RUN ) {
		$limit = intval( get_option( 'post-generator-limit', self::DEFAULT_LIMIT ) );
		$post_type = get_option( 'post-generator-post-type', self::DEFAULT_POST_TYPE );
		$n_posts = self::get_post_count( $post_type );
		if ( $n_posts < $limit ) {
			$n = min( $n, $limit - $n_posts );
			$n = min( $n, self::MAX_PER_RUN );
			if ( $n > 0 ) {
				for ( $i = 0; $i < $n; $i++ ) {
					self::create_post();
				}
			}
		}
	}

	/**
	 * Returns the total number of published posts by type.
	 *
	 * @return int
	 */
	public static function get_post_count( $post_type = 'post' ) {
		global $wpdb;
		return intval( $wpdb->get_var( $wpdb->prepare(
			"SELECT count(*) FROM $wpdb->posts WHERE post_type = %s and post_status = 'publish'",
			$post_type
		) ) );
	}

	/**
	 * Generates a new post.
	 */
	public static function create_post() {
		$pgd = new Post_Generator_Data();
		$post_type = get_option( 'post-generator-post-type', self::DEFAULT_POST_TYPE );
		$user_id = self::get_user_id();
		$title = self::get_title();
		$i = 0;
		while( ( $i < 99 ) ) {
			if ( get_page_by_title( $title, OBJECT, $post_type ) ) {
				$title .= " " . self::get_title();
			} else {
				break;
			}
			$i++;
		}

		$content = self::get_content();
		$excerpt = self::get_excerpt( 3, $content );

		$post_id = wp_insert_post( array(
			'post_type' => $post_type,
			'post_title' => $title,
			'post_excerpt' => $excerpt,
			'post_content' => $content,
			'post_status' => 'publish',
			'post_author' => $user_id
		) );
		if ( !( $post_id instanceof WP_Error ) ) {

			if ( $post_type == 'post' ) {
				// add categories
				$terms = array();
				$cats = Post_Generator_Constants::$categories;
				$c_n = count( $cats );
				$c_max = rand( 1, 3 );
				for ( $i = 0; $i < $c_max ; $i++ ) {
					$terms[] = $cats[rand( 0, $c_n - 1 )];
				}
				wp_set_object_terms( $post_id, $terms, 'category', true );

				// add tags
				$tags = explode( " ", $title );
				$tags[] = 'progen';
				$potential = explode( " ", $content );
				$n = count( $potential );
				$t_max = rand( 1, 7 );
				for ( $i = 0; $i < $t_max ; $i++ ) {
					$tags[] = preg_replace( "/[^a-zA-Z0-9 ]/", '', $potential[rand( 0, $n-1 )] );
				}
				wp_set_object_terms( $post_id, $tags, 'post_tag', true );
			}

			if ( post_type_supports( $post_type, 'thumbnail' ) ) {
				// image
				$image = self::get_image();
				$image_name = self::get_image_name( $post_type );
				$r = wp_upload_bits( $image_name, null, $image );
				if ( !empty( $r ) && is_array( $r ) && !empty( $r['file'] ) ) {
					$filetype = wp_check_filetype( $r['file'] );
					$attachment_id = wp_insert_attachment(
						array(
							'post_title' => $title,
							'post_mime_type' => $filetype['type'],
							'post_status' => 'publish',
							'post_author' => $user_id
						),
						$r['file'],
						$post_id
					);
					if ( !empty( $attachment_id ) ) {
						include_once ABSPATH . 'wp-admin/includes/image.php';
						if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
							$meta = wp_generate_attachment_metadata( $attachment_id, $r['file'] );
							wp_update_attachment_metadata( $attachment_id, $meta );
						}
						update_post_meta( $post_id, '_thumbnail_id', $attachment_id );
					}
				}
			}

			// groups
			if ( class_exists( 'Groups_Post_Access' ) ) {
				$min_groups   = get_option( 'post-generator-min-groups', self::DEFAULT_MIN_GROUPS );
				$max_groups   = get_option( 'post-generator-max-groups', self::DEFAULT_MAX_GROUPS );
				if ( $max_groups > 0 ) {
					$group_ids = $pgd->get_random_group_ids( $min_groups,$max_groups );
					foreach( $group_ids as $group_id ) {
						Groups_Post_Access::create( array( 'post_id' => $post_id, 'group_id' => $group_id ) );
					}
				}
			}

			// comments
			$min_comments = get_option( 'post-generator-min-comments', self::DEFAULT_MIN_COMMENTS );
			$max_comments = get_option( 'post-generator-max-comments', self::DEFAULT_MAX_COMMENTS );
			$new_user_probability = get_option( 'post-generator-new-user-probability', self::DEFAULT_NEW_USER_PROBABILITY );
			if ( $max_comments > 0 ) {
				$n = rand( $min_comments, $max_comments );
				for ( $i = 1; $i < $n; $i++ ) {
					if ( $user_id = $pgd->create_or_get_random_user_id( $new_user_probability ) ) {
						$user = get_user_by( 'id', $user_id );
						wp_insert_comment( array(
							'comment_author' => $user->user_login,
							'comment_author_email' => $user->user_email,
							'comment_post_ID' => $post_id,
							'user_id' => $user_id,
							'comment_content' => $pgd->get_random_content(),
						) );
					}
				}
			}
		}
	}

	/**
	 * Returns the user ID of the generator user which is used as the
	 * author of posts generated. The user is created here if it doesn't
	 * exist yet with the role 'editor'.
	 *
	 * @return int generator user ID
	 */
	public static function get_user_id() {
		$user_id = get_current_user_id();
		$user = get_user_by( 'login', 'generator' );
		if ( $user instanceof WP_User ) {
			$user_id = $user->ID;
		} else {

			$user_pass = wp_generate_password( 12 );
			$maybe_user_id = wp_insert_user( array(
					'user_login' => 'generator',
					'role'       => 'editor',
					'user_pass'  => $user_pass
			) );
			if ( !( $maybe_user_id instanceof WP_Error ) ) {
				$user_id = $maybe_user_id;

				// notify admin
				$user = get_userdata( $user_id );
				$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

				$message  = sprintf( __( 'Generator user created on %s:', 'post-generator' ), $blogname ) . "\r\n\r\n";
				$message .= sprintf( __( 'Username: %s', 'post-generator' ), $user->user_login ) . "\r\n\r\n";
				$message .= sprintf( __( 'Password: %s', 'post-generator' ), $user_pass ) . "\r\n\r\n";
				$message .= __( 'The user has the role of Editor.', 'post-generator' ) . "\r\n";

				@wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Generator User', 'post-generator' ), $blogname ), $message);
			}
		}
		return $user_id;
	}

	/**
	 * Produce a title.
	 *
	 * @param int $n_words
	 * @return string
	 */
	public static function get_title( $n_words = 3 ) {
		$titles = trim( stripslashes( get_option( 'post-generator-titles', Post_Generator_Constants::$titles ) ) );
		$titles = explode( "\n", $titles );
		$title = array();
		$n = count( $titles );
		$n_words = rand( 1, $n_words );
		for ( $i = 1; $i <= $n_words ; $i++ ) {
			$title[] = $titles[rand( 0, $n - 1 )];
		}
		$title = implode( ' ', $title );
		return $title;
	}

	/**
	 * Produce the excerpt.
	 *
	 * @param int $n_lines
	 * @return string
	 */
	public static function get_excerpt( $n_lines = 3, $contents = null ) {
		if ( $contents === null ) {
			$contents = trim( stripslashes( get_option( 'post-generator-contents', Post_Generator_Constants::$contents ) ) );
		}
		$contents = str_ireplace( '</p>', "\n", $contents );
		$contents = str_ireplace( '<p>', '', $contents );
		$contents = explode( "\n", $contents );
		$content = array();
		$n = count( $contents );
		$n_lines = rand( 1, $n_lines );
		for ( $i = 1; $i <= $n_lines ; $i++ ) {
			$maybe_content = $contents[rand( 0, $n - 1 )];
			if ( !in_array( $maybe_content, $content ) ) {
				$content[] = $maybe_content;
			}
		}
		$content = "<p>" . implode( "</p><p>", $content ) . "</p>";
		return $content;
	}

	/**
	 * Produce content.
	 *
	 * @param int $n_lines
	 * @return string
	 */
	public static function get_content( $n_lines = 10 ) {
		$contents = trim( stripslashes( get_option( 'post-generator-contents', Post_Generator_Constants::$contents ) ) );
		$contents = explode( "\n", $contents );
		$content = array();
		$n = count( $contents );
		$n_lines = rand( 1, $n_lines );
		for ( $i = 1; $i <= $n_lines ; $i++ ) {
			$content[] = $contents[rand( 0, $n - 1 )];
		}
		$content = "<p>" . implode( "</p><p>", $content ) . "</p>";
		return $content;
	}

	/**
	 * Produce an image.
	 *
	 * @return string image data
	 */
	public static function get_image() {
		$output = '';
		if ( function_exists( 'imagepng' ) ) {
			$width = self::IMAGE_WIDTH;
			$height = self::IMAGE_HEIGHT;

			$image = imagecreatetruecolor( $width, $height );
			for( $i = 0; $i <= 11; $i++ ) {
				$x = rand( 0, $width );
				$y = rand( 0, $height );
				$w = rand( 1, $width );
				$h = rand( 1, $height );
				$red = rand( 0, 255 );
				$green = rand( 0, 255 );
				$blue  = rand( 0, 255 );
				$color = imagecolorallocate( $image, $red, $green, $blue );
				imagefilledrectangle(
						$image,
						$x - $w / 2,
						$y - $h / 2,
						$x + $w / 2,
						$y + $h / 2,
						$color
						);
			}

			ob_start();
			imagepng( $image );
			$output = ob_get_clean();
			imagedestroy( $image );
		} else {
			$image = file_get_contents( POST_GENERATOR_PLUGIN_URL . '/images/placeholder.png' );
			ob_start();
			echo $image;
			$output = ob_get_clean();
		}
		return $output;

	}

	/**
	 * Produce a name for an image.
	 * @return string
	 */
	public static function get_image_name( $post_type = 'post' ) {
		$t = time();
		$r = rand();
		return "$post_type-$t-$r.png";
	}

}
Post_Generator::init();
