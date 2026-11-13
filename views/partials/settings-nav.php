<?php
/**
 * Settings tabs. Shared by the Settings page and the Platforms page
 * (Platforms is its own page because assets/platforms.js renders it, but it reads as a tab).
 *
 * @package ForWP\Social
 *
 * @var string $tab Active tab.
 */

use ForWP\Social\Admin\Admin_Menu;

defined( 'ABSPATH' ) || exit;
?>
<nav class="nav-tab-wrapper forwp-social-tabs" aria-label="<?php esc_attr_e( '4WP Social settings', '4wp-social' ); ?>">
	<?php foreach ( Admin_Menu::tabs() as $forwp_social_slug => $forwp_social_label ) : ?>
		<a href="<?php echo esc_url( Admin_Menu::tab_url( $forwp_social_slug ) ); ?>" class="nav-tab<?php echo $tab === $forwp_social_slug ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $forwp_social_label ); ?></a>
	<?php endforeach; ?>
</nav>
