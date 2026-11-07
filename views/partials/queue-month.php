<?php
/**
 * Queue tab, Month view: one cell per day, built on the server.
 *
 * @package ForWP\Social
 *
 * @var string                           $month
 * @var array<int, array<string, mixed>> $events
 * @var callable                         $month_url        URL of a month (YYYY-MM).
 * @var bool                             $calendar_titles  Show post titles in the cells.
 * @var array<int, array<string, mixed>> $site             Site content, shown first in each day.
 * @var array<int, array<string, mixed>> $adjacent_events  Optional: events of the leading/trailing days of nearby months.
 * @var array<int, array<string, mixed>> $adjacent_site    Optional: site content of those days.
 */

use ForWP\Social\Modules\Posting\Platform_Icons;
use ForWP\Social\Modules\Posting\Platform_Registry;
use ForWP\Social\Modules\Posting\Status_Column;

defined( 'ABSPATH' ) || exit;

$forwp_social_first  = new DateTimeImmutable( $month . '-01', wp_timezone() );
$forwp_social_days   = (int) $forwp_social_first->format( 't' );
$forwp_social_start  = (int) get_option( 'start_of_week', 1 );
$forwp_social_offset = ( (int) $forwp_social_first->format( 'w' ) - $forwp_social_start + 7 ) % 7;
$forwp_social_labels = Platform_Registry::all( false );

// Leading and trailing days of the neighbour months fill the grid, in a lighter style.
$forwp_social_trail = ( 7 - ( $forwp_social_offset + $forwp_social_days ) % 7 ) % 7;
$forwp_social_dates = array();
for ( $forwp_social_i = $forwp_social_offset; $forwp_social_i > 0; $forwp_social_i-- ) {
	$forwp_social_dates[] = array( $forwp_social_first->modify( '-' . $forwp_social_i . ' days' )->format( 'Y-m-d' ), true );
}
for ( $forwp_social_day = 1; $forwp_social_day <= $forwp_social_days; $forwp_social_day++ ) {
	$forwp_social_dates[] = array( sprintf( '%s-%02d', $month, $forwp_social_day ), false );
}
$forwp_social_last = $forwp_social_first->modify( 'last day of this month' );
for ( $forwp_social_i = 1; $forwp_social_i <= $forwp_social_trail; $forwp_social_i++ ) {
	$forwp_social_dates[] = array( $forwp_social_last->modify( '+' . $forwp_social_i . ' days' )->format( 'Y-m-d' ), true );
}

if ( ! isset( $adjacent_events ) || ! isset( $adjacent_site ) ) {
	$forwp_social_with_site = ! empty( $site );
	$adjacent_events = array();
	$adjacent_site   = array();
	$forwp_social_near = array();
	if ( $forwp_social_offset > 0 ) {
		$forwp_social_near[] = $forwp_social_first->modify( '-1 month' )->format( 'Y-m' );
	}
	if ( $forwp_social_trail > 0 ) {
		$forwp_social_near[] = $forwp_social_first->modify( '+1 month' )->format( 'Y-m' );
	}
	foreach ( $forwp_social_near as $forwp_social_m ) {
		$adjacent_events = array_merge( $adjacent_events, \ForWP\Social\Modules\Posting\Calendar::events( $forwp_social_m ) );
		if ( $forwp_social_with_site ) {
			$adjacent_site = array_merge( $adjacent_site, \ForWP\Social\Modules\Posting\Calendar::site_events( $forwp_social_m ) );
		}
	}
}
$forwp_social_all_events = array_merge( $events, (array) $adjacent_events );
$forwp_social_all_site   = array_merge( $site, (array) $adjacent_site );

$forwp_social_site_by_day = array();
foreach ( $forwp_social_all_site as $forwp_social_item ) {
	$forwp_social_site_by_day[ (string) $forwp_social_item['date'] ][] = $forwp_social_item;
}

$forwp_social_terms_by_day = \ForWP\Social\Modules\Posting\Calendar::day_terms( $forwp_social_all_events, $forwp_social_all_site );

$forwp_social_by_day = array();
foreach ( $forwp_social_all_events as $forwp_social_event ) {
	$forwp_social_by_day[ (string) $forwp_social_event['date'] ][] = $forwp_social_event;
}
?>
<nav class="forwp-social-month-nav">
	<a class="button" href="
	<?php
	echo esc_url(
		$month_url( $forwp_social_first->modify( '-1 month' )->format( 'Y-m' ) )
	);
	?>
	" aria-label="<?php esc_attr_e( 'Previous month', '4wp-social' ); ?>">‹</a>
	<strong><?php echo esc_html( wp_date( 'F Y', $forwp_social_first->getTimestamp() ) ); ?></strong>
	<a class="button" href="
	<?php
	echo esc_url(
		$month_url( $forwp_social_first->modify( '+1 month' )->format( 'Y-m' ) )
	);
	?>
	" aria-label="<?php esc_attr_e( 'Next month', '4wp-social' ); ?>">›</a>
