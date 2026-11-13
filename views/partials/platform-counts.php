<?php
/**
 * Published / scheduled per platform this month. Shared by Calendar and Dashboard.
 *
 * @package ForWP\Social
 *
 * @var array<int, array<string, mixed>> $events Events of the month.
 */

use ForWP\Social\Modules\Posting\Calendar;
use ForWP\Social\Modules\Posting\Platform_Icons;
use ForWP\Social\Modules\Posting\Platform_Registry;

defined( 'ABSPATH' ) || exit;
?>
	<div class="forwp-social-platform-counts-row">
		<span class="forwp-social-muted"><?php esc_html_e( 'Published / scheduled this month:', '4wp-social' ); ?></span>
		<ul class="forwp-social-platform-counts">
			<?php foreach ( Calendar::platform_counts( $events, Platform_Registry::all( false ) ) as $forwp_social_key => $forwp_social_count ) : ?>
				<?php
				$forwp_social_key   = (string) $forwp_social_key;
				$forwp_social_label = (string) ( Platform_Registry::get( $forwp_social_key )['label'] ?? $forwp_social_key );
				$forwp_social_none  = 0 === $forwp_social_count['published'] && 0 === $forwp_social_count['scheduled'];
				$forwp_social_text  = sprintf(
					/* translators: 1: platform, 2: published count, 3: scheduled count. */
					__( '%1$s: %2$d published, %3$d scheduled', '4wp-social' ),
					$forwp_social_label,
					$forwp_social_count['published'],
					$forwp_social_count['scheduled']
				);
				?>
				<li class="forwp-social-platform-count<?php echo $forwp_social_none ? ' is-zero' : ''; ?>" title="<?php echo esc_attr( $forwp_social_text ); ?>">
					<?php echo Platform_Icons::render( $forwp_social_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
					<span class="screen-reader-text"><?php echo esc_html( $forwp_social_text ); ?></span>
					<span class="forwp-social-platform-count__value" aria-hidden="true"><?php echo esc_html( number_format_i18n( $forwp_social_count['published'] ) . '/' . number_format_i18n( $forwp_social_count['scheduled'] ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
