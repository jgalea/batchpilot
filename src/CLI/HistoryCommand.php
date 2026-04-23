<?php
namespace ContentOps\CLI;

use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;

final class HistoryCommand {

	private OperationRepository $repo;

	public function __construct( OperationRepository $repo ) {
		$this->repo = $repo;
	}

	/**
	 * List recent Content Ops operations.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : Number of rows. Default: 20.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json. Default: table.
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args
	 * @param array<string, mixed> $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$result = $this->run( $assoc_args );
		if ( '' !== $result['output'] ) {
			\WP_CLI::line( $result['output'] );
		}
	}

	/**
	 * @param array<string, mixed> $assoc_args
	 * @return array{exit_code: int, output: string}
	 */
	public function run( array $assoc_args ): array {
		$limit  = max( 1, min( 100, (int) ( $assoc_args['limit'] ?? 20 ) ) );
		$format = (string) ( $assoc_args['format'] ?? 'table' );

		$rows = array_map( [ $this, 'serialize' ], $this->repo->list( $limit, 0 ) );

		if ( 'json' === $format ) {
			return [
				'exit_code' => 0,
				'output'    => (string) wp_json_encode( $rows ),
			];
		}

		$buffer = [];
		foreach ( $rows as $row ) {
			$buffer[] = sprintf(
				'%d | %s | %s | %s | %d',
				$row['id'],
				$row['type'],
				$row['target'],
				$row['status'],
				$row['affected_count']
			);
		}

		return [
			'exit_code' => 0,
			'output'    => implode( "\n", $buffer ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serialize( Operation $op ): array {
		return [
			'id'             => $op->id(),
			'type'           => $op->type(),
			'target'         => $op->target(),
			'status'         => $op->status(),
			'affected_count' => $op->affected_count(),
			'created_at'     => $op->created_at(),
			'completed_at'   => $op->completed_at(),
		];
	}
}
