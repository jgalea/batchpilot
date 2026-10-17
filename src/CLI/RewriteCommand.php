<?php
namespace BatchPilot\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RewriteCommand extends IdsOperationCommand {

	protected function operation(): string {
		return 'rewrite';
	}

	/**
	 * Create Rewrite & Republish draft copies of published posts.
	 *
	 * The copy is a linked draft. Publishing or scheduling it merges it back onto
	 * the original post, which keeps its ID and URL.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : IDs of the published posts to rewrite.
	 *
	 * [--ids=<ids>]
	 * : Comma-separated post IDs (alternative to positional IDs).
	 *
	 * [--post-type=<slug>]
	 * : Post type slug. Default: post.
	 *
	 * [--reassign-author=<id>]
	 * : User ID to assign as the author of the copies. Default: the current user.
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
