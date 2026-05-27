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

	private const VERSION_OPTION  = 'content_ops_caps_version';
	private const CURRENT_VERSION = '1';

	public static function grant_to_admins(): void {
		$role = get_role( 'administrator' );
		if ( null === $role ) {
			return;
		}

		foreach ( self::ALL as $cap ) {
			$role->add_cap( $cap );
		}

		update_option( self::VERSION_OPTION, self::CURRENT_VERSION, false );
	}

	public static function ensure_granted(): void {
		if ( get_option( self::VERSION_OPTION ) === self::CURRENT_VERSION ) {
			$role = get_role( 'administrator' );
			if ( null !== $role && $role->has_cap( 'content_ops_delete' ) ) {
				return;
			}
		}

		self::grant_to_admins();
	}
}
