<?php
/**
 * Platforms page. The list is rendered by assets/platforms.js.
 *
 * @package ForWP\Social
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap forwp-social-admin-shell">
	<h1 class="forwp-social-admin-heading">
		<span class="forwp-social-admin-heading__icon" aria-hidden="true"><span class="dashicons dashicons-share"></span></span>
		<span class="forwp-social-admin-heading__text"><span><?php esc_html_e( 'Settings', '4wp-social' ); ?></span></span>
	</h1>
	<?php
	$tab = 'platforms'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local to Admin_Menu::render_platforms().
	require FORWP_SOCIAL_PATH . 'views/partials/settings-nav.php';
	?>
	<p class="forwp-social-admin-lead"><?php esc_html_e( 'Each platform is manual or bound to one connector of the plugin. A connector can be bound only once. The mode in meta.json wins for a single post.', '4wp-social' ); ?></p>

	<div class="forwp-social-platform-actions">
		<button type="button" class="button button-primary" id="forwp-social-platform-add"><?php esc_html_e( 'Add platform', '4wp-social' ); ?></button>
	</div>
	<div id="forwp-social-platform-msg" class="forwp-social-platform-msg" role="alert"></div>
	<div id="forwp-social-platform-new"></div>
	<ul id="forwp-social-platform-list" class="forwp-social-platform-list"></ul>
</div>
