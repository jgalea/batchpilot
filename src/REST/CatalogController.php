<?php
namespace ContentOps\REST;

use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
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
		$presets = apply_filters( 'content_ops_presets', [] );

		return new WP_REST_Response(
			[
				'targets'    => $targets,
				'operations' => $ops,
				'presets'    => $presets,
			]
		);
	}
}
