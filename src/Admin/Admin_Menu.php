<?php
/**
 * Admin screens: Dashboard · Calendar · Analytics · Settings (Platforms · Modules · Accounts · Queue · Documentation).
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Admin;

use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Core\Module_Registry;
use ForWP\Social\Core\Settings;
use ForWP\Social\Modules\Analytics\Analytics;
use ForWP\Social\Modules\Posting\Adapter_Registry;
use ForWP\Social\Modules\Posting\Calendar;
use ForWP\Social\Modules\Posting\Job_Repository;
use ForWP\Social\Modules\Posting\Platform_Registry;
use ForWP\Social\Modules\Posting\Queue;
use ForWP\Social\Rest\Rest_Platforms;

defined( 'ABSPATH' ) || exit;

/**
 * Registers 4WP Social admin screens and form handlers.
 */
final class Admin_Menu {

	public const MENU_SLUG = 'forwp-social';

	public const PLATFORMS_SLUG = 'forwp-social-platforms';

	public const CALENDAR_SLUG = 'forwp-social-calendar';

	public const ANALYTICS_SLUG = 'forwp-social-analytics';

	public const SETTINGS_SLUG = 'forwp-social-settings';

	private const SAVE_SETTINGS    = 'forwp_social_save_settings';
	private const SAVE_CREDENTIALS = 'forwp_social_save_credentials';
	private const RETRY_JOB        = 'forwp_social_retry_job';
	private const RUN_JOB          = 'forwp_social_run_job';
	private const DELETE_JOB       = 'forwp_social_delete_job';
	private const SAVE_REDIRECT    = 'forwp_social_save_redirect';
	private const FETCH_ANALYTICS  = 'forwp_social_fetch_analytics';
	private const IMPORT_ANALYTICS = 'forwp_social_import_analytics';

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
		add_action( 'admin_post_' . self::RUN_JOB, array( $this, 'handle_run_job' ) );
		add_action( 'admin_post_' . self::DELETE_JOB, array( $this, 'handle_delete_job' ) );
		add_action( 'admin_post_' . self::SAVE_REDIRECT, array( $this, 'handle_save_redirect' ) );
		add_action( 'admin_post_' . self::FETCH_ANALYTICS, array( $this, 'handle_fetch_analytics' ) );
		add_action( 'admin_post_' . self::IMPORT_ANALYTICS, array( $this, 'handle_import_analytics' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy' ) );
		add_filter( 'parent_file', array( $this, 'highlight_parent' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_settings' ) );
	}

	/**
	 * Old Settings links (page=forwp-social&tab=…) open the Settings page.
	 *
	 * @return void
	 */
	public function redirect_legacy(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only redirect.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( self::MENU_SLUG !== $page || ! isset( $_GET['tab'] ) ) {
			return;
		}
		$args = map_deep( wp_unslash( $_GET ), 'sanitize_text_field' );
		// phpcs:enable
		$args['page'] = self::SETTINGS_SLUG;

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * The hidden Platforms page opens the 4WP Social menu.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string
	 */
	public function highlight_parent( $parent_file ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return self::PLATFORMS_SLUG === $page ? self::MENU_SLUG : $parent_file;
	}

	/**
	 * The hidden Platforms page highlights Settings in the menu.
	 *
	 * @param string|null $submenu_file Current submenu file.
	 * @return string|null
	 */
	public function highlight_settings( $submenu_file ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		return self::PLATFORMS_SLUG === $page ? self::SETTINGS_SLUG : $submenu_file;
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
			array( $this, 'render_dashboard' ),
			'dashicons-share',
			59
		);
		add_submenu_page( self::MENU_SLUG, __( 'Dashboard', '4wp-social' ), __( 'Dashboard', '4wp-social' ), 'manage_options', self::MENU_SLUG, array( $this, 'render_dashboard' ) );
		add_submenu_page( self::MENU_SLUG, __( 'Calendar', '4wp-social' ), __( 'Calendar', '4wp-social' ), 'manage_options', self::CALENDAR_SLUG, array( $this, 'render_calendar' ) );
		add_submenu_page( self::MENU_SLUG, __( 'Analytics', '4wp-social' ), __( 'Analytics', '4wp-social' ), 'manage_options', self::ANALYTICS_SLUG, array( $this, 'render_analytics' ) );
		add_submenu_page( self::MENU_SLUG, __( 'Settings', '4wp-social' ), __( 'Settings', '4wp-social' ), 'manage_options', self::SETTINGS_SLUG, array( $this, 'render' ) );
		// Platforms is a Settings tab: a hidden page (parent options.php), reachable by URL but not in the menu.
		// remove_submenu_page() would deny access: WP then looks for the admin_page_* hook.
		add_submenu_page( 'options.php', __( 'Platforms', '4wp-social' ), __( 'Platforms', '4wp-social' ), 'manage_options', self::PLATFORMS_SLUG, array( $this, 'render_platforms' ) );
	}

	/**
	 * Dashboard: month at a glance, queue problems, accounts, analytics.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$month      = Calendar::month( '' );
		$events     = Calendar::events( $month );
		$stats      = Calendar::stats( $events );
		$scheduled  = Calendar::scheduled( '', 10 );
		$failed     = array_values(
			array_filter(
				( new Job_Repository() )->recent( 0, 200 ),
				static function ( $job ): bool {
					return Job_Repository::FAILED === (string) $job->status;
				}
			)
		);
		$platforms  = Platform_Registry::all( true );
		$connectors = Adapter_Registry::connectors();
		$sources    = Analytics::sources();
		$snapshots  = Analytics::snapshots();

		include FORWP_SOCIAL_PATH . 'views/dashboard-page.php';
	}

	/**
	 * Analytics page: one card per source with its last raw snapshot.
	 *
	 * @return void
	 */
	public function render_analytics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$notice = isset( $_GET['forwp_social_notice'] ) ? sanitize_key( wp_unslash( $_GET['forwp_social_notice'] ) ) : '';
		$error  = isset( $_GET['forwp_social_error'] ) ? sanitize_text_field( wp_unslash( $_GET['forwp_social_error'] ) ) : '';
		// phpcs:enable
		$sources   = Analytics::sources();
		$snapshots = Analytics::snapshots();

		include FORWP_SOCIAL_PATH . 'views/analytics-page.php';
	}

	/**
	 * URL of the Analytics page.
	 *
	 * @param array<string, string> $args Extra args.
	 */
	public static function analytics_url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::ANALYTICS_SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Fetch one analytics source now.
	 *
	 * @return void
	 */
	public function handle_fetch_analytics(): void {
		$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		check_admin_referer( self::FETCH_ANALYTICS . '_' . $source );
		$this->require_admin();

		$result = Analytics::refresh( $source );
		$args   = is_wp_error( $result )
			? array( 'forwp_social_error' => $result->get_error_message() )
			: array( 'forwp_social_notice' => 'analytics_fetched' );

		wp_safe_redirect( self::analytics_url( $args ) . '#forwp-social-source-' . sanitize_html_class( str_replace( '.', '-', $source ) ) );
		exit;
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

		if ( false !== strpos( $hook_suffix, self::PLATFORMS_SLUG ) ) {
			$this->enqueue_platforms();
		}
		if ( false !== strpos( $hook_suffix, self::CALENDAR_SLUG ) ) {
			$day_js = FORWP_SOCIAL_PATH . 'assets/calendar.js';
			wp_enqueue_script( 'forwp-social-calendar', FORWP_SOCIAL_URL . 'assets/calendar.js', array( 'wp-api-fetch' ), is_readable( $day_js ) ? (string) filemtime( $day_js ) : FORWP_SOCIAL_VERSION, true );
		}
	}

	/**
	 * Scripts of the Platforms page.
	 *
	 * @return void
	 */
	private function enqueue_platforms(): void {
		$js = FORWP_SOCIAL_PATH . 'assets/platforms.js';
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_media();
		wp_enqueue_script( 'forwp-social-platforms', FORWP_SOCIAL_URL . 'assets/platforms.js', array( 'jquery', 'jquery-ui-sortable', 'wp-api-fetch' ), is_readable( $js ) ? (string) filemtime( $js ) : FORWP_SOCIAL_VERSION, true );
		wp_localize_script(
			'forwp-social-platforms',
			'forwpSocialPlatforms',
			array(
				'data' => Rest_Platforms::payload(),
				'i18n' => array(
					'manual'        => __( 'Manual', '4wp-social' ),
					'empty'         => __( 'No platforms yet. Add the first one.', '4wp-social' ),
					'notConnected'  => __( 'not connected', '4wp-social' ),
					'connect'       => __( 'Connect account', '4wp-social' ),
					'off'           => __( 'off', '4wp-social' ),
					'on'            => __( 'On', '4wp-social' ),
					'edit'          => __( 'Edit', '4wp-social' ),
					'delete'        => __( 'Delete', '4wp-social' ),
					'save'          => __( 'Save', '4wp-social' ),
					'cancel'        => __( 'Cancel', '4wp-social' ),
					'name'          => __( 'Name', '4wp-social' ),
					'key'           => __( 'Key (as in meta.json)', '4wp-social' ),
					'source'        => __( 'Source', '4wp-social' ),
					'boundTo'       => __( 'bound to', '4wp-social' ),
					'openUrl'       => __( 'Open URL', '4wp-social' ),
					'format'        => __( 'Format', '4wp-social' ),
					'icon'          => __( 'Icon', '4wp-social' ),
					'chooseIcon'    => __( 'Choose image', '4wp-social' ),
					'useDefault'    => __( 'Use default', '4wp-social' ),
					'drag'          => __( 'Drag to reorder', '4wp-social' ),
					'confirmDelete' => __( 'Delete this platform? Published statuses on posts stay.', '4wp-social' ),
					'failed'        => __( 'Request failed.', '4wp-social' ),
				),
			)
		);
	}

	/**
	 * Platforms page.
	 *
	 * @return void
	 */
	public function render_platforms(): void {
		if ( current_user_can( 'manage_options' ) ) {
			include FORWP_SOCIAL_PATH . 'views/platforms-page.php';
		}
	}

	/**
	 * Calendar page: statistics of the month and its publications.
	 *
	 * @return void
	 */
	public function render_calendar(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$month  = Calendar::month( isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '' );
		$events = Calendar::events( $month );
		$stats  = Calendar::stats( $events );
		$site   = Calendar::site_events( $month );

		include FORWP_SOCIAL_PATH . 'views/calendar-page.php';
	}

	/**
	 * URL of the Calendar page.
	 *
	 * @param string $month YYYY-MM.
	 */
	public static function calendar_url( string $month ): string {
		return add_query_arg(
			array(
				'page'  => self::CALENDAR_SLUG,
				'month' => $month,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Import a file into a manual analytics source (LinkedIn).
	 *
	 * @return void
	 */
	public function handle_import_analytics(): void {
		$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		check_admin_referer( self::IMPORT_ANALYTICS . '_' . $source );
		$this->require_admin();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload array; tmp_name is checked with is_uploaded_file().
		$file = isset( $_FILES['file'] ) && is_array( $_FILES['file'] ) ? $_FILES['file'] : array();
		$tmp  = (string) ( $file['tmp_name'] ?? '' );
		$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );

		$result = '' !== $tmp && is_uploaded_file( $tmp )
			? Analytics::import( $source, $tmp, $name )
			: new \WP_Error( 'forwp_social_analytics_file', __( 'Choose a file to upload.', '4wp-social' ) );
		$args   = is_wp_error( $result )
			? array( 'forwp_social_error' => $result->get_error_message() )
			: array( 'forwp_social_notice' => 'analytics_imported' );

		wp_safe_redirect( self::analytics_url( $args ) . '#forwp-social-source-' . sanitize_html_class( str_replace( '.', '-', $source ) ) );
		exit;
	}

	/**
	 * Tabs.
	 *
	 * @return array<string, string>
	 */
	public static function tabs(): array {
		return array(
			'queue'         => __( 'Queue', '4wp-social' ),
			'platforms'     => __( 'Platforms', '4wp-social' ),
			'modules'       => __( 'Modules', '4wp-social' ),
			'accounts'      => __( 'Accounts', '4wp-social' ),
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
			'saved'              => __( 'Settings saved.', '4wp-social' ),
			'creds_saved'        => __( 'Credentials saved. Now connect the account (step 3).', '4wp-social' ),
			'connected'          => __( 'Account connected. Posting to this platform is ready.', '4wp-social' ),
			'disconnected'       => __( 'Account disconnected.', '4wp-social' ),
			'tested'             => __( 'Connection works: the token was refreshed and the account read.', '4wp-social' ),
			'retried'            => __( 'Job queued again. The queue runs every minute.', '4wp-social' ),
			'redirect_saved'     => __( 'Redirect URI saved. Put the same URI into the platform console.', '4wp-social' ),
			'job_run'            => __( 'Job run now. See its status below; processing jobs are checked by the queue every minute.', '4wp-social' ),
			'job_deleted'        => __( 'Job deleted from the queue.', '4wp-social' ),
			'analytics_fetched'  => __( 'Analytics fetched. Parts the platform refused show their error.', '4wp-social' ),
			'analytics_imported' => __( 'File imported.', '4wp-social' ),
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
		$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'queue';
		$notice = isset( $_GET['forwp_social_notice'] ) ? sanitize_key( wp_unslash( $_GET['forwp_social_notice'] ) ) : '';
		$error  = isset( $_GET['forwp_social_error'] ) ? sanitize_text_field( wp_unslash( $_GET['forwp_social_error'] ) ) : '';
		// Result of an action on one platform: shown inside that platform's box instead of on top.
		$notice_platform = isset( $_GET['forwp_social_platform'] ) ? sanitize_text_field( wp_unslash( $_GET['forwp_social_platform'] ) ) : '';
		// phpcs:enable
		$tab = array_key_exists( $tab, self::tabs() ) && 'platforms' !== $tab ? $tab : 'queue';

		$settings  = Settings::all();
		$modules   = Module_Registry::all();
		$oauth     = OAuth_Controller::instance();
		$providers = $oauth->providers();
		$jobs      = 'queue' === $tab ? ( new Job_Repository() )->recent( 0, 400 ) : array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view state.
		$queue_view = isset( $_GET['view'] ) && 'month' === $_GET['view'] ? 'month' : 'list';
		$month      = Calendar::month( isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '' );
		// phpcs:enable
		$events = 'queue' === $tab && 'month' === $queue_view ? Calendar::events( $month ) : array();

		include FORWP_SOCIAL_PATH . 'views/settings-page.php';
	}

	/**
	 * URL of a tab.
	 *
	 * @param string                $tab  Tab.
	 * @param array<string, string> $args Extra args.
	 */
	public static function tab_url( string $tab, array $args = array() ): string {
		if ( 'platforms' === $tab ) {
			return add_query_arg( array_merge( array( 'page' => self::PLATFORMS_SLUG ), $args ), admin_url( 'admin.php' ) );
		}

		return add_query_arg(
			array_merge(
				array(
					'page' => self::SETTINGS_SLUG,
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
				isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '',
				isset( $_POST['config_id'] ) ? array( 'config_id' => sanitize_text_field( wp_unslash( $_POST['config_id'] ) ) ) : null
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

		wp_safe_redirect( self::tab_url( 'queue', array( 'forwp_social_notice' => 'retried' ) + self::queue_filter_arg() ) );
		exit;
	}

	/**
	 * Keep the queue filter after an action.
	 *
	 * @return array<string, string>
	 */
	private static function queue_filter_arg(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked by the caller.
		$filter = isset( $_POST['forwp_social_filter'] ) ? sanitize_key( wp_unslash( $_POST['forwp_social_filter'] ) ) : '';

		return '' !== $filter ? array( 'filter' => $filter ) : array();
	}

	/**
	 * Run a queued or failed job now, without waiting for WP-Cron.
	 *
	 * @return void
	 */
	public function handle_run_job(): void {
		$id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		check_admin_referer( self::RUN_JOB . '_' . $id );
		$this->require_admin();

		$repo = new Job_Repository();
		$job  = $repo->find( $id );
		if ( $job && Job_Repository::PROCESSING === $job->status ) {
			Queue::run( $id );
		} elseif ( $job && Job_Repository::is_actionable( $job ) ) {
			$fields = array( 'run_at' => current_time( 'mysql', true ) );
			if ( Job_Repository::FAILED === $job->status ) {
				$fields += array(
					'status'     => Job_Repository::QUEUED,
					'attempts'   => 0,
					'reference'  => '',
					'started_at' => null,
					'message'    => '',
				);
			}
			$repo->update( $id, $fields );
			Queue::run( $id );
		}

		wp_safe_redirect( self::tab_url( 'queue', array( 'forwp_social_notice' => 'job_run' ) + self::queue_filter_arg() ) );
		exit;
	}

	/**
	 * Delete a job that is not published and not processing.
	 *
	 * @return void
	 */
	public function handle_delete_job(): void {
		$id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		check_admin_referer( self::DELETE_JOB . '_' . $id );
		$this->require_admin();

		$repo = new Job_Repository();
		$job  = $repo->find( $id );
		if ( $job && Job_Repository::is_actionable( $job ) ) {
			$repo->delete( $id );
		}

		wp_safe_redirect( self::tab_url( 'queue', array( 'forwp_social_notice' => 'job_deleted' ) + self::queue_filter_arg() ) );
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
			'run_job'          => self::RUN_JOB,
			'delete_job'       => self::DELETE_JOB,
			'save_redirect'    => self::SAVE_REDIRECT,
			'fetch_analytics'  => self::FETCH_ANALYTICS,
			'import_analytics' => self::IMPORT_ANALYTICS,
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
