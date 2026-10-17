<?php
namespace BatchPilot\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RepublishCommand extends IdsOperationCommand {

	protected function operation(): string {
		return 'republish';
	}

	/**
	 * Merge Rewrite & Republish copies onto their original posts.
	 *
	 * The original keeps its ID and URL, a revision of its previous state is kept,
	 * and the copy is deleted.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : IDs of the original posts (or of their copies) to republish.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated post IDs (alternative to positional IDs).
	 *
	 * [--post-type=<slug>]
	 * : Post type slug. Default: post.
	 *
	 * [--dry-run]
	 * : Preview only.
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args
	 * @param array<string, mixed> $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->invoke( $args, $assoc_args );
	}
}
