<?php
/**
 * class-groups-admin.php
 *
 * Copyright (c) 2011 "kento" Karim Rahimpur www.itthinx.com
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
 * @author Karim Rahimpur
 * @package groups
 * @since groups 1.0.0
 */

if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Groups admin sections initialization.
 */
class Groups_Admin {

	/**
	 * The position of the Groups menu.
	 *
	 * @var int
	 */
	const MENU_POSITION = 38;

	/**
	 * Holds admin messages.
	 *
	 * @var string
	 */
	private static $messages = array();

	/**
	 * Sets up action hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_action( 'admin_head', array( __CLASS__, 'admin_head' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_menu' ) );
		add_filter( 'plugin_action_links_'. plugin_basename( GROUPS_FILE ), array( __CLASS__, 'plugin_action_links' ) );
		add_action( 'after_plugin_row_' . plugin_basename( GROUPS_FILE ), array( __CLASS__, 'after_plugin_row' ), 10, 3 );
	}

	/**
	* Hooks into admin_init.
	*
	* @see Groups_Admin::admin_menu()
	* @see Groups_Admin::admin_print_styles()
	* @link http://codex.wordpress.org/Function_Reference/wp_enqueue_script#Load_scripts_only_on_plugin_pages
	*/
	public static function admin_init() {
		global $groups_version;
		wp_register_style( 'groups_admin', GROUPS_PLUGIN_URL . 'css/groups_admin.css', array(), $groups_version );
		wp_register_style( 'groups_admin_post', GROUPS_PLUGIN_URL . 'css/groups_admin_post.css', array(), $groups_version );
		wp_register_style( 'groups_admin_user', GROUPS_PLUGIN_URL . 'css/groups_admin_user.css', array(), $groups_version );
		require_once GROUPS_VIEWS_LIB . '/class-groups-uie.php';
	}

	/**
	 * Loads styles for the Groups admin section.
	 *
	 * @see Groups_Admin::admin_menu()
	 */
	public static function admin_print_styles() {
		wp_enqueue_style( 'groups_admin' );
	}

	/**
	 * Loads scripts.
	 */
	public static function admin_print_scripts() {
		global $groups_version;
		// this one's currently empty
		// wp_enqueue_script( 'groups_admin', GROUPS_PLUGIN_URL . 'js/groups_admin.js', array( ), $groups_version );
		Groups_UIE::enqueue( 'select' );
	}

	/**
	 * Add a message to the list of messages displayed in the admin sections.
	 * The message is filtered using wp_filter_kses() and wrapped in a div
	 * with class 'updated' for messages of type 'info' and 'error' for
	 * those of type 'error'.
	 *
	 * @param string $message the message
	 * @param string $type type of message, defaults to 'info'
	 *
	 * @uses wp_filter_kses()
	 */
	public static function add_message( $message, $type = 'info' ) {
		if ( is_string( $message ) ) {
			$class = 'updated';
			switch( $type ) {
				case 'error' :
					$class = 'error';
			}
			self::$messages[] = '<div class="'.$class.'">' .  balanceTags( stripslashes( wp_filter_kses( $message ) ), true ) . '</div>';
		}
	}

	/**
	 * Returns the list of messages as a string.
	 * An empty string is returned if there are no messages.
	 *
	 * @return string
	 */
	public static function render_messages() {
		$output = '';
		if ( !empty( self::$messages ) ) {
			$output .= '<div class="groups messages">';
			$output .= implode( '', self::$messages ); // messages are already escaped when added using self::add_message()
			$output .= '</div>';
		}
		return $output;
	}

