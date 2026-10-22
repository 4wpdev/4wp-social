<?php
/**
 * Accounts tab: create app → paste keys → connect, per platform.
 *
 * @package ForWP\Social
 *
 * @var ForWP\Social\Auth\OAuth_Controller                        $oauth
 * @var array<string, ForWP\Social\Auth\OAuth_Provider_Interface> $providers
 */

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Admin\Redirect_Check;
use ForWP\Social\Auth\Credentials_Store;
use ForWP\Social\Auth\OAuth_Controller;

defined( 'ABSPATH' ) || exit;

$forwp_social_actions = Admin_Menu::actions();
$forwp_social_html    = array(
	'strong' => array(),
	'code'   => array(),
	'em'     => array(),
	'a'      => array(
		'href'   => true,
		'target' => true,
		'rel'    => true,
	),
);
$forwp_social_icons   = array(
	'youtube.com' => 'dashicons-youtube',
	'tiktok.com'  => 'dashicons-video-alt3',
);
?>
<div class="forwp-social-intro-card">
	<h2 class="forwp-social-intro-card__title"><?php esc_html_e( 'Brand accounts', '4wp-social' ); ?></h2>
	<p class="forwp-social-intro-card__text">
		<?php esc_html_e( 'The site publishes as your channel or profile. Each platform needs its own developer app: you create it once in the platform console, paste its two keys here and press Connect. Keys and tokens are stored encrypted. Visitor sign-in is not here — that is 4WP Account.', '4wp-social' ); ?>
	</p>
</div>