</nav>

<div class="forwp-social-month<?php echo $calendar_titles ? ' has-titles' : ''; ?>">
	<?php for ( $forwp_social_i = 0; $forwp_social_i < 7; $forwp_social_i++ ) : ?>
		<div class="forwp-social-month__head"><?php echo esc_html( $GLOBALS['wp_locale']->get_weekday_abbrev( $GLOBALS['wp_locale']->get_weekday( ( $forwp_social_start + $forwp_social_i ) % 7 ) ) ); ?></div>
	<?php endfor; ?>
	<?php foreach ( $forwp_social_dates as $forwp_social_cell ) : ?>
		<?php
		$forwp_social_date = $forwp_social_cell[0];
		$forwp_social_day  = (int) substr( $forwp_social_date, 8, 2 );
		?>
		<div class="forwp-social-month__cell<?php echo wp_date( 'Y-m-d' ) === $forwp_social_date ? ' is-today' : ''; ?><?php echo $forwp_social_cell[1] ? ' is-adjacent' : ''; ?>">
			<div class="forwp-social-month__top">
				<span class="forwp-social-month__day"><?php echo esc_html( (string) $forwp_social_day ); ?></span>
				<?php if ( $calendar_titles ) : ?>
					<button type="button" class="forwp-social-month__zoom" data-date="<?php echo esc_attr( $forwp_social_date ); ?>" aria-haspopup="dialog" title="<?php esc_attr_e( 'Open day', '4wp-social' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: date. */ __( 'Open day %s', '4wp-social' ), $forwp_social_date ) ); ?>"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $forwp_social_terms_by_day[ $forwp_social_date ] ) ) : ?>
				<ul class="forwp-social-month__series" aria-label="<?php esc_attr_e( 'Series', '4wp-social' ); ?>">
					<?php foreach ( $forwp_social_terms_by_day[ $forwp_social_date ] as $forwp_social_term ) : ?>
						<li class="forwp-social-series forwp-social-series--<?php echo esc_attr( $forwp_social_term['taxonomy'] ); ?>"><?php echo esc_html( $forwp_social_term['name'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php foreach ( $forwp_social_site_by_day[ $forwp_social_date ] ?? array() as $forwp_social_item ) : ?>
				<?php
				$forwp_social_is_update = 'update' === $forwp_social_item['action'];
				$forwp_social_action    = $forwp_social_is_update ? __( 'Updated', '4wp-social' ) : __( 'Published', '4wp-social' );
				$forwp_social_title     = (string) $forwp_social_item['type_label'] . ': ' . $forwp_social_action . ' · ' . (string) $forwp_social_item['title'];
				?>
				<a class="forwp-social-chip forwp-social-chip--site" href="<?php echo esc_url( (string) $forwp_social_item['edit_url'] ); ?>" title="<?php echo esc_attr( $forwp_social_title ); ?>">
					<span class="dashicons <?php echo esc_attr( (string) $forwp_social_item['icon'] ); ?>" aria-hidden="true"></span>
					<?php echo Status_Column::marker( (string) $forwp_social_item['action'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from constants. ?>
					<span class="forwp-social-chip__title"><?php echo esc_html( (string) $forwp_social_item['title'] ); ?></span>
					<span class="screen-reader-text"><?php echo esc_html( $forwp_social_title ); ?></span>
				</a>
			<?php endforeach; ?>
			<?php foreach ( $forwp_social_by_day[ $forwp_social_date ] ?? array() as $forwp_social_event ) : ?>
				<?php
				$forwp_social_key   = (string) $forwp_social_event['platform'];
				$forwp_social_label = (string) ( $forwp_social_labels[ $forwp_social_key ]['label'] ?? $forwp_social_key );
				$forwp_social_title = $forwp_social_label . ': ' . Status_Column::status_label( (string) $forwp_social_event['status'] ) . ' · ' . (string) $forwp_social_event['title'];
				?>
				<a class="forwp-social-chip" href="<?php echo esc_url( (string) $forwp_social_event['edit_url'] ); ?>" title="<?php echo esc_attr( $forwp_social_title ); ?>">
					<?php echo Platform_Icons::render( $forwp_social_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside. ?>
					<?php echo Status_Column::marker( (string) $forwp_social_event['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from constants. ?>
					<?php if ( $calendar_titles ) : ?>
						<span class="forwp-social-chip__title"><?php echo esc_html( (string) $forwp_social_event['title'] ); ?></span>
					<?php endif; ?>
					<span class="screen-reader-text"><?php echo esc_html( $forwp_social_title ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>
</div>
