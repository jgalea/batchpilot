<?php
namespace BatchPilot\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Capabilities {

	public const ALL = [
		'batchpilot_delete',
		'batchpilot_edit',
		'batchpilot_duplicate',
		'batchpilot_move',
		'batchpilot_schedule',
	];

	private const VERSION_OPTION  = 'batchpilot_caps_version';
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
			if ( null !== $role && $role->has_cap( 'batchpilot_delete' ) ) {
				return;
			}
		}

		self::grant_to_admins();
	}
}
