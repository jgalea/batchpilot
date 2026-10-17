<?php
namespace BatchPilot\Rewrite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RewriteLinks {

	public const META_ORIGINAL = '_bp_rewrite_of';
	public const META_COPY     = '_bp_rewrite_copy';

	public static function original_of( int $copy_id ): int {
		return (int) get_post_meta( $copy_id, self::META_ORIGINAL, true );
	}

	/**
	 * The live rewrite copy of an original, or 0. A copy that was trashed or deleted no
	 * longer counts and its stale link is cleared.
	 */
	public static function copy_of( int $original_id ): int {
		$copy_id = (int) get_post_meta( $original_id, self::META_COPY, true );
		if ( 0 === $copy_id ) {
			return 0;
		}
		$copy = get_post( $copy_id );
		if ( null === $copy || 'trash' === $copy->post_status || self::original_of( $copy_id ) !== $original_id ) {
			delete_post_meta( $original_id, self::META_COPY );
			return 0;
		}
		return $copy_id;
	}

	public static function link( int $original_id, int $copy_id ): bool {
		if ( ! add_post_meta( $original_id, self::META_COPY, $copy_id, true ) ) {
			return false;
		}
		update_post_meta( $copy_id, self::META_ORIGINAL, $original_id );
		return true;
	}

	public static function unlink( int $original_id, int $copy_id ): void {
		if ( (int) get_post_meta( $original_id, self::META_COPY, true ) === $copy_id ) {
			delete_post_meta( $original_id, self::META_COPY );
		}
		delete_post_meta( $copy_id, self::META_ORIGINAL );
	}
}
