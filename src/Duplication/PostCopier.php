<?php
namespace BatchPilot\Duplication;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Post;

final class PostCopier {

	private const DEFAULT_EXCLUDED_META = [
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_old_date',
		'_wp_desired_post_slug',
		'_wp_trash_meta_*',
		'_pingme',
		'_encloseme',
		'_bp_rewrite_*',
		'_dp_*',
		'_yoast_wpseo_linkdex',
		'_yoast_wpseo_content_score',
		'_yoast_wpseo_estimated-reading-time-minutes',
		'_yoast_wpseo_wordproof_timestamp',
		'_yoast_indexnow_last_ping',
		'rank_math_seo_score',
		'rank_math_internal_links_processed',
	];

	/**
	 * @return string[]
	 */
	public function excluded_meta_patterns( ?WP_Post $post = null ): array {
		/**
		 * Filters the meta keys that are never copied when duplicating a post or merging a
		 * Rewrite & Republish copy back onto its original. Entries may use * as a wildcard.
		 *
		 * @param string[]     $keys Meta key patterns to skip.
		 * @param WP_Post|null $post Source post, when known.
		 */
		$keys = apply_filters( 'batchpilot_duplicate_excluded_meta_keys', self::DEFAULT_EXCLUDED_META, $post );
		return array_values( array_filter( array_map( 'strval', (array) $keys ) ) );
	}

	/**
	 * @param string[] $patterns
	 */
	public function is_excluded( string $meta_key, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			if ( $pattern === $meta_key || ( false !== strpos( $pattern, '*' ) && fnmatch( $pattern, $meta_key ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $overrides
	 * @return int|\WP_Error
	 */
	public function create_copy( WP_Post $source, array $overrides = [] ) {
		$data = array_merge(
			[
				'post_title'     => $source->post_title,
				'post_content'   => $source->post_content,
				'post_excerpt'   => $source->post_excerpt,
				'post_type'      => $source->post_type,
				'post_status'    => 'draft',
				'post_author'    => (int) $source->post_author,
				'post_parent'    => (int) $source->post_parent,
				'menu_order'     => (int) $source->menu_order,
				'comment_status' => $source->comment_status,
				'ping_status'    => $source->ping_status,
				'post_password'  => $source->post_password,
			],
			$overrides
		);

		$new_id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		if ( 0 === (int) $new_id ) {
			return new \WP_Error( 'bp.copy.insert_failed', 'wp_insert_post returned 0.' );
		}

		$this->copy_meta( (int) $source->ID, (int) $new_id, $source );
		$this->copy_taxonomies( (int) $source->ID, (int) $new_id, $source->post_type );

		return (int) $new_id;
	}

	public function copy_meta( int $source_id, int $target_id, ?WP_Post $source = null ): void {
		$patterns = $this->excluded_meta_patterns( $source ?? get_post( $source_id ) );
		foreach ( get_post_meta( $source_id ) as $key => $values ) {
			if ( $this->is_excluded( (string) $key, $patterns ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				add_post_meta( $target_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
	}

	public function copy_taxonomies( int $source_id, int $target_id, string $post_type ): void {
		foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $source_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $target_id, array_map( 'intval', $terms ), $taxonomy );
			}
		}
	}

	public function sync_taxonomies( int $source_id, int $target_id, string $post_type ): void {
		foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $source_id, $taxonomy, [ 'fields' => 'ids' ] );
			wp_set_object_terms( $target_id, is_array( $terms ) ? array_map( 'intval', $terms ) : [], $taxonomy );
		}
	}

	/**
	 * Makes the target's copyable meta equal to the source's. Keys whose values already
	 * match are left alone so unchanged meta keeps its meta IDs and fires no update hooks.
	 */
	public function sync_meta( int $source_id, int $target_id ): void {
		$patterns = $this->excluded_meta_patterns( get_post( $source_id ) );
		$source   = $this->copyable_meta( $source_id, $patterns );
		$target   = $this->copyable_meta( $target_id, $patterns );

		foreach ( $target as $key => $values ) {
			if ( ! isset( $source[ $key ] ) ) {
				delete_post_meta( $target_id, $key );
			}
		}
		foreach ( $source as $key => $values ) {
			if ( isset( $target[ $key ] ) && $target[ $key ] === $values ) {
				continue;
			}
			delete_post_meta( $target_id, $key );
			foreach ( $values as $value ) {
				add_post_meta( $target_id, $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
	}

	/**
	 * @param array<string, string[]> $meta Raw stored values keyed by meta key.
	 */
	public function replace_meta( int $post_id, array $meta ): void {
		$patterns = $this->excluded_meta_patterns( get_post( $post_id ) );
		foreach ( array_keys( $this->copyable_meta( $post_id, $patterns ) ) as $key ) {
			delete_post_meta( $post_id, $key );
		}
		foreach ( $meta as $key => $values ) {
			if ( $this->is_excluded( (string) $key, $patterns ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $post_id, (string) $key, wp_slash( maybe_unserialize( $value ) ) );
			}
		}
	}

	/**
	 * @param string[] $patterns
	 * @return array<string, string[]>
	 */
	public function copyable_meta( int $post_id, array $patterns ): array {
		$out = [];
		foreach ( get_post_meta( $post_id ) as $key => $values ) {
			if ( $this->is_excluded( (string) $key, $patterns ) ) {
				continue;
			}
			$out[ (string) $key ] = array_map( 'strval', (array) $values );
		}
		return $out;
	}
}
