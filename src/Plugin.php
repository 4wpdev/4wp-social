<?php
/**
 * Plugin bootstrap.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social;

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Auth\Bluesky_Account;
use ForWP\Social\Auth\OAuth_Controller;
use ForWP\Social\Core\Module_Registry;
use ForWP\Social\Database\Schema;
use ForWP\Social\Modules\Posting\Queue;
use ForWP\Social\Rest\Rest_Jobs;
use ForWP\Social\Rest\Rest_Link;
use ForWP\Social\Rest\Rest_Oauth;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin singleton.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Boot hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		Schema::maybe_upgrade();
		Queue::register_interval();
		OAuth_Controller::instance()->boot();
		Bluesky_Account::boot();
		Admin_Menu::instance()->boot();
		Rest_Oauth::register();
		Rest_Jobs::register();
		Rest_Link::register();

		// Modules register on init so third-party plugins can add theirs via forwp_social_modules.
		add_action( 'init', array( Module_Registry::class, 'boot_enabled' ), 5 );
	}
}
