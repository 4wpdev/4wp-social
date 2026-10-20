<?php
/**
 * 4WP Social admin shell (aligned with 4WP Advanced Code / Notifications).
 *
 * @package ForWP\Social
 *
 * @var string                                                     $tab
 * @var string                                                     $notice
 * @var string                                                     $error
 * @var string                                                     $notice_platform
 * @var array<string, mixed>                                       $settings
 * @var array<string, ForWP\Social\Contracts\Module_Interface>     $modules
 * @var ForWP\Social\Auth\OAuth_Controller                         $oauth
 * @var array<string, ForWP\Social\Auth\OAuth_Provider_Interface>  $providers
 * @var array<int, object>                                         $jobs
 */

use ForWP\Social\Admin\Admin_Menu;

defined( 'ABSPATH' ) || exit;

$forwp_social_notices = Admin_Menu::notices();
$forwp_social_leads   = array(
	'modules'       => __( 'Switch modules on or off and set how posting behaves.', '4wp-social' ),
	'accounts'      => __( 'Connect the brand accounts the site publishes as. Three steps per platform: create an app, paste its keys, connect.', '4wp-social' ),
	'queue'         => __( 'Every publication, its status and the link on the platform.', '4wp-social' ),
	'documentation' => __( 'How publishing works: editor button, 4WP Drive packages and AI agents.', '4wp-social' ),
);
?>
<div class="wrap forwp-social-admin-shell">
	<h1 class="forwp-social-admin-heading">
		<span class="forwp-social-admin-heading__icon" aria-hidden="true"><span class="dashicons dashicons-share"></span></span>
		<span class="forwp-social-admin-heading__text">
			<span><?php esc_html_e( '4WP Social', '4wp-social' ); ?></span>
			<span class="forwp-social-admin-heading__version"><?php echo esc_html( 'v' . FORWP_SOCIAL_VERSION ); ?></span>
		</span>
	</h1>
	<p class="forwp-social-admin-lead"><?php echo esc_html( $forwp_social_leads[ $tab ] ); ?></p>

	<?php if ( '' === $notice_platform && isset( $forwp_social_notices[ $notice ] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $forwp_social_notices[ $notice ] ); ?></p></div>
	<?php endif; ?>
	<?php if ( '' === $notice_platform && '' !== $error ) : ?>
		<div class="notice notice-error is-dismissible"><p><strong><?php esc_html_e( 'Connection failed:', '4wp-social' ); ?></strong> <?php echo esc_html( $error ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper forwp-social-tabs" aria-label="<?php esc_attr_e( '4WP Social sections', '4wp-social' ); ?>">
		<?php foreach ( Admin_Menu::tabs() as $forwp_social_slug => $forwp_social_label ) : ?>
			<a href="<?php echo esc_url( Admin_Menu::tab_url( $forwp_social_slug ) ); ?>" class="nav-tab<?php echo $tab === $forwp_social_slug ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $forwp_social_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="forwp-social-admin-app">
		<?php require FORWP_SOCIAL_PATH . 'views/partials/tab-' . $tab . '.php'; ?>
	</div>
</div>
