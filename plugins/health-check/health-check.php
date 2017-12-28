<?php
/*
	Plugin Name: Health Check
	Plugin URI: http://wordpress.org/plugins/health-check/
	Description: Checks the health of your WordPress install.
	Author: The WordPress.org community
	Version: 0.7.0
	Author URI: http://wordpress.org/plugins/health-check/
	Text Domain: health-check
 */
if ( ! defined( 'ABSPATH' ) ) {
	die( 'We\'re sorry, but you can not directly access this file.' );
}

define( 'HEALTH_CHECK_PHP_MIN_VERSION', '5.2.4' );
define( 'HEALTH_CHECK_PHP_REC_VERSION', '7.0' );
define( 'HEALTH_CHECK_MYSQL_MIN_VERSION', '5.0' );
define( 'HEALTH_CHECK_MYSQL_REC_VERSION', '5.6' );
define( 'HEALTH_CHECK_PLUGIN_VERSION', '0.7.0' );
define( 'HEALTH_CHECK_PLUGIN_DIRECTORY', plugin_dir_path( __FILE__ ) );

class HealthCheck {

	public function __construct() {
		$this->init();
	}

	public function init() {
		add_action( 'plugins_loaded', array( $this, 'load_i18n' ) );

		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_filter( 'plugin_row_meta', array( $this, 'settings_link' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueues' ) );

		add_action( 'init', array( $this, 'start_troubleshoot_mode' ) );

		add_action( 'wp_ajax_health-check-loopback-no-plugins', array( 'Health_Check_Loopback', 'loopback_no_plugins' ) );
		add_action( 'wp_ajax_health-check-loopback-individual-plugins', array( 'Health_Check_Loopback', 'loopback_test_individual_plugins' ) );
	}

	public function start_troubleshoot_mode() {
		if ( ! isset( $_POST['health-check-troubleshoot-mode'] ) || ! isset( $_POST['health-check-troubleshoot-mode-confirmed'] ) ) {
			return;
		}

		$loopback_hash = md5( rand() );
		update_option( 'health-check-disable-plugin-hash', $loopback_hash );

		setcookie( 'health-check-disable-plugins', $loopback_hash, 0, COOKIEPATH, COOKIE_DOMAIN );
	}

	public function load_i18n() {
		load_plugin_textdomain( 'health-check', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	public function enqueues() {
		// Don't enqueue anything unless we're on the health check page
		if ( ! isset( $_GET['page'] ) || 'health-check' !== $_GET['page'] ) {
			return;
		}

		wp_enqueue_style( 'health-check', plugins_url( '/assets/css/health-check.css', __FILE__ ), array(), HEALTH_CHECK_PLUGIN_VERSION );

		wp_enqueue_script( 'health-check', plugins_url( '/assets/javascript/health-check.js', __FILE__ ), array( 'jquery' ), HEALTH_CHECK_PLUGIN_VERSION, true );

		wp_localize_script( 'health-check', 'health_check', array(
			'string' => array(
				'please_wait' => esc_html__( 'Please wait...', 'health-check' )
			)
		) );
	}

	public function action_admin_menu() {
		add_dashboard_page( __( 'Health Check', 'health-check' ), __( 'Health Check', 'health-check' ), 'manage_options', 'health-check', array( $this, 'dashboard_page' ) );
	}

	public function settings_link( $meta, $name ) {
		if ( plugin_basename( __FILE__ ) === $name ) {
			$meta[] = sprintf( '<a href="%s">' . __( 'Health Check', 'health-check' ) . '</a>', menu_page_url( 'health-check', false ) );
		}

		return $meta;
	}

	public function dashboard_page() {
		?>
		<div class="wrap">
			<h1>
				<?php _e( 'Health Check', 'health-check' ); ?>
			</h1>

			<?php
			$tabs = array(
				'health-check' => esc_html__( 'Health Check', 'health-check' ),
				'debug'        => esc_html__( 'Debug information', 'health-check' ),
				'troubleshoot' => esc_html__( 'Troubleshooting', 'health-check' ),
				'phpinfo'      => esc_html__( 'PHP Information', 'health-check' )
			);

			$current_tab = ( isset( $_GET['tab'] ) ? $_GET['tab'] : 'health-check' );
			?>

			<h2 class="nav-tab-wrapper wp-clearfix">
				<?php
				foreach( $tabs as $tab => $label ) {
					printf(
						'<a href="%s" class="nav-tab %s">%s</a>',
						sprintf(
							'%s&tab=%s',
							menu_page_url( 'health-check', false ),
							$tab
						),
						( $current_tab === $tab ? 'nav-tab-active' : '' ),
						$label
					);
				}
				?>
			</h2>

			<?php
			switch ( $current_tab ) {
				case 'debug':
					include_once( dirname( __FILE__ ) . '/pages/debug-data.php' );
					break;
				case 'phpinfo':
					include_once( dirname( __FILE__ ) . '/pages/phpinfo.php' );
					break;
				case 'troubleshoot':
					include_once( dirname( __FILE__ ) . '/pages/troubleshoot.php' );
					break;
				case 'health-check':
				default:
					include_once( dirname( __FILE__ ) . '/pages/health-check.php' );
			}
			?>
		</div>
		<?php
	}

	static function display_notice( $message, $status = 'success' ) {
		printf(
			'<div class="notice notice-%s inline">',
			$status
		);

		printf(
			'<p>%s</p>',
			$message
		);

		echo '</div>';
	}

	static function json_check() {
		$extension_loaded = extension_loaded( 'json' );
		$functions_exist = function_exists( 'json_encode' ) && function_exists( 'json_decode' );
		$functions_work = function_exists( 'json_encode' ) && ( '' != json_encode( 'my test string' ) );

		return $extension_loaded && $functions_exist && $functions_work;
	}
}

/* Initialize ourselves */
new HealthCheck();

// Include classes used by our plugin
require_once( dirname( __FILE__ ) . '/includes/class-health-check-auto-updates.php' );
require_once( dirname( __FILE__ ) . '/includes/class-health-check-wp-cron.php' );
require_once( dirname( __FILE__ ) . '/includes/class-health-check-debug-data.php' );
require_once( dirname( __FILE__ ) . '/includes/class-health-check-loopback.php' );
require_once( dirname( __FILE__ ) . '/includes/class-health-check-troubleshoot.php' );