<?php foreach ( $providers as $forwp_social_key => $forwp_social_provider ) : ?>
	<?php
	$forwp_social_guide   = $forwp_social_provider->get_setup_guide();
	$forwp_social_labels  = $forwp_social_provider->get_credential_labels();
	$forwp_social_creds   = Credentials_Store::get( $forwp_social_key );
	$forwp_social_has_key = Credentials_Store::has( $forwp_social_key );
	$forwp_social_conn    = $oauth->connection_details( $forwp_social_key );
	$forwp_social_uri     = $oauth->redirect_uri( $forwp_social_key );
	$forwp_social_issues  = Redirect_Check::issues( $forwp_social_uri, $forwp_social_guide );
	$forwp_social_anchor  = 'forwp-social-' . sanitize_html_class( str_replace( '.', '-', $forwp_social_key ) );
	$forwp_social_uri_id  = $forwp_social_anchor . '-uri';

	// Step state: 1 app, 2 keys, 3 connected.
	$forwp_social_current = $forwp_social_conn['connected'] ? 4 : ( $forwp_social_has_key ? 3 : 1 );
	$forwp_social_state   = static function ( int $step ) use ( $forwp_social_current ): string {
		if ( $step < $forwp_social_current ) {
			return 'is-done';
		}
		return $step === $forwp_social_current ? 'is-current' : '';
	};
	$forwp_social_steps   = array(
		1 => __( 'Create app', '4wp-social' ),
		2 => __( 'Paste keys', '4wp-social' ),
		3 => __( 'Connect', '4wp-social' ),
	);
	?>
	<section class="forwp-social-platform" id="<?php echo esc_attr( $forwp_social_anchor ); ?>">
		<header class="forwp-social-platform__head">
			<h3 class="forwp-social-platform__title">
				<span class="dashicons <?php echo esc_attr( $forwp_social_icons[ $forwp_social_key ] ?? 'dashicons-share' ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $forwp_social_provider->get_label() ); ?>
				<?php if ( $forwp_social_conn['connected'] ) : ?>
					<span class="forwp-social-badge forwp-social-badge--on"><?php esc_html_e( 'Connected', '4wp-social' ); ?></span>
				<?php elseif ( $forwp_social_has_key ) : ?>
					<span class="forwp-social-badge forwp-social-badge--warn"><?php esc_html_e( 'Keys saved — not connected', '4wp-social' ); ?></span>
				<?php else : ?>
					<span class="forwp-social-badge"><?php esc_html_e( 'Not set up', '4wp-social' ); ?></span>
				<?php endif; ?>
			</h3>
			<ol class="forwp-social-progress" aria-label="<?php esc_attr_e( 'Setup progress', '4wp-social' ); ?>">
				<?php foreach ( $forwp_social_steps as $forwp_social_n => $forwp_social_step_label ) : ?>
					<li class="<?php echo esc_attr( $forwp_social_state( $forwp_social_n ) ); ?>">
						<span class="forwp-social-progress__dot"><?php echo $forwp_social_n < $forwp_social_current ? '&#10003;' : esc_html( (string) $forwp_social_n ); ?></span>
						<?php echo esc_html( $forwp_social_step_label ); ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</header>

		<?php /* Step 1 — create the app. */ ?>
		<div class="forwp-social-step <?php echo esc_attr( $forwp_social_state( 1 ) ); ?>">
			<div class="forwp-social-step__num"><?php echo 1 < $forwp_social_current ? '&#10003;' : '1'; ?></div>
			<div class="forwp-social-step__body">
				<h4 class="forwp-social-step__title"><?php esc_html_e( 'Create an app in the developer console', '4wp-social' ); ?></h4>

				<p class="forwp-social-field__label"><?php esc_html_e( 'Redirect URI — paste it exactly as shown:', '4wp-social' ); ?></p>
				<div class="forwp-social-copy">
					<input type="text" readonly id="<?php echo esc_attr( $forwp_social_uri_id ); ?>" value="<?php echo esc_attr( $forwp_social_uri ); ?>" onclick="this.select()" />
					<button type="button" class="button forwp-social-copy-btn" data-target="<?php echo esc_attr( $forwp_social_uri_id ); ?>" data-copied="<?php esc_attr_e( 'Copied', '4wp-social' ); ?>"><?php esc_html_e( 'Copy', '4wp-social' ); ?></button>
				</div>

				<?php if ( OAuth_Controller::is_local_site() ) : ?>
					<?php
					$forwp_social_loop   = $oauth->suggested_loopback_uri( $forwp_social_key );
					$forwp_social_custom = OAuth_Controller::redirect_overrides()[ $forwp_social_key ] ?? '';
					?>
					<div class="forwp-social-panel forwp-social-devmode">
						<h5 class="forwp-social-section-title"><?php esc_html_e( 'Development mode — OAuth redirect', '4wp-social' ); ?></h5>
						<p class="forwp-social-muted"><?php esc_html_e( 'Local site: the platform rejects this domain. Use the loopback address (127.0.0.1 + your Local port) instead, like 4WP Drive does. Save it here, then paste the same URI into the console.', '4wp-social' ); ?></p>
						<?php if ( empty( $forwp_social_guide['loopback_allowed'] ) ) : ?>
							<p class="forwp-social-callout forwp-social-callout--warn"><?php esc_html_e( 'TikTok may reject a loopback redirect (it requires https and a registered domain) — not verified yet. If the console refuses it, connect TikTok on staging.', '4wp-social' ); ?></p>
						<?php endif; ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="forwp-social-copy">
							<input type="hidden" name="action" value="<?php echo esc_attr( $forwp_social_actions['save_redirect'] ); ?>" />
							<input type="hidden" name="platform" value="<?php echo esc_attr( $forwp_social_key ); ?>" />
							<?php wp_nonce_field( $forwp_social_actions['save_redirect'] . '_' . $forwp_social_key ); ?>
							<input type="text" name="redirect_uri" value="<?php echo esc_attr( '' !== $forwp_social_custom ? $forwp_social_custom : $forwp_social_loop ); ?>" spellcheck="false" />
							<button type="submit" class="button button-primary"><?php echo '' !== $forwp_social_custom ? esc_html__( 'Update redirect', '4wp-social' ) : esc_html__( 'Use loopback redirect', '4wp-social' ); ?></button>
							<?php if ( '' !== $forwp_social_custom ) : ?>
								<button type="submit" name="reset" value="1" class="button"><?php esc_html_e( 'Reset', '4wp-social' ); ?></button>
							<?php endif; ?>
						</form>
						<p class="forwp-social-field__help">
							<?php
							/* translators: %s: suggested loopback URI. */
							echo esc_html( sprintf( __( 'Suggested for this site: %s', '4wp-social' ), $forwp_social_loop ) );
							?>
						</p>
					</div>
				<?php endif; ?>

				<?php foreach ( $forwp_social_issues as $forwp_social_issue ) : ?>
					<div class="forwp-social-callout forwp-social-callout--<?php echo 'error' === $forwp_social_issue['level'] ? 'error' : 'warn'; ?>">
						<p><?php echo esc_html( $forwp_social_issue['text'] ); ?></p>
					</div>
				<?php endforeach; ?>

				<details <?php echo $forwp_social_current <= 2 ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Step-by-step instructions', '4wp-social' ); ?></summary>
					<ol class="forwp-social-howto">
						<?php foreach ( $forwp_social_guide['steps'] as $forwp_social_line ) : ?>
							<li><?php echo wp_kses( $forwp_social_line, $forwp_social_html ); ?></li>
						<?php endforeach; ?>
					</ol>
					<?php if ( ! empty( $forwp_social_guide['notes'] ) ) : ?>
						<div class="forwp-social-callout">
							<?php foreach ( $forwp_social_guide['notes'] as $forwp_social_note ) : ?>
								<p><?php echo wp_kses( $forwp_social_note, $forwp_social_html ); ?></p>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</details>

				<a class="button" href="<?php echo esc_url( $forwp_social_guide['console_url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html( $forwp_social_guide['console_label'] ); ?> <span class="dashicons dashicons-external" style="vertical-align:text-bottom;font-size:16px" aria-hidden="true"></span>
				</a>
			</div>
		</div>

		<?php /* Step 2 — paste the keys. */ ?>
		<div class="forwp-social-step <?php echo esc_attr( $forwp_social_state( 2 ) ); ?>">
			<div class="forwp-social-step__num"><?php echo 2 < $forwp_social_current ? '&#10003;' : '2'; ?></div>
			<div class="forwp-social-step__body">
				<h4 class="forwp-social-step__title"><?php esc_html_e( 'Paste the app keys', '4wp-social' ); ?></h4>
				<p class="forwp-social-muted"><?php esc_html_e( 'From the client you just created. The secret is stored encrypted and never shown again; leave it empty to keep the saved one.', '4wp-social' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $forwp_social_actions['save_credentials'] ); ?>" />
					<input type="hidden" name="platform" value="<?php echo esc_attr( $forwp_social_key ); ?>" />
					<?php wp_nonce_field( $forwp_social_actions['save_credentials'] . '_' . $forwp_social_key ); ?>
					<div class="forwp-social-field">
						<label class="forwp-social-field__label" for="<?php echo esc_attr( $forwp_social_anchor ); ?>-id"><?php echo esc_html( $forwp_social_labels['client_id'] ); ?></label>
						<input type="text" id="<?php echo esc_attr( $forwp_social_anchor ); ?>-id" name="client_id" value="<?php echo esc_attr( $forwp_social_creds['client_id'] ); ?>" autocomplete="off" spellcheck="false" />
					</div>
					<div class="forwp-social-field">
						<label class="forwp-social-field__label" for="<?php echo esc_attr( $forwp_social_anchor ); ?>-secret"><?php echo esc_html( $forwp_social_labels['client_secret'] ); ?></label>
						<input type="password" id="<?php echo esc_attr( $forwp_social_anchor ); ?>-secret" name="client_secret" value="" autocomplete="new-password" placeholder="<?php echo '' !== $forwp_social_creds['client_secret'] ? esc_attr__( '•••••••• saved', '4wp-social' ) : ''; ?>" />
					</div>
					<?php submit_button( $forwp_social_has_key ? __( 'Update keys', '4wp-social' ) : __( 'Save keys', '4wp-social' ), $forwp_social_has_key ? 'secondary' : 'primary', 'submit', false ); ?>
				</form>
			</div>
		</div>

		<?php /* Step 3 — connect the account. */ ?>
		<div class="forwp-social-step <?php echo esc_attr( $forwp_social_state( 3 ) ); ?>" id="<?php echo esc_attr( OAuth_Controller::connect_anchor( $forwp_social_key ) ); ?>">
			<div class="forwp-social-step__num"><?php echo 3 < $forwp_social_current ? '&#10003;' : '3'; ?></div>
			<div class="forwp-social-step__body">
				<h4 class="forwp-social-step__title"><?php esc_html_e( 'Connect the account', '4wp-social' ); ?></h4>

				<?php if ( $notice_platform === $forwp_social_key ) : ?>
					<?php if ( '' !== $error ) : ?>
						<div class="forwp-social-callout forwp-social-callout--error" role="alert"><p><strong><?php esc_html_e( 'Connection failed:', '4wp-social' ); ?></strong> <?php echo esc_html( $error ); ?></p></div>
					<?php elseif ( isset( Admin_Menu::notices()[ $notice ] ) ) : ?>
						<div class="forwp-social-callout forwp-social-callout--ok" role="status"><p>&#10003; <?php echo esc_html( Admin_Menu::notices()[ $notice ] ); ?></p></div>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $forwp_social_conn['connected'] ) : ?>
					<div class="forwp-social-callout forwp-social-callout--ok">
						<div class="forwp-social-account">
							<dl>
								<dt><?php esc_html_e( 'Account', '4wp-social' ); ?></dt>
								<dd><strong><?php echo esc_html( '' !== $forwp_social_conn['account'] ? $forwp_social_conn['account'] : __( 'connected (name not available)', '4wp-social' ) ); ?></strong></dd>
								<?php if ( '' !== $forwp_social_conn['connected_at'] ) : ?>
									<dt><?php esc_html_e( 'Connected', '4wp-social' ); ?></dt>
									<dd><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $forwp_social_conn['connected_at'] ) ) ); ?></dd>
								<?php endif; ?>
								<?php if ( $forwp_social_conn['refresh_expires_at'] > 0 ) : ?>
									<dt><?php esc_html_e( 'Reconnect before', '4wp-social' ); ?></dt>
									<dd><?php echo esc_html( wp_date( get_option( 'date_format' ), $forwp_social_conn['refresh_expires_at'] ) ); ?></dd>
								<?php endif; ?>
								<?php if ( '' !== $forwp_social_conn['scope'] ) : ?>
									<dt><?php esc_html_e( 'Permissions', '4wp-social' ); ?></dt>
									<dd><code><?php echo esc_html( $forwp_social_conn['scope'] ); ?></code></dd>
								<?php endif; ?>
							</dl>
						</div>
					</div>
					<div class="forwp-social-actions">
						<a class="button" href="<?php echo esc_url( $oauth->action_url( OAuth_Controller::TEST_ACTION, $forwp_social_key ) ); ?>"><?php esc_html_e( 'Test connection', '4wp-social' ); ?></a>
						<a class="button" href="<?php echo esc_url( $oauth->action_url( OAuth_Controller::START_ACTION, $forwp_social_key ) ); ?>"><?php esc_html_e( 'Reconnect', '4wp-social' ); ?></a>
						<a class="button-link-delete" href="<?php echo esc_url( $oauth->action_url( OAuth_Controller::DISCONNECT_ACTION, $forwp_social_key ) ); ?>"><?php esc_html_e( 'Disconnect', '4wp-social' ); ?></a>
					</div>
				<?php elseif ( $forwp_social_has_key ) : ?>
					<p><?php echo esc_html( $forwp_social_guide['account_hint'] ); ?> <?php esc_html_e( 'You will see the platform consent screen, allow access, and come back here automatically.', '4wp-social' ); ?></p>
					<div class="forwp-social-actions">
						<a class="button button-primary button-hero" href="<?php echo esc_url( $oauth->action_url( OAuth_Controller::START_ACTION, $forwp_social_key ) ); ?>">
							<?php
							/* translators: %s: platform name. */
							echo esc_html( sprintf( __( 'Connect %s account', '4wp-social' ), $forwp_social_provider->get_label() ) );
							?>
						</a>
					</div>
					<p class="forwp-social-field__help"><?php esc_html_e( '"redirect_uri_mismatch" or a similar error means the Redirect URI in the console differs from the one in step 1 — even a trailing slash or http/https matters.', '4wp-social' ); ?></p>
				<?php else : ?>
					<p class="forwp-social-muted"><?php esc_html_e( 'Available after you save the keys in step 2.', '4wp-social' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
<?php endforeach; ?>

<?php require FORWP_SOCIAL_PATH . 'views/partials/account-bluesky.php'; ?>
