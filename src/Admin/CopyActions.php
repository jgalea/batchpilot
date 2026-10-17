<?php
namespace BatchPilot\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BatchPilot\Duplication\Access;
use BatchPilot\Execution\ExecutionService;
use BatchPilot\History\OperationRepository;
use BatchPilot\Rewrite\RewriteLinks;
use BatchPilot\Rewrite\RewriteWorkflow;
use WP_Post;

final class CopyActions {

	private const EDITOR_HANDLE = 'batchpilot-copy-actions';

	private Access $access;
	private ExecutionService $execution;
	private OperationRepository $operations;
	private string $plugin_file;

	public function __construct( Access $access, ExecutionService $execution, OperationRepository $operations, string $plugin_file ) {
		$this->access      = $access;
		$this->execution   = $execution;
		$this->operations  = $operations;
		$this->plugin_file = $plugin_file;
	}

	public function register(): void {
		add_filter( 'post_row_actions', [ $this, 'filter_row_actions' ], 20, 2 );
		add_filter( 'page_row_actions', [ $this, 'filter_row_actions' ], 20, 2 );
		add_filter( 'display_post_states', [ $this, 'filter_post_states' ], 10, 2 );
		add_action( 'post_submitbox_start', [ $this, 'render_submitbox_links' ] );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );
		add_action( 'admin_post_batchpilot_duplicate_post', [ $this, 'handle_duplicate' ] );
		add_action( 'admin_post_batchpilot_rewrite_post', [ $this, 'handle_rewrite' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_script' ] );
	}

	public function duplicate_url( int $post_id ): string {
		return add_query_arg(
			[
				'action'   => 'batchpilot_duplicate_post',
				'post'     => $post_id,
				'_wpnonce' => wp_create_nonce( 'batchpilot_duplicate_post_' . $post_id ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	public function rewrite_url( int $post_id ): string {
		return add_query_arg(
			[
				'action'   => 'batchpilot_rewrite_post',
				'post'     => $post_id,
				'_wpnonce' => wp_create_nonce( 'batchpilot_rewrite_post_' . $post_id ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function filter_row_actions( array $actions, WP_Post $post ): array {
		if ( 'trash' === $post->post_status || ! $this->access->can_duplicate( $post ) ) {
			return $actions;
		}

		$actions['batchpilot_copy'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $this->duplicate_url( (int) $post->ID ) ),
			esc_html__( 'Duplicate', 'batchpilot' )
		);

		if ( $this->access->can_rewrite( $post ) ) {
			$copy_id = RewriteLinks::copy_of( (int) $post->ID );
			if ( 0 === $copy_id ) {
				$actions['batchpilot_rewrite'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $this->rewrite_url( (int) $post->ID ) ),
					esc_html__( 'Rewrite & Republish', 'batchpilot' )
				);
			} else {
				$actions['batchpilot_rewrite'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( (string) get_edit_post_link( $copy_id, 'raw' ) ),
					esc_html__( 'Edit rewrite', 'batchpilot' )
				);
			}
		}

		return $actions;
	}

	/**
	 * @param array<string, string> $states
	 * @return array<string, string>
	 */
	public function filter_post_states( array $states, WP_Post $post ): array {
		if ( 0 !== RewriteLinks::original_of( (int) $post->ID ) ) {
			$states['batchpilot_rewrite_copy'] = __( 'Rewrite copy', 'batchpilot' );
		} elseif ( 0 !== RewriteLinks::copy_of( (int) $post->ID ) ) {
			$states['batchpilot_rewrite_pending'] = __( 'Rewrite in progress', 'batchpilot' );
		}
		return $states;
	}

	public function render_submitbox_links( ?WP_Post $post = null ): void {
		$post = $post ?? get_post();
		if ( null === $post || 'auto-draft' === $post->post_status || ! $this->access->can_duplicate( $post ) ) {
			return;
		}

		echo '<div class="batchpilot-submitbox-actions" style="margin:6px 0 10px">';
		printf(
			'<a href="%s">%s</a>',
			esc_url( $this->duplicate_url( (int) $post->ID ) ),
			esc_html__( 'Copy to a new draft', 'batchpilot' )
		);
		if ( $this->access->can_rewrite( $post ) ) {
			$copy_id = RewriteLinks::copy_of( (int) $post->ID );
			echo '<br>';
			if ( 0 === $copy_id ) {
				printf( '<a href="%s">%s</a>', esc_url( $this->rewrite_url( (int) $post->ID ) ), esc_html__( 'Rewrite & Republish', 'batchpilot' ) );
			} else {
				printf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $copy_id, 'raw' ) ), esc_html__( 'Edit the rewrite', 'batchpilot' ) );
			}
		}
		echo '</div>';
	}

	public function render_notices(): void {
		$screen = get_current_screen();
		if ( null === $screen || 'post' !== $screen->base || $screen->is_block_editor() ) {
			return;
		}
		$post = get_post();
		if ( null === $post ) {
			return;
		}

		$original_id = RewriteLinks::original_of( (int) $post->ID );
		if ( 0 !== $original_id && null !== get_post( $original_id ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: 1: link to the original post, 2: original post title. */
						__( 'This is a Rewrite & Republish copy of <a href="%1$s">%2$s</a>. Publishing or scheduling it replaces the content of the original, which keeps its URL.', 'batchpilot' ),
						esc_url( (string) get_edit_post_link( $original_id, 'raw' ) ),
						esc_html( get_the_title( $original_id ) )
					),
					[ 'a' => [ 'href' => [] ] ]
				)
			);
			$error = (string) get_post_meta( (int) $post->ID, RewriteWorkflow::META_ERROR, true );
			if ( '' !== $error ) {
				printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
			}
			return;
		}

		$copy_id = RewriteLinks::copy_of( (int) $post->ID );
		if ( 0 !== $copy_id ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: link to the rewrite copy. */
						__( 'A Rewrite & Republish copy of this post exists. <a href="%s">Edit the rewrite</a>.', 'batchpilot' ),
						esc_url( (string) get_edit_post_link( $copy_id, 'raw' ) )
					),
					[ 'a' => [ 'href' => [] ] ]
				)
			);
		}
	}

	public function handle_duplicate(): void {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		check_admin_referer( 'batchpilot_duplicate_post_' . $post_id );

		$post = get_post( $post_id );
		if ( null === $post || ! $this->access->can_duplicate( $post ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this item.', 'batchpilot' ), '', [ 'response' => 403 ] );
		}

		$new_id = $this->run_single(
			$post,
			'duplicate',
			[
				'target_status'   => 'draft',
				'title_suffix'    => '',
				'reassign_author' => get_current_user_id(),
			]
		);
		$this->redirect_to_edit( $new_id );
	}

	public function handle_rewrite(): void {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		check_admin_referer( 'batchpilot_rewrite_post_' . $post_id );

		$post = get_post( $post_id );
		if ( null === $post || ! $this->access->can_rewrite( $post ) ) {
			wp_die( esc_html__( 'You are not allowed to rewrite this item.', 'batchpilot' ), '', [ 'response' => 403 ] );
		}

		$existing = RewriteLinks::copy_of( $post_id );
		if ( 0 !== $existing ) {
			$this->redirect_to_edit( $existing );
		}

		$this->redirect_to_edit( $this->run_single( $post, 'rewrite', [ 'reassign_author' => get_current_user_id() ] ) );
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function run_single( WP_Post $post, string $operation, array $params ): int {
		$operation_id = $this->execution->record( $post->post_type, $operation, get_current_user_id(), [ 'ids' => [ (int) $post->ID ] ], $params );
		$result       = $this->execution->run_sync( $operation_id );

		if ( ! $result->is_ok() || $result->succeeded() < 1 ) {
			$errors  = $result->is_ok() ? $result->item_errors() : [];
			$message = ! empty( $errors ) ? (string) reset( $errors ) : __( 'The copy could not be created.', 'batchpilot' );
			wp_die( esc_html( $message ), '', [ 'back_link' => true ] );
		}

		$row = $this->operations->find( $operation_id );
		$ids = null === $row ? [] : $row->affected_ids();
		return (int) ( $ids[0] ?? 0 );
	}

	private function redirect_to_edit( int $post_id ): void {
		$url = 0 === $post_id ? admin_url( 'edit.php' ) : (string) get_edit_post_link( $post_id, 'raw' );
		wp_safe_redirect( $url );
		exit;
	}

	public function enqueue_editor_script(): void {
		$post = get_post();
		if ( null === $post || ! $this->access->post_type_enabled( $post->post_type ) ) {
			return;
		}

		$original_id = RewriteLinks::original_of( (int) $post->ID );
		$copy_id     = 0 === $original_id ? RewriteLinks::copy_of( (int) $post->ID ) : 0;
		$data        = [
			'duplicateUrl'    => $this->access->can_duplicate( $post ) && 'auto-draft' !== $post->post_status ? $this->duplicate_url( (int) $post->ID ) : '',
			'rewriteUrl'      => 0 === $copy_id && 0 === $original_id && $this->access->can_rewrite( $post ) ? $this->rewrite_url( (int) $post->ID ) : '',
			'rewriteCopyUrl'  => 0 !== $copy_id ? (string) get_edit_post_link( $copy_id, 'raw' ) : '',
			'originalEditUrl' => 0 !== $original_id ? (string) get_edit_post_link( $original_id, 'raw' ) : '',
			'originalTitle'   => 0 !== $original_id ? get_the_title( $original_id ) : '',
			'error'           => 0 !== $original_id ? (string) get_post_meta( (int) $post->ID, RewriteWorkflow::META_ERROR, true ) : '',
		];

		wp_enqueue_script(
			self::EDITOR_HANDLE,
			plugin_dir_url( $this->plugin_file ) . 'assets/editor/copy-actions.js',
			[ 'wp-data', 'wp-dom-ready', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ],
			BATCHPILOT_VERSION,
			true
		);
		wp_set_script_translations( self::EDITOR_HANDLE, 'batchpilot' );
		wp_add_inline_script( self::EDITOR_HANDLE, 'window.batchPilotCopy = ' . wp_json_encode( $data ) . ';', 'before' );
	}
}
