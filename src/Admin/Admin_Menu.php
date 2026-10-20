<?php
/**
 * Admin screen: Modules · Accounts · Queue.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Admin;

use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Core\Module_Registry;
use ForWP\Social\Core\Settings;
use ForWP\Social\Modules\Posting\Job_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Registers 4WP Social admin screens and form handlers.
 */
final class Admin_Menu {

	public const MENU_SLUG = 'forwp-social';

	private const SAVE_SETTINGS    = 'forwp_social_save_settings';
	private const SAVE_CREDENTIALS = 'forwp_social_save_credentials';
	private const RETRY_JOB        = 'forwp_social_retry_job';
	private const SAVE_REDIRECT    = 'forwp_social_save_redirect';

	/**
	 * @var self|null
	 */
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	/**
	 * @return void
	 */
	public function boot(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::SAVE_SETTINGS, array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_' . self::SAVE_CREDENTIALS, array( $this, 'handle_save_credentials' ) );
		add_action( 'admin_post_' . self::RETRY_JOB, array( $this, 'handle_retry_job' ) );
		add_action( 'admin_post_' . self::SAVE_REDIRECT, array( $this, 'handle_save_redirect' ) );
	}

	/**
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( '4WP Social', '4wp-social' ),
			__( '4WP Social', '4wp-social' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-share',
			59
		);
	}

	/**
	 * @param string $hook_suffix Screen hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}

		$css = FORWP_SOCIAL_PATH . 'assets/admin.css';
		$js  = FORWP_SOCIAL_PATH . 'assets/admin.js';
		wp_enqueue_style( 'forwp-social-admin', FORWP_SOCIAL_URL . 'assets/admin.css', array(), is_readable( $css ) ? (string) filemtime( $css ) : FORWP_SOCIAL_VERSION );
		wp_enqueue_script( 'forwp-social-admin', FORWP_SOCIAL_URL . 'assets/admin.js', array(), is_readable( $js ) ? (string) filemtime( $js ) : FORWP_SOCIAL_VERSION, true );
	}

	/**
	 * Tabs.
	 *
	 * @return array<string, string>
	 */
	public static function tabs(): array {
		return array(
			'modules'       => __( 'Modules', '4wp-social' ),
			'accounts'      => __( 'Accounts', '4wp-social' ),
			'queue'         => __( 'Queue', '4wp-social' ),
			'documentation' => __( 'Documentation', '4wp-social' ),
		);
	}

	/**
	 * Flash notices by key.
	 *
	 * @return array<string, string>
	 */
	public static function notices(): array {
		return array(
			'saved'        => __( 'Settings saved.', '4wp-social' ),
			'creds_saved'  => __( 'Credentials saved. Now connect the account (step 3).', '4wp-social' ),
			'connected'    => __( 'Account connected. Posting to this platform is ready.', '4wp-social' ),
			'disconnected' => __( 'Account disconnected.', '4wp-social' ),
			'tested'       => __( 'Connection works: the token was refreshed and the account read.', '4wp-social' ),
			'retried'      => __( 'Job queued again. The queue runs every minute.', '4wp-social' ),
			'redirect_saved' => __( 'Redirect URI saved. Put the same URI into the platform console.', '4wp-social' ),
		);
	}

	/**
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'modules';
		$notice = isset( $_GET['forwp_social_notice'] ) ? sanitize_key( wp_unslash( $_GET['forwp_social_notice'] ) ) : '';
		$error  = isset( $_GET['forwp_social_error'] ) ? sanitize_text_field( wp_unslash( $_GET['forwp_social_error'] ) ) : '';
		// Result of an action on one platform: shown inside that platform's box instead of on top.
		$notice_platform = isset( $_GET['forwp_social_platform'] ) ? sanitize_text_field( wp_unslash( $_GET['forwp_social_platform'] ) ) : '';
		// phpcs:enable
		$tab = array_key_exists( $tab, self::tabs() ) ? $tab : 'modules';

		$settings  = Settings::all();
		$modules   = Module_Registry::all();
		$oauth     = OAuth_Controller::instance();
		$providers = $oauth->providers();
		$jobs      = 'queue' === $tab ? ( new Job_Repository() )->recent( 0, 100 ) : array();

		include FORWP_SOCIAL_PATH . 'views/settings-page.php';
	}

	/**
	 * URL of a tab.
	 *
	 * @param string                $tab  Tab.
	 * @param array<string, string> $args Extra args.
	 */
	public static function tab_url( string $tab, array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::MENU_SLUG,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * @return void
	 */
	public function handle_save_settings(): void {
		check_admin_referer( self::SAVE_SETTINGS );
		$this->require_admin();

		$raw = isset( $_POST['forwp_social_settings'] ) && is_array( $_POST['forwp_social_settings'] )
			? map_deep( wp_unslash( $_POST['forwp_social_settings'] ), 'sanitize_text_field' )
			: array();
		Settings::save( $raw );

		wp_safe_redirect( self::tab_url( 'modules', array( 'forwp_social_notice' => 'saved' ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public function handle_save_credentials(): void {
		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : '';
		check_admin_referer( self::SAVE_CREDENTIALS . '_' . $platform );
		$this->require_admin();

		if ( OAuth_Controller::instance()->provider( $platform ) ) {
			Credentials_Store::save(
				$platform,
				isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
				isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : ''
			);
		}

		wp_safe_redirect( self::tab_url( 'accounts', array( 'forwp_social_notice' => 'creds_saved' ) ) . '#forwp-social-' . sanitize_html_class( str_replace( '.', '-', $platform ) ) );
		exit;
	}

	/**
	 * Development mode: save or reset the redirect URI of a platform.
	 *
	 * @return void
	 */
	public function handle_save_redirect(): void {
		$platform = isset( $_POST['platform'] ) ? sanitize_text_field( wp_unslash( $_POST['platform'] ) ) : '';
		check_admin_referer( self::SAVE_REDIRECT . '_' . $platform );
		$this->require_admin();

		if ( OAuth_Controller::instance()->provider( $platform ) ) {
			$uri = isset( $_POST['redirect_uri'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_uri'] ) ) : '';
			OAuth_Controller::save_redirect_override( $platform, isset( $_POST['reset'] ) ? '' : $uri );
		}

		wp_safe_redirect( self::tab_url( 'accounts', array( 'forwp_social_notice' => 'redirect_saved' ) ) . '#forwp-social-' . sanitize_html_class( str_replace( '.', '-', $platform ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public function handle_retry_job(): void {
		$id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		check_admin_referer( self::RETRY_JOB . '_' . $id );
		$this->require_admin();

		$repo = new Job_Repository();
		if ( $repo->find( $id ) ) {
			$repo->update(
				$id,
				array(
					'status'     => Job_Repository::QUEUED,
					'attempts'   => 0,
					'reference'  => '',
					'started_at' => null,
					'message'    => '',
					'run_at'     => current_time( 'mysql', true ),
				)
			);
		}

		wp_safe_redirect( self::tab_url( 'queue', array( 'forwp_social_notice' => 'retried' ) ) );
		exit;
	}

	/**
	 * Action names for the view.
	 *
	 * @return array<string, string>
	 */
	public static function actions(): array {
		return array(
			'save_settings'    => self::SAVE_SETTINGS,
			'save_credentials' => self::SAVE_CREDENTIALS,
			'retry_job'        => self::RETRY_JOB,
			'save_redirect'    => self::SAVE_REDIRECT,
		);
	}

	/**
	 * @return void
	 */
	private function require_admin(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change 4WP Social settings.', '4wp-social' ) );
		}
	}
}
