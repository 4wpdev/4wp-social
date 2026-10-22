<?php
/**
 * Accounts tab: Bluesky (handle + app password, no developer app).
 *
 * @package ForWP\Social
 *
 * @var string $notice
 * @var string $error
 * @var string $notice_platform
 */

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Auth\Bluesky_Account;
use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\OAuth_Controller;

defined( 'ABSPATH' ) || exit;

$forwp_social_bsky       = Bluesky_Account::details();
$forwp_social_bsky_on    = Bluesky_Account::is_connected();
$forwp_social_bsky_creds = Credentials_Store::get( Bluesky_Account::SLUG );
$forwp_social_bsky_cur   = $forwp_social_bsky_on ? 3 : 1;
$forwp_social_bsky_state = static function ( int $step ) use ( $forwp_social_bsky_cur ): string {
	if ( $step < $forwp_social_bsky_cur ) {
		return 'is-done';
	}
	return $step === $forwp_social_bsky_cur ? 'is-current' : '';
};
?>
<section class="forwp-social-platform" id="forwp-social-bsky-app">
	<header class="forwp-social-platform__head">
		<h3 class="forwp-social-platform__title">
			<span class="dashicons dashicons-cloud" aria-hidden="true"></span>
			Bluesky
			<?php if ( $forwp_social_bsky_on ) : ?>
				<span class="forwp-social-badge forwp-social-badge--on"><?php esc_html_e( 'Connected', '4wp-social' ); ?></span>
			<?php else : ?>
				<span class="forwp-social-badge"><?php esc_html_e( 'Not set up', '4wp-social' ); ?></span>
			<?php endif; ?>
		</h3>
		<ol class="forwp-social-progress" aria-label="<?php esc_attr_e( 'Setup progress', '4wp-social' ); ?>">
			<?php foreach ( array( 1 => __( 'App password', '4wp-social' ), 2 => __( 'Connect', '4wp-social' ) ) as $forwp_social_n => $forwp_social_step_label ) : ?>
				<li class="<?php echo esc_attr( $forwp_social_bsky_state( $forwp_social_n ) ); ?>">
					<span class="forwp-social-progress__dot"><?php echo $forwp_social_n < $forwp_social_bsky_cur ? '&#10003;' : esc_html( (string) $forwp_social_n ); ?></span>
					<?php echo esc_html( $forwp_social_step_label ); ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</header>

	<?php /* Step 1 — create an app password. */ ?>
	<div class="forwp-social-step <?php echo esc_attr( $forwp_social_bsky_state( 1 ) ); ?>">
		<div class="forwp-social-step__num"><?php echo 1 < $forwp_social_bsky_cur ? '&#10003;' : '1'; ?></div>
		<div class="forwp-social-step__body">
			<h4 class="forwp-social-step__title"><?php esc_html_e( 'Create an app password', '4wp-social' ); ?></h4>
			<p class="forwp-social-muted"><?php esc_html_e( 'Bluesky needs no developer app. In the brand account open Settings → Privacy and security → App passwords, add one named "4WP Social" and copy it (xxxx-xxxx-xxxx-xxxx). Never use the main account password here.', '4wp-social' ); ?></p>
			<a class="button" href="https://bsky.app/settings/app-passwords" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Bluesky app passwords', '4wp-social' ); ?> <span class="dashicons dashicons-external" style="vertical-align:text-bottom;font-size:16px" aria-hidden="true"></span>
			</a>
		</div>
	</div>

	<?php /* Step 2 — connect. */ ?>
	<div class="forwp-social-step <?php echo esc_attr( $forwp_social_bsky_state( 2 ) ); ?>" id="<?php echo esc_attr( OAuth_Controller::connect_anchor( Bluesky_Account::SLUG ) ); ?>">
		<div class="forwp-social-step__num"><?php echo 2 < $forwp_social_bsky_cur ? '&#10003;' : '2'; ?></div>
		<div class="forwp-social-step__body">
			<h4 class="forwp-social-step__title"><?php esc_html_e( 'Connect the account', '4wp-social' ); ?></h4>

			<?php if ( Bluesky_Account::SLUG === $notice_platform ) : ?>
				<?php if ( '' !== $error ) : ?>
					<div class="forwp-social-callout forwp-social-callout--error" role="alert"><p><strong><?php esc_html_e( 'Connection failed:', '4wp-social' ); ?></strong> <?php echo esc_html( $error ); ?></p></div>
				<?php elseif ( isset( Admin_Menu::notices()[ $notice ] ) ) : ?>
					<div class="forwp-social-callout forwp-social-callout--ok" role="status"><p>&#10003; <?php echo esc_html( Admin_Menu::notices()[ $notice ] ); ?></p></div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $forwp_social_bsky_on ) : ?>
				<div class="forwp-social-callout forwp-social-callout--ok">
					<div class="forwp-social-account">
						<dl>
							<dt><?php esc_html_e( 'Account', '4wp-social' ); ?></dt>
							<dd><strong>@<?php echo esc_html( $forwp_social_bsky['handle'] ); ?></strong></dd>
							<?php if ( '' !== $forwp_social_bsky['connected_at'] ) : ?>
								<dt><?php esc_html_e( 'Connected', '4wp-social' ); ?></dt>
								<dd><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $forwp_social_bsky['connected_at'] ) ) ); ?></dd>
							<?php endif; ?>
							<dt>DID</dt>
							<dd><code><?php echo esc_html( $forwp_social_bsky['did'] ); ?></code></dd>
						</dl>
					</div>
				</div>
				<div class="forwp-social-actions">
					<a class="button" href="<?php echo esc_url( Bluesky_Account::action_url( Bluesky_Account::TEST_ACTION ) ); ?>"><?php esc_html_e( 'Test connection', '4wp-social' ); ?></a>
					<a class="button-link-delete" href="<?php echo esc_url( Bluesky_Account::action_url( Bluesky_Account::DISCONNECT_ACTION ) ); ?>"><?php esc_html_e( 'Disconnect', '4wp-social' ); ?></a>
				</div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( Bluesky_Account::CONNECT_ACTION ); ?>" />
					<?php wp_nonce_field( Bluesky_Account::CONNECT_ACTION ); ?>
					<div class="forwp-social-field">
						<label class="forwp-social-field__label" for="forwp-social-bsky-handle"><?php esc_html_e( 'Handle', '4wp-social' ); ?></label>
						<input type="text" id="forwp-social-bsky-handle" name="handle" value="<?php echo esc_attr( $forwp_social_bsky_creds['client_id'] ); ?>" placeholder="4wpdev.bsky.social" autocomplete="off" spellcheck="false" />
					</div>
					<div class="forwp-social-field">
						<label class="forwp-social-field__label" for="forwp-social-bsky-password"><?php esc_html_e( 'App password', '4wp-social' ); ?></label>
						<input type="password" id="forwp-social-bsky-password" name="app_password" value="" autocomplete="new-password" placeholder="<?php echo '' !== $forwp_social_bsky_creds['client_secret'] ? esc_attr__( '•••••••• saved', '4wp-social' ) : 'xxxx-xxxx-xxxx-xxxx'; ?>" />
					</div>
					<?php submit_button( __( 'Connect Bluesky account', '4wp-social' ), 'primary', 'submit', false ); ?>
				</form>
				<p class="forwp-social-field__help"><?php esc_html_e( 'The password is checked with Bluesky before it is saved, and stored encrypted.', '4wp-social' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>
