<?php
/**
 * Daily source whose API gives lifetime counters only (TikTok): the store keeps snapshots and derives daily changes.
 *
 * @package ForWP\Social
 */

namespace ForWP\Social\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Lifetime counters → daily rows in Metrics_Store.
 */
interface Analytics_Cumulative_Interface extends Analytics_Daily_Interface {

	/**
	 * Lifetime counters from the raw fetch() answer.
	 * `account`: account counters (followers, videos); `items`: item id => counters; `meta`: item id => { title, url, thumbnail, duration, published_at }.
	 * Daily change of every item counter is summed into the account row under the same key,
	 * unless history_days() > 1: then daily() gives the account history from the API and snapshots only feed items (Facebook).
	 *
	 * @param array<string, mixed> $raw fetch() answer.
	 * @return array{account: array<string, float>, items: array<string, array<string, float>>, meta: array<string, array<string, mixed>>}|null Null when the answer has errors.
	 */
	public function snapshot( array $raw ): ?array;
}
