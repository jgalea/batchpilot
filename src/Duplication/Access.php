<?php
namespace BatchPilot\Duplication;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Admin\Settings;
use WP_Post;

final class Access {

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @return string[]
	 */
	public function post_types(): array {
		return array_values( array_map( 'strval', (array) $this->settings->get_all()['copy_post_types'] ) );
	}

	public function post_type_enabled( string $post_type ): bool {
		return in_array( $post_type, $this->post_types(), true );
	}

	public function user_has_role(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$user = wp_get_current_user();
		if ( 0 === (int) $user->ID ) {
			return false;
		}
		$allowed = array_map( 'strval', (array) $this->settings->get_all()['copy_roles'] );
		return count( array_intersect( $allowed, (array) $user->roles ) ) > 0;
	}

	public function can_duplicate( WP_Post $post ): bool {
		if ( ! $this->post_type_enabled( $post->post_type ) || ! $this->user_has_role() ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		if ( null === $type || ! current_user_can( $type->cap->create_posts ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post->ID );
	}

	public function can_rewrite( WP_Post $post ): bool {
		if ( ! in_array( $post->post_status, [ 'publish', 'private' ], true ) ) {
			return false;
		}
		return $this->can_duplicate( $post ) && current_user_can( 'edit_post', $post->ID );
	}
}
