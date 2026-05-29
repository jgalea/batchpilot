<?php
namespace BatchPilot\REST;

use BatchPilot\Registry\OperationRegistry;
use BatchPilot\Registry\TargetRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class CatalogController extends RestController {

	private TargetRegistry $targets;
	private OperationRegistry $operations;

	public function __construct( TargetRegistry $targets, OperationRegistry $operations ) {
		$this->targets    = $targets;
		$this->operations = $operations;
	}

	/**
	 * @return true|\WP_Error
	 */
	public function check_permission() {
		return $this->require_capability( 'manage_options' );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$targets = [];
		foreach ( $this->targets->all() as $target ) {
			$filters = [];
			foreach ( $target->get_filters() as $filter ) {
				$filters[] = $filter->to_array();
			}
			$targets[] = [
				'slug'    => $target->slug(),
				'label'   => $target->label(),
				'filters' => $filters,
			];
		}

		$ops = [];
		foreach ( $this->operations->all() as $op ) {
			$ops[] = [
				'slug'          => $op->slug(),
				'label'         => $op->label(),
				'params_schema' => $op->get_params_schema(),
				'supports_undo' => $op->supports_undo(),
			];
		}

		/**
		 * Filters the list of presets exposed via the catalog endpoint.
		 *
		 * @param array<int, array<string, mixed>> $presets Preset entries.
		 */
		$presets = apply_filters( 'batchpilot_presets', [] );

		return new WP_REST_Response(
			[
				'targets'    => $targets,
				'operations' => $ops,
				'presets'    => $presets,
				'vocab'      => [
					'statuses'   => $this->statuses(),
					'taxonomies' => $this->taxonomies(),
				],
			]
		);
	}

	/**
	 * @return array<int, array{label: string, value: string}>
	 */
	private function statuses(): array {
		$out = [];
		foreach ( [ 'publish', 'draft', 'pending', 'private', 'future', 'trash' ] as $slug ) {
			$obj = get_post_status_object( $slug );
			if ( null !== $obj ) {
				$out[] = [
					'label' => (string) $obj->label,
					'value' => $slug,
				];
			}
		}
		return $out;
	}

	/**
	 * @return array<int, array{slug: string, label: string, hierarchical: bool, object_types: array<int, string>}>
	 */
	private function taxonomies(): array {
		$out        = [];
		$taxonomies = get_taxonomies( [ 'show_ui' => true ], 'objects' );
		foreach ( $taxonomies as $tax ) {
			$rest_base = ! empty( $tax->rest_base ) ? (string) $tax->rest_base : (string) $tax->name;
			$out[]     = [
				'slug'         => (string) $tax->name,
				'label'        => isset( $tax->labels->name ) ? (string) $tax->labels->name : (string) $tax->name,
				'rest_base'    => $rest_base,
				'hierarchical' => (bool) $tax->hierarchical,
				'object_types' => array_values( array_map( 'strval', (array) $tax->object_type ) ),
			];
		}
		return $out;
	}
}
