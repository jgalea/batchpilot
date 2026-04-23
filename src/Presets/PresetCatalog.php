<?php
namespace ContentOps\Presets;

final class PresetCatalog {

	/** @return array<int, array<string, mixed>> */
	public function all(): array {
		$ninety_days_ago = gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) );

		return [
			[
				'slug'        => 'trash-old-drafts',
				'label'       => __( 'Trash drafts older than 90 days', 'content-ops' ),
				'description' => __( 'Moves draft posts unmodified for 90 days into the trash.', 'content-ops' ),
				'target'      => 'post',
				'operation'   => 'delete',
				'filters'     => [
					'status'          => [ 'draft' ],
					'modified_before' => $ninety_days_ago,
				],
				'params'      => [ 'permanent' => false ],
			],
			[
				'slug'        => 'trash-auto-drafts',
				'label'       => __( 'Trash auto-drafts', 'content-ops' ),
				'description' => __( 'Moves auto-draft posts into the trash.', 'content-ops' ),
				'target'      => 'post',
				'operation'   => 'delete',
				'filters'     => [ 'status' => [ 'auto-draft' ] ],
				'params'      => [ 'permanent' => false ],
			],
		];
	}
}
