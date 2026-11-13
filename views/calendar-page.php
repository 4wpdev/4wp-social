<?php
/**
 * Calendar page: statistics of the month and its tasks and publications.
 *
 * @package ForWP\Social
 *
 * @var string                           $month
 * @var array<int, array<string, mixed>> $events
 * @var array<string, int>               $stats
 * @var array<int, array<string, mixed>> $site
 */

use ForWP\Social\Admin\Admin_Menu;
use ForWP\Social\Modules\Posting\Calendar;
use ForWP\Social\Modules\Posting\Job_Repository;
use ForWP\Social\Modules\Posting\Platform_Icons;
use ForWP\Social\Modules\Posting\Platform_Registry;
use ForWP\Social\Modules\Posting\Status_Column;
use ForWP\Social\Modules\Posting\Status_Index;

defined( 'ABSPATH' ) || exit;

$forwp_social_tiles = array(
	array( $stats['published'], __( 'Published', '4wp-social' ), Job_Repository::PUBLISHED ),
	array( $stats['scheduled'], __( 'Scheduled', '4wp-social' ), Job_Repository::QUEUED ),
	array( $stats['waiting'], __( 'Waiting for manual posting', '4wp-social' ), Status_Index::MANUAL_PENDING ),
	array( $stats['failed'], __( 'Failed', '4wp-social' ), Job_Repository::FAILED ),
);

$forwp_social_platforms = array();
foreach ( Platform_Registry::all( false ) as $forwp_social_key => $forwp_social_row ) {
	$forwp_social_platforms[ $forwp_social_key ] = array(
		'label' => (string) $forwp_social_row['label'],
		'icon'  => Platform_Icons::render( (string) $forwp_social_key ),
	);
}

$forwp_social_statuses = array();
foreach ( array_keys( Calendar::STAT_GROUPS ) as $forwp_social_status ) {
	$forwp_social_statuses[ $forwp_social_status ] = array(
		'label'  => Status_Column::status_label( $forwp_social_status ),
		'marker' => Status_Column::marker( $forwp_social_status ),
	);
}

$forwp_social_day_config = array(
	'month'     => $month,
	'events'    => $events,
	'site'      => $site,
	'platforms' => $forwp_social_platforms,
	'statuses'  => $forwp_social_statuses,
	'groups'    => Calendar::STAT_GROUPS,
	'stats'     => array(
		'published' => array(
			'label'  => __( 'Published', '4wp-social' ),
			'marker' => Status_Column::marker( Job_Repository::PUBLISHED ),
		),
		'scheduled' => array(
			'label'  => __( 'Scheduled', '4wp-social' ),
			'marker' => Status_Column::marker( Job_Repository::QUEUED ),
		),
		'waiting'   => array(
			'label'  => __( 'Waiting for manual posting', '4wp-social' ),
			'marker' => Status_Column::marker( Status_Index::MANUAL_PENDING ),
		),
		'failed'    => array(
			'label'  => __( 'Failed', '4wp-social' ),
			'marker' => Status_Column::marker( Job_Repository::FAILED ),
		),
	),
	'actions'   => array(
		'publish' => array(
			'label'  => __( 'Published', '4wp-social' ),
			'marker' => Status_Column::marker( 'publish' ),
		),
		'update'  => array(
			'label'  => __( 'Updated', '4wp-social' ),
			'marker' => Status_Column::marker( 'update' ),
		),
	),
	'i18n'      => array(
		'siteTitle' => __( 'On the site', '4wp-social' ),
		'shared'    => __( 'Shared', '4wp-social' ),
		'view'      => __( 'View', '4wp-social' ),
		'empty'     => __( 'Nothing on this day.', '4wp-social' ),
		'open'      => __( 'Open on platform', '4wp-social' ),
		'failed'    => __( 'Could not load this month.', '4wp-social' ),
		'loading'   => __( 'Loading…', '4wp-social' ),
	),
);

$calendar_titles = true;
$month_url       = array( Admin_Menu::class, 'calendar_url' );
?>
<div class="wrap forwp-social-admin-shell forwp-social-calendar-page">
	<h1 class="forwp-social-admin-heading">
		<span class="forwp-social-admin-heading__icon" aria-hidden="true"><span class="dashicons dashicons-calendar-alt"></span></span>
		<span class="forwp-social-admin-heading__text"><span><?php esc_html_e( 'Calendar', '4wp-social' ); ?></span></span>
	</h1>

	<?php require FORWP_SOCIAL_PATH . 'views/partials/platform-counts.php'; ?>

	<div class="forwp-social-stats">
		<?php foreach ( $forwp_social_tiles as $forwp_social_tile ) : ?>
			<div class="forwp-social-stat">
				<span class="forwp-social-stat__value"><?php echo esc_html( number_format_i18n( $forwp_social_tile[0] ) ); ?></span>
				<span class="forwp-social-stat__label"><?php echo Status_Column::marker( $forwp_social_tile[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from constants. ?> <?php echo esc_html( $forwp_social_tile[1] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>

	<?php require FORWP_SOCIAL_PATH . 'views/partials/queue-month.php'; ?>

	<dialog id="forwp-social-day" class="forwp-social-day" tabindex="-1" aria-labelledby="forwp-social-day-title" data-config="<?php echo esc_attr( (string) wp_json_encode( $forwp_social_day_config ) ); ?>">
		<header class="forwp-social-day__head">
			<h2 id="forwp-social-day-title" class="forwp-social-day__title"></h2>
			<button type="button" class="forwp-social-day__btn forwp-social-day__prev" aria-label="<?php esc_attr_e( 'Previous day', '4wp-social' ); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span></button>
			<button type="button" class="forwp-social-day__btn forwp-social-day__next" aria-label="<?php esc_attr_e( 'Next day', '4wp-social' ); ?>"><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></button>
			<button type="button" class="forwp-social-day__btn forwp-social-day__close" aria-label="<?php esc_attr_e( 'Close', '4wp-social' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
		</header>
		<div class="forwp-social-day__stats"></div>
		<div class="forwp-social-day__body" aria-live="polite"></div>
	</dialog>
</div>
