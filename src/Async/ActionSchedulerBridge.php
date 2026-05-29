<?php
namespace BatchPilot\Async;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ActionSchedulerBridge {

	public function is_available(): bool {
		return function_exists( 'as_schedule_single_action' );
	}

	/**
	 * @param array<int|string, mixed> $args
	 */
	public function schedule_single_action( int $timestamp, string $hook, array $args, string $group ): int {
		if ( ! $this->is_available() ) {
			return 0;
		}
		return (int) as_schedule_single_action( $timestamp, $hook, $args, $group );
	}

	/**
	 * @param array<int|string, mixed> $args
	 */
	public function schedule_recurring_action( int $timestamp, int $interval_in_seconds, string $hook, array $args, string $group ): int {
		if ( ! $this->is_available() ) {
			return 0;
		}
		return (int) as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args, $group );
	}

	public function cancel_action( int $action_id ): void {
		if ( ! class_exists( \ActionScheduler::class ) ) {
			return;
		}
		$action = \ActionScheduler::store()->fetch_action( $action_id );
		if ( null === $action || $action instanceof \ActionScheduler_NullAction ) {
			return;
		}
		\ActionScheduler::store()->delete_action( $action_id );
	}

	public function action_exists( int $action_id ): bool {
		if ( ! class_exists( \ActionScheduler::class ) ) {
			return false;
		}
		$action = \ActionScheduler::store()->fetch_action( $action_id );
		return null !== $action && ! $action instanceof \ActionScheduler_NullAction;
	}
}
