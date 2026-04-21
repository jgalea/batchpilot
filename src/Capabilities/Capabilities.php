<?php
namespace ContentOps\Capabilities;

final class Capabilities {

	public const ALL = [
		'content_ops_delete',
		'content_ops_edit',
		'content_ops_duplicate',
		'content_ops_move',
		'content_ops_schedule',
	];

	public static function grant_to_admins(): void {
		$role = get_role( 'administrator' );
		if ( null === $role ) {
			return;
		}

		foreach ( self::ALL as $cap ) {
			$role->add_cap( $cap );
		}
	}
}