	/**
	 * Prints admin notices.
	 */
	public static function admin_notices() {
		global $groups_admin_messages;
		if ( !empty( $groups_admin_messages ) ) {
			foreach ( $groups_admin_messages as $msg ) {
				echo $msg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}
	}

	/**
	 * Use a context-sensitive menu item title.
	 */
	public static function admin_head() {
		global $submenu;
		if ( isset( $submenu['groups-admin'] ) ) {
			$submenu['groups-admin'][0][0] = _x( 'Groups', 'menu item title', 'groups' );
		}
	}

	/**
	 * Admin menu.
	 */
	public static function admin_menu() {

		include_once GROUPS_ADMIN_LIB . '/groups-admin-groups.php';
		include_once GROUPS_ADMIN_LIB . '/groups-admin-capabilities.php';
		include_once GROUPS_ADMIN_LIB . '/groups-admin-options.php';
		include_once GROUPS_ADMIN_LIB . '/groups-admin-add-ons.php';

		$pages = array();

		// main
		$page = add_menu_page(
			_x( 'Groups', 'page-title', 'groups' ),
			'Groups', // don't translate, reasons: a) Groups menu title consistency and b) http://core.trac.wordpress.org/ticket/18857 translation affects $screen->id
			GROUPS_ADMINISTER_GROUPS,
			'groups-admin',
			apply_filters( 'groups_add_menu_page_function', 'groups_admin_groups' ),
			'none', // @since 2.16.0 CSS icon from SVG
			self::MENU_POSITION
		);
		$pages[] = $page;
		add_action( 'admin_print_styles-' . $page, array( __CLASS__, 'admin_print_styles' ) );
		add_action( 'admin_print_scripts-' . $page, array( __CLASS__, 'admin_print_scripts' ) );

		if ( isset( $_POST[GROUPS_ADMIN_OPTIONS_NONCE] ) && wp_verify_nonce( $_POST[GROUPS_ADMIN_OPTIONS_NONCE], 'admin' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$show_tree_view = !empty( $_POST[GROUPS_SHOW_TREE_VIEW] );
		} else {
			$show_tree_view = Groups_Options::get_option( GROUPS_SHOW_TREE_VIEW, GROUPS_SHOW_TREE_VIEW_DEFAULT );
		}

		if ( $show_tree_view ) {
			include_once GROUPS_ADMIN_LIB . '/groups-admin-tree-view.php';
			$page = add_submenu_page(
				'groups-admin',
				__( 'Tree', 'groups' ),
				__( 'Tree', 'groups' ),
				GROUPS_ACCESS_GROUPS,
				'groups-admin-tree-view',
				apply_filters( 'groups_add_submenu_page_function', 'groups_admin_tree_view' )
			);
			$pages[] = $page;
			add_action( 'admin_print_styles-' . $page, array( __CLASS__, 'admin_print_styles' ) );
			add_action( 'admin_print_scripts-' . $page, array( __CLASS__, 'admin_print_scripts' ) );
		}

		// capabilities
		$page = add_submenu_page(
			'groups-admin',
			__( 'Groups Capabilities', 'groups' ),
			__( 'Capabilities', 'groups' ),
			GROUPS_ADMINISTER_GROUPS,
			'groups-admin-capabilities',
			apply_filters( 'groups_add_submenu_page_function', 'groups_admin_capabilities' )
		);
		$pages[] = $page;
		add_action( 'admin_print_styles-' . $page, array( __CLASS__, 'admin_print_styles' ) );
		add_action( 'admin_print_scripts-' . $page, array( __CLASS__, 'admin_print_scripts' ) );

		// options
		$page = add_submenu_page(
			'groups-admin',
			__( 'Groups options', 'groups' ),
			__( 'Options', 'groups' ),
			GROUPS_ADMINISTER_OPTIONS,
			'groups-admin-options',
			apply_filters( 'groups_add_submenu_page_function', 'groups_admin_options' )
		);
		$pages[] = $page;
		add_action( 'admin_print_styles-' . $page, array( __CLASS__, 'admin_print_styles' ) );
		add_action( 'admin_print_scripts-' . $page, array( __CLASS__, 'admin_print_scripts' ) );

		// add-ons
		$page = add_submenu_page(
			'groups-admin',
			__( 'Groups Add-Ons', 'groups' ),
			__( 'Add-Ons', 'groups' ),
			GROUPS_ACCESS_GROUPS,
			'groups-admin-add-ons',
			apply_filters( 'groups_add_submenu_page_function', 'groups_admin_add_ons' )
		);
		$pages[] = $page;
		add_action( 'admin_print_styles-' . $page, array( __CLASS__, 'admin_print_styles' ) );
		add_action( 'admin_print_scripts-' . $page, array( __CLASS__, 'admin_print_scripts' ) );

		do_action( 'groups_admin_menu', $pages );
	}

	/**
	 * Network admin menu.
	 */
	public static function network_admin_menu() {

		include_once GROUPS_ADMIN_LIB . '/groups-admin-options.php';

		$pages = array();

		// main
		$page = add_menu_page(
			_x( 'Groups', 'Network menu page title', 'groups' ),
			'Groups', // don't translate, see note on same in self::admin_menu()
			GROUPS_ADMINISTER_GROUPS,
			'groups-network-admin',
			apply_filters( 'groups_add_menu_page_function', 'groups_network_admin_options' ),
			'none' // @since 2.16.0 CSS icon from SVG
		);
		$pages[] = $page;
		add_action( 'admin_print_styles-' . $page, array( __CLASS__, 'admin_print_styles' ) );
		add_action( 'admin_print_scripts-' . $page, array( __CLASS__, 'admin_print_scripts' ) );

		do_action( 'groups_network_admin_menu', $pages );
	}

	/**
	 * Adds plugin links.
	 *
	 * @param array $links
	 * @param array $links with additional links
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		if ( Groups_User::current_user_can( GROUPS_ADMINISTER_OPTIONS ) ) {
			array_unshift(
				$links,
				'<a href="' . get_admin_url( null, 'admin.php?page=groups-admin-options' ) . '">' .
				_x( 'Options', 'Plugin action link', 'groups' ) .
				'</a>'
			);
		}
		if ( Groups_User::current_user_can( GROUPS_ADMINISTER_GROUPS ) ) {
			array_unshift(
				$links,
				'<a href="' . get_admin_url( null, 'admin.php?page=groups-admin' ) . '">' .
				_x( 'Groups', 'Plugin action link', 'groups' ) .
				'</a>'
			);
		}
		return $links;
	}

	/**
	 * Prints a warning when data is deleted on deactivation.
	 *
	 * @param string $plugin_file
	 * @param array $plugin_data
	 * @param string $status
	 */
	public static function after_plugin_row( $plugin_file, $plugin_data, $status ) {
		if ( $plugin_file == plugin_basename( GROUPS_FILE ) ) {
			$delete_data         = Groups_Options::get_option( 'groups_delete_data', false );
			$delete_network_data = Groups_Options::get_option( 'groups_network_delete_data', false );
			if (
				( is_plugin_active( $plugin_file ) && $delete_data && Groups_User::current_user_can( 'install_plugins' ) ) ||
				( is_plugin_active_for_network( $plugin_file ) && $delete_network_data  && Groups_User::current_user_can( 'manage_network_plugins' ) )
			) {
				echo '<tr class="active">';
				echo '<td>&nbsp;</td>';
				echo '<td colspan="2">';
				echo '<div style="border: 2px solid #dc3232; padding: 1em">';
				echo '<p>';
				echo '<strong>';
				echo esc_html__( 'Warning!', 'groups' );
				echo '</strong>';
				echo '</p>';
				echo '<p>';
				echo esc_html__( 'Groups is configured to delete its plugin data on deactivation.', 'groups' );
				echo '</p>';
				echo '</div>';
				echo '</td>';
				echo '</tr>';
			}
		}
	}
}
Groups_Admin::init();
