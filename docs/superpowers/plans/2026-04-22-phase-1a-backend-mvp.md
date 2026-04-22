# Phase 1a — Backend MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the first user-visible functionality — a concrete `PostTarget` (post/page/any CPT) plus `Delete`, `Duplicate`, and `Bulk Edit` operations — exposed through REST, WP-CLI, and the Abilities API. No admin UI. End state: a site owner or AI agent can preview, execute, list, and undo bulk operations over posts via three surfaces.

**Architecture:** `PostTarget` implements `TargetInterface` and is instantiated once per registered public post type. Each of the three operation classes implements `OperationInterface`. Registries hold them for discovery. REST endpoints (`/catalog`, `/preview`, `/execute`, `/operations[/{id}[/undo]]`) plus WP-CLI commands (`delete`, `duplicate`, `edit`, `history`, `undo`) share a single `ExecutionService` that encapsulates validate → preview → record → run. Heavy workloads go through Action Scheduler via `OperationRunner`; CLI runs synchronously. Abilities register dynamically from the Target × Operation matrix.

**Tech Stack:**
- PHP 7.4+, WordPress 6.3+
- Action Scheduler (bundled) for async batching
- PHPUnit 9.6 with brain-monkey (unit) and wp-phpunit (integration)
- PHPStan level set in Phase 0, PHPCS with WordPress-Coding-Standards

---

## File structure added in Phase 1a

```
src/
├── Targets/
│   └── PostTarget.php
├── Operations/
│   ├── DeleteOperation.php
│   ├── DuplicateOperation.php
│   └── BulkEditOperation.php
├── Presets/
│   └── PresetCatalog.php
├── Execution/
│   ├── ExecutionService.php
│   └── OperationRunner.php
├── REST/
│   ├── CatalogController.php
│   ├── PreviewController.php
│   ├── ExecuteController.php
│   ├── OperationsController.php
│   └── UndoController.php
└── CLI/
    ├── DeleteCommand.php
    ├── DuplicateCommand.php
    ├── EditCommand.php
    ├── HistoryCommand.php
    └── UndoCommand.php

tests/
├── unit/
│   ├── Targets/PostTargetUnitTest.php
│   ├── Operations/
│   │   ├── DeleteOperationUnitTest.php
│   │   ├── DuplicateOperationUnitTest.php
│   │   └── BulkEditOperationUnitTest.php
│   └── Presets/PresetCatalogTest.php
└── integration/
    ├── Targets/PostTargetTest.php
    ├── Operations/
    │   ├── DeleteOperationTest.php
    │   ├── DuplicateOperationTest.php
    │   └── BulkEditOperationTest.php
    ├── Execution/
    │   ├── ExecutionServiceTest.php
    │   └── OperationRunnerTest.php
    ├── REST/
    │   ├── CatalogRouteTest.php
    │   ├── PreviewRouteTest.php
    │   ├── ExecuteRouteTest.php
    │   ├── OperationsRouteTest.php
    │   └── UndoRouteTest.php
    ├── CLI/
    │   ├── DeleteCommandTest.php
    │   ├── DuplicateCommandTest.php
    │   ├── EditCommandTest.php
    │   ├── HistoryCommandTest.php
    │   └── UndoCommandTest.php
    └── Abilities/AbilitiesMatrixTest.php
```

Responsibilities:
- `Targets/PostTarget.php` — one instance per post type. Builds `WP_Query` args from `QueryArgs`, returns matched IDs, provides display rows for preview samples.
- `Operations/*Operation.php` — pure operation logic. Receive the Target at call time, never resolve registries themselves.
- `Presets/PresetCatalog.php` — in-memory list of curated filter+operation presets exposed through `/catalog`.
- `Execution/ExecutionService.php` — shared by REST, CLI, and the async runner. Resolves Target + Operation from registries, runs `validate`, `preview`, generates preview token, stores canonical payload, creates the history row, and runs `execute_batch` when called synchronously.
- `Execution/OperationRunner.php` — registers the `content_ops_run_operation` Action Scheduler hook. Loads the row, re-runs the query, chunks, executes, and marks the row.
- `REST/*Controller.php` — one controller per endpoint; each delegates to `ExecutionService` or repository.
- `CLI/*Command.php` — flag parsing, delegation to `ExecutionService` (synchronous path), stable exit codes.

---

## Task 1: `PostTarget` skeleton, slug/label/supports_operation

**Files:**
- Create: `src/Targets/PostTarget.php`
- Create: `tests/unit/Targets/PostTargetUnitTest.php`

Decision locked here: `PostTarget` is instantiated **once per post type**. Its slug is the post type slug (`post`, `page`, `product`, etc.). The Plugin boot registers one instance per configured public post type. Label is the post type's plural label with a fallback to the slug.

- [ ] **Step 1: Write the failing unit test**

```php
<?php
namespace ContentOps\Tests\Unit\Targets;

use Brain\Monkey\Functions;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Unit\TestCase;

final class PostTargetUnitTest extends TestCase {

	public function test_slug_is_post_type(): void {
		$target = new PostTarget( 'page' );
		$this->assertSame( 'page', $target->slug() );
	}

	public function test_label_falls_back_to_slug_when_post_type_missing(): void {
		Functions\when( 'get_post_type_object' )->justReturn( null );

		$target = new PostTarget( 'custom_thing' );
		$this->assertSame( 'custom_thing', $target->label() );
	}

	public function test_label_uses_plural_label_when_available(): void {
		$stub          = new \stdClass();
		$stub->labels  = (object) [ 'name' => 'Products' ];
		Functions\when( 'get_post_type_object' )->justReturn( $stub );

		$target = new PostTarget( 'product' );
		$this->assertSame( 'Products', $target->label() );
	}

	public function test_supports_operation_allows_delete_duplicate_edit(): void {
		$target = new PostTarget( 'post' );
		$this->assertTrue( $target->supports_operation( 'delete' ) );
		$this->assertTrue( $target->supports_operation( 'duplicate' ) );
		$this->assertTrue( $target->supports_operation( 'edit' ) );
		$this->assertFalse( $target->supports_operation( 'move' ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `composer test:unit -- --filter PostTargetUnitTest`
Expected: FAIL — class `ContentOps\Targets\PostTarget` does not exist.

- [ ] **Step 3: Implement the minimal class**

```php
<?php
namespace ContentOps\Targets;

use ContentOps\Contracts\FilterDefinition;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;

final class PostTarget implements TargetInterface {

	private const SUPPORTED = [ 'delete', 'duplicate', 'edit' ];

	private string $post_type;

	public function __construct( string $post_type ) {
		$this->post_type = $post_type;
	}

	public function slug(): string {
		return $this->post_type;
	}

	public function label(): string {
		$object = get_post_type_object( $this->post_type );
		if ( null === $object || ! isset( $object->labels->name ) || '' === $object->labels->name ) {
			return $this->post_type;
		}
		return (string) $object->labels->name;
	}

	public function get_filters(): array {
		return [];
	}

	public function query( QueryArgs $args, int $limit = 0, int $offset = 0 ): array {
		return [];
	}

	public function count( QueryArgs $args ): int {
		return 0;
	}

	public function get_display( int $id ): array {
		return [];
	}

	public function supports_operation( string $operation_slug ): bool {
		return in_array( $operation_slug, self::SUPPORTED, true );
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `composer test:unit -- --filter PostTargetUnitTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Targets/PostTarget.php tests/unit/Targets/PostTargetUnitTest.php
git commit -m "add PostTarget skeleton with slug/label/supports_operation"
```

---

## Task 2: `PostTarget::get_filters()`

**Files:**
- Modify: `src/Targets/PostTarget.php`
- Modify: `tests/unit/Targets/PostTargetUnitTest.php`

Twelve filters listed in the spec. Filter keys: `post_type`, `status`, `author`, `modified_before`, `modified_after`, `published_before`, `published_after`, `taxonomy`, `has_comments`, `has_featured_image`, `post_parent`, `has_children`.

- [ ] **Step 1: Add failing test**

Append to `tests/unit/Targets/PostTargetUnitTest.php`:

```php
	public function test_get_filters_returns_expected_keys(): void {
		$target  = new PostTarget( 'post' );
		$filters = $target->get_filters();

		$keys = array_map( static fn ( $f ) => $f->key(), $filters );

		$this->assertSame(
			[
				'post_type',
				'status',
				'author',
				'modified_before',
				'modified_after',
				'published_before',
				'published_after',
				'taxonomy',
				'has_comments',
				'has_featured_image',
				'post_parent',
				'has_children',
			],
			$keys
		);
	}

	public function test_get_filters_types_are_declared(): void {
		$target = new PostTarget( 'post' );
		$by_key = [];
		foreach ( $target->get_filters() as $filter ) {
			$by_key[ $filter->key() ] = $filter->type();
		}

		$this->assertSame( 'enum', $by_key['status'] );
		$this->assertSame( 'user', $by_key['author'] );
		$this->assertSame( 'date', $by_key['modified_before'] );
		$this->assertSame( 'taxonomy', $by_key['taxonomy'] );
		$this->assertSame( 'bool', $by_key['has_comments'] );
		$this->assertSame( 'post', $by_key['post_parent'] );
	}
```

- [ ] **Step 2: Run to verify failure**

Run: `composer test:unit -- --filter PostTargetUnitTest`
Expected: FAIL — `get_filters()` returns empty array.

- [ ] **Step 3: Implement**

Replace the `get_filters` method in `src/Targets/PostTarget.php`:

```php
	public function get_filters(): array {
		return [
			new FilterDefinition( 'post_type', __( 'Post type', 'content-ops' ), 'enum', [ 'default' => $this->post_type ] ),
			new FilterDefinition( 'status', __( 'Status', 'content-ops' ), 'enum', [ 'multiple' => true ] ),
			new FilterDefinition( 'author', __( 'Author', 'content-ops' ), 'user' ),
			new FilterDefinition( 'modified_before', __( 'Modified before', 'content-ops' ), 'date' ),
			new FilterDefinition( 'modified_after', __( 'Modified after', 'content-ops' ), 'date' ),
			new FilterDefinition( 'published_before', __( 'Published before', 'content-ops' ), 'date' ),
			new FilterDefinition( 'published_after', __( 'Published after', 'content-ops' ), 'date' ),
			new FilterDefinition( 'taxonomy', __( 'Taxonomy term', 'content-ops' ), 'taxonomy', [ 'shape' => [ 'taxonomy' => 'string', 'term_ids' => 'int[]' ] ] ),
			new FilterDefinition( 'has_comments', __( 'Has comments', 'content-ops' ), 'bool' ),
			new FilterDefinition( 'has_featured_image', __( 'Has featured image', 'content-ops' ), 'bool' ),
			new FilterDefinition( 'post_parent', __( 'Post parent', 'content-ops' ), 'post' ),
			new FilterDefinition( 'has_children', __( 'Has children', 'content-ops' ), 'bool' ),
		];
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:unit -- --filter PostTargetUnitTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Targets/PostTarget.php tests/unit/Targets/PostTargetUnitTest.php
git commit -m "add PostTarget filter definitions"
```

---

## Task 3: `PostTarget::query()` and `count()`

**Files:**
- Modify: `src/Targets/PostTarget.php`
- Create: `tests/integration/Targets/PostTargetTest.php`

Integration test against real `WP_Query`. Uses the unit test's post type, but executed in the integration harness so `WP_Query` and `wp_insert_post` actually run.

- [ ] **Step 1: Write the failing integration test**

```php
<?php
namespace ContentOps\Tests\Integration\Targets;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class PostTargetTest extends TestCase {

	public function test_query_filters_by_status(): void {
		$draft_a  = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$draft_b  = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$target = new PostTarget( 'post' );
		$ids    = $target->query( QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ) );

		sort( $ids );
		$this->assertSame( [ $draft_a, $draft_b ], array_map( 'intval', $ids ) );
	}

	public function test_count_matches_query_size(): void {
		self::factory()->post->create_many( 4, [ 'post_status' => 'draft' ] );

		$target = new PostTarget( 'post' );
		$this->assertSame( 4, $target->count( QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ) ) );
	}

	public function test_modified_before_filters_by_date(): void {
		$old = self::factory()->post->create(
			[
				'post_status'       => 'draft',
				'post_modified'     => '2024-01-01 00:00:00',
				'post_modified_gmt' => '2024-01-01 00:00:00',
			]
		);
		self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$target = new PostTarget( 'post' );
		$ids    = $target->query(
			QueryArgs::from_array(
				[
					'status'          => [ 'draft' ],
					'modified_before' => '2024-06-01',
				]
			)
		);

		$this->assertSame( [ (int) $old ], array_map( 'intval', $ids ) );
	}

	public function test_author_filter(): void {
		$user_id = self::factory()->user->create();
		$mine    = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $user_id,
			]
		);
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$target = new PostTarget( 'post' );
		$ids    = $target->query( QueryArgs::from_array( [ 'author' => $user_id ] ) );

		$this->assertSame( [ (int) $mine ], array_map( 'intval', $ids ) );
	}

	public function test_has_featured_image_filter(): void {
		$with    = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$without = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $with, '_thumbnail_id', 999 );

		$target = new PostTarget( 'post' );
		$ids    = $target->query(
			QueryArgs::from_array(
				[
					'status'             => [ 'publish' ],
					'has_featured_image' => true,
				]
			)
		);

		$this->assertSame( [ (int) $with ], array_map( 'intval', $ids ) );
		$this->assertNotContains( (int) $without, array_map( 'intval', $ids ) );
	}

	public function test_limit_and_offset_paginate(): void {
		$ids = self::factory()->post->create_many( 5, [ 'post_status' => 'draft' ] );
		sort( $ids );

		$target = new PostTarget( 'post' );
		$page_1 = $target->query( QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ), 2, 0 );
		$page_2 = $target->query( QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ), 2, 2 );

		$this->assertCount( 2, $page_1 );
		$this->assertCount( 2, $page_2 );
		$this->assertSame( [], array_intersect( $page_1, $page_2 ) );
	}
}
```

- [ ] **Step 2: Run and confirm failure**

Run: `composer test:integration -- --filter PostTargetTest`
Expected: FAIL — `query()` returns empty array.

- [ ] **Step 3: Implement `query()` and `count()`**

Replace those two methods in `src/Targets/PostTarget.php`:

```php
	public function query( QueryArgs $args, int $limit = 0, int $offset = 0 ): array {
		$query_args                    = $this->build_wp_query_args( $args );
		$query_args['fields']          = 'ids';
		$query_args['posts_per_page']  = $limit > 0 ? $limit : -1;
		$query_args['offset']          = $offset;
		$query_args['no_found_rows']   = true;
		$query_args['suppress_filters'] = false;

		$query = new \WP_Query( $query_args );

		return array_map( 'intval', $query->posts );
	}

	public function count( QueryArgs $args ): int {
		$query_args                   = $this->build_wp_query_args( $args );
		$query_args['fields']         = 'ids';
		$query_args['posts_per_page'] = 1;
		$query_args['no_found_rows']  = false;

		$query = new \WP_Query( $query_args );

		return (int) $query->found_posts;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_wp_query_args( QueryArgs $args ): array {
		$query = [
			'post_type'   => $this->post_type,
			'post_status' => 'any',
		];

		if ( $args->has( 'status' ) ) {
			$query['post_status'] = $args->get( 'status' );
		}

		if ( $args->has( 'author' ) ) {
			$query['author'] = (int) $args->get( 'author' );
		}

		$date_query = [];
		if ( $args->has( 'published_before' ) ) {
			$date_query[] = [ 'before' => (string) $args->get( 'published_before' ), 'column' => 'post_date', 'inclusive' => true ];
		}
		if ( $args->has( 'published_after' ) ) {
			$date_query[] = [ 'after' => (string) $args->get( 'published_after' ), 'column' => 'post_date', 'inclusive' => true ];
		}
		if ( $args->has( 'modified_before' ) ) {
			$date_query[] = [ 'before' => (string) $args->get( 'modified_before' ), 'column' => 'post_modified', 'inclusive' => true ];
		}
		if ( $args->has( 'modified_after' ) ) {
			$date_query[] = [ 'after' => (string) $args->get( 'modified_after' ), 'column' => 'post_modified', 'inclusive' => true ];
		}
		if ( ! empty( $date_query ) ) {
			$query['date_query'] = $date_query;
		}

		$meta_query = [];
		if ( $args->has( 'has_featured_image' ) ) {
			$meta_query[] = true === $args->get( 'has_featured_image' )
				? [ 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ]
				: [ 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ];
		}
		if ( ! empty( $meta_query ) ) {
			$query['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		if ( $args->has( 'taxonomy' ) ) {
			$tax = $args->get( 'taxonomy' );
			if ( is_array( $tax ) && ! empty( $tax['taxonomy'] ) && ! empty( $tax['term_ids'] ) ) {
				$query['tax_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					[
						'taxonomy' => (string) $tax['taxonomy'],
						'field'    => 'term_id',
						'terms'    => array_map( 'intval', (array) $tax['term_ids'] ),
					],
				];
			}
		}

		if ( $args->has( 'post_parent' ) ) {
			$query['post_parent'] = (int) $args->get( 'post_parent' );
		}

		if ( $args->has( 'has_comments' ) ) {
			$query['comment_count'] = true === $args->get( 'has_comments' )
				? [ 'value' => 0, 'compare' => '>' ]
				: 0;
		}

		if ( $args->has( 'has_children' ) ) {
			$query['co_has_children'] = (bool) $args->get( 'has_children' );
		}

		return $query;
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter PostTargetTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Targets/PostTarget.php tests/integration/Targets/PostTargetTest.php
git commit -m "implement PostTarget query and count against WP_Query"
```

---

## Task 4: `PostTarget::get_display()`

**Files:**
- Modify: `src/Targets/PostTarget.php`
- Modify: `tests/integration/Targets/PostTargetTest.php`

Preview rows need: `id`, `title`, `status`, `date`, `edit_url`, `thumbnail_url` (nullable).

- [ ] **Step 1: Add failing test**

Append to `PostTargetTest`:

```php
	public function test_get_display_returns_summary(): void {
		$post_id = self::factory()->post->create(
			[
				'post_title'  => 'Hello world',
				'post_status' => 'draft',
				'post_date'   => '2024-06-15 10:00:00',
			]
		);

		$target = new PostTarget( 'post' );
		$row    = $target->get_display( (int) $post_id );

		$this->assertSame( (int) $post_id, $row['id'] );
		$this->assertSame( 'Hello world', $row['title'] );
		$this->assertSame( 'draft', $row['status'] );
		$this->assertSame( '2024-06-15 10:00:00', $row['date'] );
		$this->assertStringContainsString( 'post.php', (string) $row['edit_url'] );
		$this->assertNull( $row['thumbnail_url'] );
	}

	public function test_get_display_missing_post_returns_placeholder(): void {
		$target = new PostTarget( 'post' );
		$row    = $target->get_display( 999999 );

		$this->assertSame( 999999, $row['id'] );
		$this->assertSame( '', $row['title'] );
		$this->assertSame( 'missing', $row['status'] );
	}
```

- [ ] **Step 2: Verify failure**

Run: `composer test:integration -- --filter PostTargetTest`
Expected: FAIL — `get_display` returns empty array.

- [ ] **Step 3: Implement**

Replace `get_display` in `src/Targets/PostTarget.php`:

```php
	public function get_display( int $id ): array {
		$post = get_post( $id );
		if ( null === $post ) {
			return [
				'id'            => $id,
				'title'         => '',
				'status'        => 'missing',
				'date'          => '',
				'edit_url'      => '',
				'thumbnail_url' => null,
			];
		}

		$thumb_id  = (int) get_post_thumbnail_id( $post );
		$thumb_url = 0 === $thumb_id ? null : (string) wp_get_attachment_image_url( $thumb_id, 'thumbnail' );

		return [
			'id'            => (int) $post->ID,
			'title'         => (string) $post->post_title,
			'status'        => (string) $post->post_status,
			'date'          => (string) $post->post_date,
			'edit_url'      => (string) get_edit_post_link( $post->ID, 'raw' ),
			'thumbnail_url' => $thumb_url,
		];
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter PostTargetTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Targets/PostTarget.php tests/integration/Targets/PostTargetTest.php
git commit -m "add PostTarget get_display summary rows"
```

---

---

## Task 5: `DeleteOperation` skeleton + `validate()` + `preview()`

**Files:**
- Create: `src/Operations/DeleteOperation.php`
- Create: `tests/integration/Operations/DeleteOperationTest.php`

Decision: `supports_undo()` returns `true` unconditionally (capability flag). `undo()` inspects the stored operation: if the row's `params.permanent === true`, returns an `UndoResult::error` because hard-deleted posts cannot be restored; otherwise untrashes.

`DeleteOperation` constructor takes `TokenGenerator` and `TokenStore` so `preview()` can mint and store tokens. Tests construct them directly with a fixed salt.

- [ ] **Step 1: Write the failing integration test**

```php
<?php
namespace ContentOps\Tests\Integration\Operations;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Operations\DeleteOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DeleteOperationTest extends TestCase {

	private function op(): DeleteOperation {
		return new DeleteOperation( new TokenGenerator( 'test-salt' ), new TokenStore( 300 ) );
	}

	public function test_slug_and_label(): void {
		$op = $this->op();
		$this->assertSame( 'delete', $op->slug() );
		$this->assertNotSame( '', $op->label() );
	}

	public function test_params_schema_exposes_permanent_flag(): void {
		$schema = $this->op()->get_params_schema();
		$this->assertArrayHasKey( 'permanent', $schema['properties'] );
		$this->assertSame( 'boolean', $schema['properties']['permanent']['type'] );
	}

	public function test_validate_returns_ok(): void {
		$result = $this->op()->validate( QueryArgs::from_array( [] ), [] );
		$this->assertTrue( $result->is_ok() );
	}

	public function test_preview_returns_count_sample_and_token(): void {
		self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$target = new PostTarget( 'post' );
		$args   = QueryArgs::from_array( [ 'status' => [ 'draft' ] ] );

		$preview = $this->op()->preview( $args, [ 'permanent' => false ], $target );

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 3, $preview->count() );
		$this->assertCount( 3, $preview->sample_ids() );
		$this->assertNotSame( '', $preview->preview_token() );
	}

	public function test_preview_caps_sample_at_twenty(): void {
		self::factory()->post->create_many( 25, [ 'post_status' => 'draft' ] );

		$preview = $this->op()->preview(
			QueryArgs::from_array( [ 'status' => [ 'draft' ] ] ),
			[ 'permanent' => false ],
			new PostTarget( 'post' )
		);

		$this->assertSame( 25, $preview->count() );
		$this->assertCount( 20, $preview->sample_ids() );
	}

	public function test_supports_undo_is_true(): void {
		$this->assertTrue( $this->op()->supports_undo() );
	}
}
```

- [ ] **Step 2: Run and confirm failure**

Run: `composer test:integration -- --filter DeleteOperationTest`
Expected: FAIL — class `ContentOps\Operations\DeleteOperation` missing.

- [ ] **Step 3: Implement skeleton**

Create `src/Operations/DeleteOperation.php`:

```php
<?php
namespace ContentOps\Operations;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\OperationInterface;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Contracts\UndoResult;
use ContentOps\Contracts\ValidationResult;
use ContentOps\Errors\ContentOpsError;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;

final class DeleteOperation implements OperationInterface {

	private const SAMPLE_SIZE = 20;

	private TokenGenerator $token_generator;
	private TokenStore $token_store;

	public function __construct( TokenGenerator $token_generator, TokenStore $token_store ) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
	}

	public function slug(): string {
		return 'delete';
	}

	public function label(): string {
		return __( 'Delete', 'content-ops' );
	}

	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'permanent' => [
					'type'    => 'boolean',
					'default' => false,
				],
			],
		];
	}

	public function validate( QueryArgs $args, array $params ): ValidationResult {
		return ValidationResult::ok();
	}

	public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult {
		$count      = $target->count( $args );
		$sample_ids = $target->query( $args, self::SAMPLE_SIZE, 0 );

		$payload = [
			'target'      => $target->slug(),
			'operation'   => $this->slug(),
			'filters'     => $args->to_array(),
			'params'      => $params,
			'sample_ids'  => $sample_ids,
			'count'       => $count,
		];

		$token = $this->token_generator->generate( $payload );
		$this->token_store->store( $token, $payload );

		return PreviewResult::of( $count, $sample_ids, $token );
	}

	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
		return BatchResult::of( 0, 0, 0 );
	}

	public function supports_undo(): bool {
		return true;
	}

	public function undo( int $operation_id ): UndoResult {
		return UndoResult::error( new ContentOpsError( 'co.undo.not_implemented', 'Not implemented yet.' ) );
	}
}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter DeleteOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/DeleteOperation.php tests/integration/Operations/DeleteOperationTest.php
git commit -m "add DeleteOperation skeleton with validate and preview"
```

---

## Task 6: `DeleteOperation::execute_batch()`

**Files:**
- Modify: `src/Operations/DeleteOperation.php`
- Modify: `tests/integration/Operations/DeleteOperationTest.php`

Trashes when `permanent=false`; hard deletes when `permanent=true`. Counts successes and failures. Captures per-item error messages.

- [ ] **Step 1: Add failing tests**

Append to `DeleteOperationTest`:

```php
	public function test_execute_batch_trashes_by_default(): void {
		$ids    = self::factory()->post->create_many( 3, [ 'post_status' => 'publish' ] );
		$target = new PostTarget( 'post' );

		$result = $this->op()->execute_batch( $ids, [ 'permanent' => false ], $target );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 3, $result->processed() );
		$this->assertSame( 3, $result->succeeded() );
		$this->assertSame( 0, $result->failed() );

		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}

	public function test_execute_batch_hard_deletes_when_permanent(): void {
		$ids    = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		$target = new PostTarget( 'post' );

		$result = $this->op()->execute_batch( $ids, [ 'permanent' => true ], $target );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->succeeded() );

		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ) );
		}
	}

	public function test_execute_batch_records_failures_for_missing_ids(): void {
		$ok_id  = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$target = new PostTarget( 'post' );

		$result = $this->op()->execute_batch( [ $ok_id, 999999 ], [ 'permanent' => false ], $target );

		$this->assertSame( 2, $result->processed() );
		$this->assertSame( 1, $result->succeeded() );
		$this->assertSame( 1, $result->failed() );
		$this->assertArrayHasKey( 999999, $result->item_errors() );
	}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter DeleteOperationTest`
Expected: FAIL — `execute_batch` returns zeroes.

- [ ] **Step 3: Implement**

Replace `execute_batch` in `src/Operations/DeleteOperation.php`:

```php
	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
		$permanent   = ! empty( $params['permanent'] );
		$succeeded   = 0;
		$failed      = 0;
		$item_errors = [];

		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$post = get_post( $id );

			if ( null === $post ) {
				++$failed;
				$item_errors[ $id ] = 'Post not found.';
				continue;
			}

			$result = $permanent ? wp_delete_post( $id, true ) : wp_trash_post( $id );

			if ( false === $result || null === $result ) {
				++$failed;
				$item_errors[ $id ] = $permanent ? 'wp_delete_post returned false.' : 'wp_trash_post returned false.';
				continue;
			}

			++$succeeded;
		}

		return BatchResult::of( count( $ids ), $succeeded, $failed, $item_errors );
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter DeleteOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/DeleteOperation.php tests/integration/Operations/DeleteOperationTest.php
git commit -m "implement DeleteOperation execute_batch with trash and permanent modes"
```

---

## Task 7: `DeleteOperation::undo()`

**Files:**
- Modify: `src/Operations/DeleteOperation.php`
- Modify: `tests/integration/Operations/DeleteOperationTest.php`

Undo loads the operation row via `OperationRepository`, iterates `affected_ids`, calls `wp_untrash_post`. If the stored `params.permanent` was true, returns a structured error.

Inject `OperationRepository` into the constructor for lookup. Extends the signature from Task 5.

- [ ] **Step 1: Add failing tests**

Append to `DeleteOperationTest`:

```php
	public function test_undo_restores_trashed_posts(): void {
		global $wpdb;
		\ContentOps\Database\Schema::install();
		$repo = new \ContentOps\History\OperationRepository( $wpdb );

		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$saved = $repo->create(
			\ContentOps\History\Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => false ] )
		);
		$repo->mark_completed( $saved->id(), $ids );

		$op = new DeleteOperation( new TokenGenerator( 'test-salt' ), new TokenStore( 300 ), $repo );
		$result = $op->undo( $saved->id() );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->restored() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}

	public function test_undo_rejects_permanent_deletes(): void {
		global $wpdb;
		\ContentOps\Database\Schema::install();
		$repo = new \ContentOps\History\OperationRepository( $wpdb );

		$saved = $repo->create(
			\ContentOps\History\Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => true ] )
		);
		$repo->mark_completed( $saved->id(), [ 123 ] );

		$op = new DeleteOperation( new TokenGenerator( 'test-salt' ), new TokenStore( 300 ), $repo );
		$result = $op->undo( $saved->id() );

		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.undo.permanent_delete', $result->get_error()->code() );
	}

	public function test_undo_rejects_missing_operation(): void {
		global $wpdb;
		\ContentOps\Database\Schema::install();
		$repo = new \ContentOps\History\OperationRepository( $wpdb );

		$op = new DeleteOperation( new TokenGenerator( 'test-salt' ), new TokenStore( 300 ), $repo );
		$result = $op->undo( 999999 );

		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.undo.not_found', $result->get_error()->code() );
	}
```

Also update the helper:

```php
	private function op(): DeleteOperation {
		global $wpdb;
		\ContentOps\Database\Schema::install();
		return new DeleteOperation(
			new TokenGenerator( 'test-salt' ),
			new TokenStore( 300 ),
			new \ContentOps\History\OperationRepository( $wpdb )
		);
	}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter DeleteOperationTest`
Expected: FAIL — constructor signature mismatch, undo returns `not_implemented`.

- [ ] **Step 3: Update constructor and undo()**

Edit `src/Operations/DeleteOperation.php`:

```php
	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	private \ContentOps\History\OperationRepository $operations;

	public function __construct(
		TokenGenerator $token_generator,
		TokenStore $token_store,
		\ContentOps\History\OperationRepository $operations
	) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
		$this->operations      = $operations;
	}
```

Replace `undo`:

```php
	public function undo( int $operation_id ): UndoResult {
		$op = $this->operations->find( $operation_id );
		if ( null === $op ) {
			return UndoResult::error( new ContentOpsError( 'co.undo.not_found', 'Operation not found.', [ 'operation_id' => $operation_id ] ) );
		}

		if ( ! empty( $op->params()['permanent'] ) ) {
			return UndoResult::error(
				new ContentOpsError(
					'co.undo.permanent_delete',
					'Permanent deletions cannot be undone.',
					[ 'operation_id' => $operation_id ]
				)
			);
		}

		$restored = 0;
		foreach ( $op->affected_ids() as $id ) {
			if ( false !== wp_untrash_post( (int) $id ) ) {
				++$restored;
			}
		}

		return UndoResult::of( $restored );
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter DeleteOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/DeleteOperation.php tests/integration/Operations/DeleteOperationTest.php
git commit -m "implement DeleteOperation undo with permanent-delete guard"
```

---

## Task 8: `DuplicateOperation` skeleton + `validate()` + `preview()`

**Files:**
- Create: `src/Operations/DuplicateOperation.php`
- Create: `tests/integration/Operations/DuplicateOperationTest.php`

Params: `target_status` (default `draft`), `reassign_author` (optional user_id), `title_suffix` (default ` (Copy)`), `include_children` (bool, default false). `validate()` checks status is registered and author exists if supplied.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace ContentOps\Tests\Integration\Operations;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Operations\DuplicateOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DuplicateOperationTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	private function op(): DuplicateOperation {
		global $wpdb;
		return new DuplicateOperation(
			new TokenGenerator( 'test-salt' ),
			new TokenStore( 300 ),
			new \ContentOps\History\OperationRepository( $wpdb )
		);
	}

	public function test_slug_and_label(): void {
		$op = $this->op();
		$this->assertSame( 'duplicate', $op->slug() );
		$this->assertNotSame( '', $op->label() );
	}

	public function test_validate_rejects_unknown_status(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[ 'target_status' => 'banana' ]
		);
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_status', $result->get_error()->code() );
	}

	public function test_validate_rejects_missing_author(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[ 'reassign_author' => 999999 ]
		);
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_author', $result->get_error()->code() );
	}

	public function test_validate_accepts_valid_params(): void {
		$user_id = self::factory()->user->create();
		$result  = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[ 'target_status' => 'draft', 'reassign_author' => $user_id, 'title_suffix' => ' (Copy)' ]
		);
		$this->assertTrue( $result->is_ok() );
	}

	public function test_preview_returns_count_and_token(): void {
		self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );

		$preview = $this->op()->preview(
			QueryArgs::from_array( [ 'status' => [ 'publish' ] ] ),
			[],
			new PostTarget( 'post' )
		);

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 2, $preview->count() );
		$this->assertNotSame( '', $preview->preview_token() );
	}

	public function test_params_schema_lists_all_params(): void {
		$schema = $this->op()->get_params_schema();
		$this->assertArrayHasKey( 'target_status', $schema['properties'] );
		$this->assertArrayHasKey( 'reassign_author', $schema['properties'] );
		$this->assertArrayHasKey( 'title_suffix', $schema['properties'] );
		$this->assertArrayHasKey( 'include_children', $schema['properties'] );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter DuplicateOperationTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

Create `src/Operations/DuplicateOperation.php`:

```php
<?php
namespace ContentOps\Operations;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\OperationInterface;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Contracts\UndoResult;
use ContentOps\Contracts\ValidationResult;
use ContentOps\Errors\ContentOpsError;
use ContentOps\History\OperationRepository;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;

final class DuplicateOperation implements OperationInterface {

	private const SAMPLE_SIZE  = 20;
	private const DEFAULT_SUFFIX = ' (Copy)';

	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	private OperationRepository $operations;

	public function __construct( TokenGenerator $token_generator, TokenStore $token_store, OperationRepository $operations ) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
		$this->operations      = $operations;
	}

	public function slug(): string {
		return 'duplicate';
	}

	public function label(): string {
		return __( 'Duplicate', 'content-ops' );
	}

	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'target_status'    => [ 'type' => 'string', 'default' => 'draft' ],
				'reassign_author'  => [ 'type' => 'integer' ],
				'title_suffix'     => [ 'type' => 'string', 'default' => self::DEFAULT_SUFFIX ],
				'include_children' => [ 'type' => 'boolean', 'default' => false ],
			],
		];
	}

	public function validate( QueryArgs $args, array $params ): ValidationResult {
		if ( isset( $params['target_status'] ) ) {
			$status = (string) $params['target_status'];
			if ( null === get_post_status_object( $status ) ) {
				return ValidationResult::error(
					new ContentOpsError( 'co.params.invalid_status', 'Unknown post status.', [ 'target_status' => $status ] )
				);
			}
		}

		if ( isset( $params['reassign_author'] ) ) {
			$user_id = (int) $params['reassign_author'];
			if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
				return ValidationResult::error(
					new ContentOpsError( 'co.params.invalid_author', 'User not found.', [ 'reassign_author' => $user_id ] )
				);
			}
		}

		return ValidationResult::ok();
	}

	public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult {
		$count      = $target->count( $args );
		$sample_ids = $target->query( $args, self::SAMPLE_SIZE, 0 );

		$payload = [
			'target'     => $target->slug(),
			'operation'  => $this->slug(),
			'filters'    => $args->to_array(),
			'params'     => $params,
			'sample_ids' => $sample_ids,
			'count'      => $count,
		];

		$token = $this->token_generator->generate( $payload );
		$this->token_store->store( $token, $payload );

		return PreviewResult::of( $count, $sample_ids, $token );
	}

	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
		return BatchResult::of( 0, 0, 0 );
	}

	public function supports_undo(): bool {
		return true;
	}

	public function undo( int $operation_id ): UndoResult {
		return UndoResult::error( new ContentOpsError( 'co.undo.not_implemented', 'Not implemented yet.' ) );
	}
}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter DuplicateOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/DuplicateOperation.php tests/integration/Operations/DuplicateOperationTest.php
git commit -m "add DuplicateOperation skeleton with validate and preview"
```

---

## Task 9: `DuplicateOperation::execute_batch()`

**Files:**
- Modify: `src/Operations/DuplicateOperation.php`
- Modify: `tests/integration/Operations/DuplicateOperationTest.php`

For each source post: insert a new post copying `post_title` (with suffix), `post_content`, `post_excerpt`, `post_type`; set `post_status` to `target_status` (default `draft`); set author per `reassign_author` else original author; copy all post meta except keys beginning with `_edit_lock`, `_edit_last`; copy taxonomy terms; copy the `_thumbnail_id`. Return list of newly created IDs in `item_errors` key mapping? No — newly created IDs need to be surfaced through the Operation row, not the BatchResult. To keep `BatchResult` simple, the duplicate IDs are returned via a side-channel: `DuplicateOperation` stashes them on an instance property that `ExecutionService` reads after each batch. Simpler alternative used here: use `do_action( 'content_ops_duplicate_created', $new_id, $source_id, $operation_id )` and have `ExecutionService` collect via a callback. For Phase 1a we take the simpler path: `execute_batch` accepts an optional `operation_id` in `$params` (`__operation_id`) and writes snapshots that record the new IDs as the "affected" set, consumed by the caller.

Decision: `execute_batch` tracks `new_ids` in an instance buffer (`$this->last_new_ids`) reset per call, accessible via `last_new_ids(): array`. `ExecutionService` calls this after every batch. Undo reads the row's `affected_ids` (which `ExecutionService` writes using `last_new_ids`).

- [ ] **Step 1: Add failing tests**

Append to `DuplicateOperationTest`:

```php
	public function test_execute_batch_duplicates_posts_with_suffix_and_draft(): void {
		$source = (int) self::factory()->post->create(
			[
				'post_title'   => 'Hello',
				'post_content' => 'Body',
				'post_status'  => 'publish',
			]
		);

		$op     = $this->op();
		$target = new PostTarget( 'post' );

		$result = $op->execute_batch( [ $source ], [], $target );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 1, $result->succeeded() );
		$this->assertSame( 0, $result->failed() );

		$new_ids = $op->last_new_ids();
		$this->assertCount( 1, $new_ids );
		$copy = get_post( $new_ids[0] );
		$this->assertSame( 'Hello (Copy)', $copy->post_title );
		$this->assertSame( 'Body', $copy->post_content );
		$this->assertSame( 'draft', $copy->post_status );
	}

	public function test_execute_batch_copies_meta_and_thumbnail_but_skips_edit_lock(): void {
		$source = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $source, 'custom_field', 'value' );
		update_post_meta( $source, '_thumbnail_id', 42 );
		update_post_meta( $source, '_edit_lock', '1234567890:1' );

		$op = $this->op();
		$op->execute_batch( [ $source ], [], new PostTarget( 'post' ) );
		$new_id = $op->last_new_ids()[0];

		$this->assertSame( 'value', get_post_meta( $new_id, 'custom_field', true ) );
		$this->assertSame( '42', (string) get_post_meta( $new_id, '_thumbnail_id', true ) );
		$this->assertSame( '', get_post_meta( $new_id, '_edit_lock', true ) );
	}

	public function test_execute_batch_copies_taxonomies(): void {
		$source  = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = (int) self::factory()->term->create( [ 'taxonomy' => 'category' ] );
		wp_set_post_terms( $source, [ $term_id ], 'category' );

		$op = $this->op();
		$op->execute_batch( [ $source ], [], new PostTarget( 'post' ) );
		$new_id = $op->last_new_ids()[0];

		$terms = wp_get_post_terms( $new_id, 'category', [ 'fields' => 'ids' ] );
		$this->assertSame( [ $term_id ], array_map( 'intval', $terms ) );
	}

	public function test_execute_batch_uses_target_status_param(): void {
		$source = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$op = $this->op();
		$op->execute_batch( [ $source ], [ 'target_status' => 'pending', 'title_suffix' => '' ], new PostTarget( 'post' ) );
		$new_id = $op->last_new_ids()[0];

		$this->assertSame( 'pending', get_post_status( $new_id ) );
		$this->assertSame( get_post( $source )->post_title, get_post( $new_id )->post_title );
	}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter DuplicateOperationTest`
Expected: FAIL — `last_new_ids()` missing, duplicates not created.

- [ ] **Step 3: Implement**

Add property and method to `src/Operations/DuplicateOperation.php`:

```php
	/** @var int[] */
	private array $last_new_ids = [];

	/** @return int[] */
	public function last_new_ids(): array {
		return $this->last_new_ids;
	}
```

Replace `execute_batch`:

```php
	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
		$this->last_new_ids = [];
		$target_status      = isset( $params['target_status'] ) ? (string) $params['target_status'] : 'draft';
		$reassign_author    = isset( $params['reassign_author'] ) ? (int) $params['reassign_author'] : 0;
		$title_suffix       = array_key_exists( 'title_suffix', $params ) ? (string) $params['title_suffix'] : self::DEFAULT_SUFFIX;

		$succeeded   = 0;
		$failed      = 0;
		$item_errors = [];

		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$source = get_post( $id );
			if ( null === $source ) {
				++$failed;
				$item_errors[ $id ] = 'Post not found.';
				continue;
			}

			$new_post = [
				'post_title'   => $source->post_title . $title_suffix,
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
				'post_type'    => $source->post_type,
				'post_status'  => $target_status,
				'post_author'  => $reassign_author > 0 ? $reassign_author : (int) $source->post_author,
				'post_parent'  => (int) $source->post_parent,
				'menu_order'   => (int) $source->menu_order,
			];

			$new_id = wp_insert_post( $new_post, true );
			if ( is_wp_error( $new_id ) || 0 === (int) $new_id ) {
				++$failed;
				$item_errors[ $id ] = is_wp_error( $new_id ) ? $new_id->get_error_message() : 'wp_insert_post returned 0.';
				continue;
			}

			$this->copy_meta( $id, (int) $new_id );
			$this->copy_taxonomies( $id, (int) $new_id, $source->post_type );

			$this->last_new_ids[] = (int) $new_id;
			++$succeeded;
		}

		return BatchResult::of( count( $ids ), $succeeded, $failed, $item_errors );
	}

	private function copy_meta( int $source_id, int $new_id ): void {
		$meta = get_post_meta( $source_id );
		foreach ( $meta as $key => $values ) {
			if ( in_array( $key, [ '_edit_lock', '_edit_last' ], true ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}
	}

	private function copy_taxonomies( int $source_id, int $new_id, string $post_type ): void {
		foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
			$terms = wp_get_object_terms( $source_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( is_array( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_id, array_map( 'intval', $terms ), $taxonomy );
			}
		}
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter DuplicateOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/DuplicateOperation.php tests/integration/Operations/DuplicateOperationTest.php
git commit -m "implement DuplicateOperation execute_batch with meta and taxonomy copy"
```

---

## Task 10: `DuplicateOperation::undo()`

**Files:**
- Modify: `src/Operations/DuplicateOperation.php`
- Modify: `tests/integration/Operations/DuplicateOperationTest.php`

Deletes the duplicate post IDs stored in the operation row's `affected_ids`. Uses hard delete (`wp_delete_post( $id, true )`) — undoing a duplicate should remove the copy entirely, not trash it.

- [ ] **Step 1: Add failing test**

Append to `DuplicateOperationTest`:

```php
	public function test_undo_deletes_the_duplicate_posts(): void {
		global $wpdb;
		$repo = new \ContentOps\History\OperationRepository( $wpdb );

		$source = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$op     = $this->op();
		$op->execute_batch( [ $source ], [], new PostTarget( 'post' ) );
		$new_ids = $op->last_new_ids();

		$saved = $repo->create(
			\ContentOps\History\Operation::newly_created( 'duplicate', 'post', 0, [], [] )
		);
		$repo->mark_completed( $saved->id(), $new_ids );

		$result = $op->undo( $saved->id() );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( count( $new_ids ), $result->restored() );
		foreach ( $new_ids as $id ) {
			$this->assertNull( get_post( $id ) );
		}
		$this->assertNotNull( get_post( $source ) );
	}

	public function test_undo_missing_operation_returns_error(): void {
		$op = $this->op();
		$result = $op->undo( 999999 );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.undo.not_found', $result->get_error()->code() );
	}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter DuplicateOperationTest`
Expected: FAIL — `undo` returns `not_implemented`.

- [ ] **Step 3: Implement**

Replace `undo` in `src/Operations/DuplicateOperation.php`:

```php
	public function undo( int $operation_id ): UndoResult {
		$op = $this->operations->find( $operation_id );
		if ( null === $op ) {
			return UndoResult::error( new ContentOpsError( 'co.undo.not_found', 'Operation not found.', [ 'operation_id' => $operation_id ] ) );
		}

		$restored = 0;
		foreach ( $op->affected_ids() as $id ) {
			if ( wp_delete_post( (int) $id, true ) ) {
				++$restored;
			}
		}
		return UndoResult::of( $restored );
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter DuplicateOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/DuplicateOperation.php tests/integration/Operations/DuplicateOperationTest.php
git commit -m "implement DuplicateOperation undo by deleting duplicates"
```

---

## Task 11: `BulkEditOperation` skeleton + `validate()` + `preview()`

**Files:**
- Create: `src/Operations/BulkEditOperation.php`
- Create: `tests/integration/Operations/BulkEditOperationTest.php`

Params: `set_status`, `reassign_author`, `shift_dates_days`, `taxonomy_add` (`{taxonomy, term_ids}`), `taxonomy_remove` (same shape), `password`, `comment_status`, `menu_order`.

`validate()`: reject unknown statuses, missing authors, unknown taxonomies, non-integer `shift_dates_days` / `menu_order`, and `comment_status` values outside `open|closed`.

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\Operations;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Operations\BulkEditOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class BulkEditOperationTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	private function op(): BulkEditOperation {
		global $wpdb;
		return new BulkEditOperation(
			new TokenGenerator( 'test-salt' ),
			new TokenStore( 300 ),
			new \ContentOps\History\OperationRepository( $wpdb ),
			new \ContentOps\History\SnapshotRepository( $wpdb )
		);
	}

	public function test_slug_and_label(): void {
		$op = $this->op();
		$this->assertSame( 'edit', $op->slug() );
		$this->assertNotSame( '', $op->label() );
	}

	public function test_validate_rejects_unknown_status(): void {
		$result = $this->op()->validate( QueryArgs::from_array( [] ), [ 'set_status' => 'banana' ] );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_status', $result->get_error()->code() );
	}

	public function test_validate_rejects_non_integer_shift(): void {
		$result = $this->op()->validate( QueryArgs::from_array( [] ), [ 'shift_dates_days' => 'lots' ] );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_shift', $result->get_error()->code() );
	}

	public function test_validate_rejects_bad_comment_status(): void {
		$result = $this->op()->validate( QueryArgs::from_array( [] ), [ 'comment_status' => 'maybe' ] );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_comment_status', $result->get_error()->code() );
	}

	public function test_validate_rejects_unknown_taxonomy(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[ 'taxonomy_add' => [ 'taxonomy' => 'fake_tax', 'term_ids' => [ 1 ] ] ]
		);
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.params.invalid_taxonomy', $result->get_error()->code() );
	}

	public function test_validate_accepts_combined_params(): void {
		$result = $this->op()->validate(
			QueryArgs::from_array( [] ),
			[
				'set_status'       => 'draft',
				'shift_dates_days' => 7,
				'comment_status'   => 'closed',
				'taxonomy_add'     => [ 'taxonomy' => 'category', 'term_ids' => [ 1 ] ],
			]
		);
		$this->assertTrue( $result->is_ok() );
	}

	public function test_preview_returns_expected_shape(): void {
		self::factory()->post->create_many( 4, [ 'post_status' => 'publish' ] );
		$preview = $this->op()->preview(
			QueryArgs::from_array( [ 'status' => [ 'publish' ] ] ),
			[ 'set_status' => 'draft' ],
			new PostTarget( 'post' )
		);

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 4, $preview->count() );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter BulkEditOperationTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

Create `src/Operations/BulkEditOperation.php`:

```php
<?php
namespace ContentOps\Operations;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\OperationInterface;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Contracts\TargetInterface;
use ContentOps\Contracts\UndoResult;
use ContentOps\Contracts\ValidationResult;
use ContentOps\Errors\ContentOpsError;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;

final class BulkEditOperation implements OperationInterface {

	private const SAMPLE_SIZE = 20;

	private TokenGenerator $token_generator;
	private TokenStore $token_store;
	private OperationRepository $operations;
	private SnapshotRepository $snapshots;

	public function __construct(
		TokenGenerator $token_generator,
		TokenStore $token_store,
		OperationRepository $operations,
		SnapshotRepository $snapshots
	) {
		$this->token_generator = $token_generator;
		$this->token_store     = $token_store;
		$this->operations      = $operations;
		$this->snapshots       = $snapshots;
	}

	public function slug(): string {
		return 'edit';
	}

	public function label(): string {
		return __( 'Bulk edit', 'content-ops' );
	}

	public function get_params_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'set_status'       => [ 'type' => 'string' ],
				'reassign_author'  => [ 'type' => 'integer' ],
				'shift_dates_days' => [ 'type' => 'integer' ],
				'taxonomy_add'     => [ 'type' => 'object' ],
				'taxonomy_remove'  => [ 'type' => 'object' ],
				'password'         => [ 'type' => 'string' ],
				'comment_status'   => [ 'type' => 'string', 'enum' => [ 'open', 'closed' ] ],
				'menu_order'       => [ 'type' => 'integer' ],
			],
		];
	}

	public function validate( QueryArgs $args, array $params ): ValidationResult {
		if ( isset( $params['set_status'] ) && null === get_post_status_object( (string) $params['set_status'] ) ) {
			return ValidationResult::error(
				new ContentOpsError( 'co.params.invalid_status', 'Unknown post status.', [ 'set_status' => $params['set_status'] ] )
			);
		}

		if ( isset( $params['reassign_author'] ) ) {
			$user_id = (int) $params['reassign_author'];
			if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
				return ValidationResult::error(
					new ContentOpsError( 'co.params.invalid_author', 'User not found.', [ 'reassign_author' => $user_id ] )
				);
			}
		}

		if ( isset( $params['shift_dates_days'] ) && ! is_int( $params['shift_dates_days'] ) ) {
			return ValidationResult::error(
				new ContentOpsError( 'co.params.invalid_shift', 'shift_dates_days must be an integer.' )
			);
		}

		if ( isset( $params['menu_order'] ) && ! is_int( $params['menu_order'] ) ) {
			return ValidationResult::error(
				new ContentOpsError( 'co.params.invalid_menu_order', 'menu_order must be an integer.' )
			);
		}

		if ( isset( $params['comment_status'] ) && ! in_array( $params['comment_status'], [ 'open', 'closed' ], true ) ) {
			return ValidationResult::error(
				new ContentOpsError( 'co.params.invalid_comment_status', 'comment_status must be open or closed.' )
			);
		}

		foreach ( [ 'taxonomy_add', 'taxonomy_remove' ] as $key ) {
			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}
			$spec = $params[ $key ];
			if ( ! is_array( $spec ) || empty( $spec['taxonomy'] ) || ! taxonomy_exists( (string) $spec['taxonomy'] ) ) {
				return ValidationResult::error(
					new ContentOpsError( 'co.params.invalid_taxonomy', 'Unknown taxonomy.', [ 'param' => $key ] )
				);
			}
		}

		return ValidationResult::ok();
	}

	public function preview( QueryArgs $args, array $params, TargetInterface $target ): PreviewResult {
		$count      = $target->count( $args );
		$sample_ids = $target->query( $args, self::SAMPLE_SIZE, 0 );

		$payload = [
			'target'     => $target->slug(),
			'operation'  => $this->slug(),
			'filters'    => $args->to_array(),
			'params'     => $params,
			'sample_ids' => $sample_ids,
			'count'      => $count,
		];

		$token = $this->token_generator->generate( $payload );
		$this->token_store->store( $token, $payload );

		return PreviewResult::of( $count, $sample_ids, $token );
	}

	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
		return BatchResult::of( 0, 0, 0 );
	}

	public function supports_undo(): bool {
		return true;
	}

	public function undo( int $operation_id ): UndoResult {
		return UndoResult::error( new ContentOpsError( 'co.undo.not_implemented', 'Not implemented yet.' ) );
	}
}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter BulkEditOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/BulkEditOperation.php tests/integration/Operations/BulkEditOperationTest.php
git commit -m "add BulkEditOperation skeleton with validate and preview"
```

---

## Task 12: `BulkEditOperation::execute_batch()` with snapshots

**Files:**
- Modify: `src/Operations/BulkEditOperation.php`
- Modify: `tests/integration/Operations/BulkEditOperationTest.php`

For each post, snapshot every field the caller is changing, then apply the update. Snapshots are keyed by the operation row id passed through `$params['__operation_id']` (set by `ExecutionService` before dispatch). `execute_batch` looks up the id; if absent (agent called raw), snapshots are skipped and a note added to `item_errors`? Simpler: if `__operation_id` is absent, the operation still runs but undo won't have data. We surface a warning via `$params` — but `BatchResult` doesn't carry warnings. Decision: require `__operation_id` to be set. Callers that lack it (direct tests) pass `0` and accept that undo will have nothing to restore.

Snapshot fields: one snapshot row per (post_id, field). Fields snapshotted: `post_status`, `post_author`, `post_date`, `post_date_gmt`, `comment_status`, `menu_order`, `post_password`. Taxonomy snapshots use `field = 'taxonomy:{taxonomy}'` and `old_value = json_encode( term_ids )`.

- [ ] **Step 1: Add failing tests**

Append to `BulkEditOperationTest`:

```php
	public function test_execute_batch_changes_status_and_snapshots_old_value(): void {
		global $wpdb;
		$repo    = new \ContentOps\History\OperationRepository( $wpdb );
		$op_row  = $repo->create( \ContentOps\History\Operation::newly_created( 'edit', 'post', 0, [], [] ) );
		$ids     = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );

		$result = $this->op()->execute_batch(
			$ids,
			[ 'set_status' => 'draft', '__operation_id' => $op_row->id() ],
			new PostTarget( 'post' )
		);

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->succeeded() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'draft', get_post_status( $id ) );
		}

		$snapshots = ( new \ContentOps\History\SnapshotRepository( $wpdb ) )->for_operation( $op_row->id() );
		$this->assertCount( 2, $snapshots );
		foreach ( $snapshots as $snap ) {
			$this->assertSame( 'post_status', $snap->field() );
			$this->assertSame( 'publish', $snap->old_value() );
		}
	}

	public function test_execute_batch_shifts_dates(): void {
		global $wpdb;
		$repo   = new \ContentOps\History\OperationRepository( $wpdb );
		$op_row = $repo->create( \ContentOps\History\Operation::newly_created( 'edit', 'post', 0, [], [] ) );
		$id     = (int) self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_date'   => '2024-06-01 10:00:00',
				'post_date_gmt' => '2024-06-01 10:00:00',
			]
		);

		$this->op()->execute_batch(
			[ $id ],
			[ 'shift_dates_days' => 7, '__operation_id' => $op_row->id() ],
			new PostTarget( 'post' )
		);

		$this->assertSame( '2024-06-08 10:00:00', get_post( $id )->post_date );
	}

	public function test_execute_batch_adds_taxonomy_terms(): void {
		global $wpdb;
		$repo    = new \ContentOps\History\OperationRepository( $wpdb );
		$op_row  = $repo->create( \ContentOps\History\Operation::newly_created( 'edit', 'post', 0, [], [] ) );
		$id      = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = (int) self::factory()->term->create( [ 'taxonomy' => 'category' ] );

		$this->op()->execute_batch(
			[ $id ],
			[
				'taxonomy_add'   => [ 'taxonomy' => 'category', 'term_ids' => [ $term_id ] ],
				'__operation_id' => $op_row->id(),
			],
			new PostTarget( 'post' )
		);

		$terms = wp_get_post_terms( $id, 'category', [ 'fields' => 'ids' ] );
		$this->assertContains( $term_id, array_map( 'intval', $terms ) );
	}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter BulkEditOperationTest`
Expected: FAIL — no updates happen.

- [ ] **Step 3: Implement**

Replace `execute_batch` in `src/Operations/BulkEditOperation.php`:

```php
	public function execute_batch( array $ids, array $params, TargetInterface $target ): BatchResult {
		$operation_id = isset( $params['__operation_id'] ) ? (int) $params['__operation_id'] : 0;
		$succeeded    = 0;
		$failed       = 0;
		$item_errors  = [];
		$snapshots    = [];

		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$post = get_post( $id );
			if ( null === $post ) {
				++$failed;
				$item_errors[ $id ] = 'Post not found.';
				continue;
			}

			$update = [ 'ID' => $id ];

			if ( isset( $params['set_status'] ) ) {
				$snapshots[]            = new \ContentOps\History\Snapshot( $operation_id, 'post', $id, 'post_status', (string) $post->post_status );
				$update['post_status']  = (string) $params['set_status'];
			}
			if ( isset( $params['reassign_author'] ) ) {
				$snapshots[]            = new \ContentOps\History\Snapshot( $operation_id, 'post', $id, 'post_author', (string) $post->post_author );
				$update['post_author']  = (int) $params['reassign_author'];
			}
			if ( isset( $params['shift_dates_days'] ) ) {
				$snapshots[]              = new \ContentOps\History\Snapshot( $operation_id, 'post', $id, 'post_date', (string) $post->post_date );
				$snapshots[]              = new \ContentOps\History\Snapshot( $operation_id, 'post', $id, 'post_date_gmt', (string) $post->post_date_gmt );
				$shifted                  = gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date ) + ( (int) $params['shift_dates_days'] * DAY_IN_SECONDS ) );
				$shifted_gmt              = gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date_gmt ) + ( (int) $params['shift_dates_days'] * DAY_IN_SECONDS ) );
				$update['post_date']      = $shifted;
				$update['post_date_gmt']  = $shifted_gmt;
			}
			if ( isset( $params['password'] ) ) {
				$snapshots[]              = new \ContentOps\History\Snapshot( $operation_id, 'post', $id, 'post_password', (string) $post->post_password );
				$update['post_password']  = (string) $params['password'];
			}
			if ( isset( $params['comment_status'] ) ) {
				$snapshots[]               = new \ContentOps\History\Snapshot( $operation_id, 'post', $id, 'comment_status', (string) $post->comment_status );
				$update['comment_status']  = (string) $params['comment_status'];
			}
			if ( isset( $params['menu_order'] ) ) {
				$snapshots[]           = new \ContentOps\History\Snapshot( $operation_id, 'post', $id, 'menu_order', (string) $post->menu_order );
				$update['menu_order']  = (int) $params['menu_order'];
			}

			$result = wp_update_post( $update, true );
			if ( is_wp_error( $result ) || 0 === (int) $result ) {
				++$failed;
				$item_errors[ $id ] = is_wp_error( $result ) ? $result->get_error_message() : 'wp_update_post failed.';
				continue;
			}

			if ( isset( $params['taxonomy_add'] ) ) {
				$tax      = $params['taxonomy_add'];
				$existing = wp_get_object_terms( $id, (string) $tax['taxonomy'], [ 'fields' => 'ids' ] );
				$snapshots[] = new \ContentOps\History\Snapshot(
					$operation_id,
					'post',
					$id,
					'taxonomy:' . (string) $tax['taxonomy'],
					(string) wp_json_encode( array_map( 'intval', is_array( $existing ) ? $existing : [] ) )
				);
				wp_set_object_terms( $id, array_map( 'intval', (array) $tax['term_ids'] ), (string) $tax['taxonomy'], true );
			}
			if ( isset( $params['taxonomy_remove'] ) ) {
				$tax      = $params['taxonomy_remove'];
				$existing = wp_get_object_terms( $id, (string) $tax['taxonomy'], [ 'fields' => 'ids' ] );
				$snapshots[] = new \ContentOps\History\Snapshot(
					$operation_id,
					'post',
					$id,
					'taxonomy:' . (string) $tax['taxonomy'],
					(string) wp_json_encode( array_map( 'intval', is_array( $existing ) ? $existing : [] ) )
				);
				wp_remove_object_terms( $id, array_map( 'intval', (array) $tax['term_ids'] ), (string) $tax['taxonomy'] );
			}

			++$succeeded;
		}

		if ( $operation_id > 0 && ! empty( $snapshots ) ) {
			$this->snapshots->bulk_insert( $snapshots );
		}

		return BatchResult::of( count( $ids ), $succeeded, $failed, $item_errors );
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter BulkEditOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/BulkEditOperation.php tests/integration/Operations/BulkEditOperationTest.php
git commit -m "implement BulkEditOperation execute_batch with snapshots"
```

---

## Task 13: `BulkEditOperation::undo()` from snapshots

**Files:**
- Modify: `src/Operations/BulkEditOperation.php`
- Modify: `tests/integration/Operations/BulkEditOperationTest.php`

Load snapshots for the operation; group by `object_id`; for each post, build a `wp_update_post` array from the fields and call `wp_set_object_terms` for `taxonomy:*` fields.

- [ ] **Step 1: Add failing test**

Append to `BulkEditOperationTest`:

```php
	public function test_undo_restores_status_from_snapshot(): void {
		global $wpdb;
		$repo   = new \ContentOps\History\OperationRepository( $wpdb );
		$op_row = $repo->create( \ContentOps\History\Operation::newly_created( 'edit', 'post', 0, [], [ 'set_status' => 'draft' ] ) );
		$ids    = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );

		$op = $this->op();
		$op->execute_batch( $ids, [ 'set_status' => 'draft', '__operation_id' => $op_row->id() ], new PostTarget( 'post' ) );
		$repo->mark_completed( $op_row->id(), $ids );

		$result = $op->undo( $op_row->id() );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->restored() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}

	public function test_undo_restores_taxonomy_terms(): void {
		global $wpdb;
		$repo    = new \ContentOps\History\OperationRepository( $wpdb );
		$op_row  = $repo->create( \ContentOps\History\Operation::newly_created( 'edit', 'post', 0, [], [] ) );
		$id      = (int) self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$term_id = (int) self::factory()->term->create( [ 'taxonomy' => 'category' ] );

		$op = $this->op();
		$op->execute_batch(
			[ $id ],
			[ 'taxonomy_add' => [ 'taxonomy' => 'category', 'term_ids' => [ $term_id ] ], '__operation_id' => $op_row->id() ],
			new PostTarget( 'post' )
		);
		$repo->mark_completed( $op_row->id(), [ $id ] );

		$op->undo( $op_row->id() );

		$terms = wp_get_post_terms( $id, 'category', [ 'fields' => 'ids' ] );
		$this->assertNotContains( $term_id, array_map( 'intval', $terms ) );
	}

	public function test_undo_missing_operation_returns_error(): void {
		$result = $this->op()->undo( 999999 );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'co.undo.not_found', $result->get_error()->code() );
	}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter BulkEditOperationTest`
Expected: FAIL — `undo` returns `not_implemented`.

- [ ] **Step 3: Implement**

Replace `undo` in `src/Operations/BulkEditOperation.php`:

```php
	public function undo( int $operation_id ): UndoResult {
		$op = $this->operations->find( $operation_id );
		if ( null === $op ) {
			return UndoResult::error( new ContentOpsError( 'co.undo.not_found', 'Operation not found.', [ 'operation_id' => $operation_id ] ) );
		}

		$snaps     = $this->snapshots->for_operation( $operation_id );
		$by_id     = [];
		foreach ( $snaps as $snap ) {
			$by_id[ $snap->object_id() ][ $snap->field() ] = $snap->old_value();
		}

		$restored = 0;
		foreach ( $by_id as $post_id => $fields ) {
			$update = [ 'ID' => (int) $post_id ];
			foreach ( $fields as $field => $old_value ) {
				if ( 0 === strpos( $field, 'taxonomy:' ) ) {
					$taxonomy = substr( $field, strlen( 'taxonomy:' ) );
					$term_ids = json_decode( (string) $old_value, true );
					wp_set_object_terms( (int) $post_id, is_array( $term_ids ) ? array_map( 'intval', $term_ids ) : [], $taxonomy );
					continue;
				}
				if ( in_array( $field, [ 'menu_order', 'post_author' ], true ) ) {
					$update[ $field ] = (int) $old_value;
				} else {
					$update[ $field ] = (string) $old_value;
				}
			}
			if ( count( $update ) > 1 ) {
				$result = wp_update_post( $update, true );
				if ( ! is_wp_error( $result ) && 0 !== (int) $result ) {
					++$restored;
				}
			} else {
				++$restored;
			}
		}

		return UndoResult::of( $restored );
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter BulkEditOperationTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Operations/BulkEditOperation.php tests/integration/Operations/BulkEditOperationTest.php
git commit -m "implement BulkEditOperation undo from snapshots"
```

---

## Task 14: `ExecutionService` and `OperationRunner`

**Files:**
- Create: `src/Execution/ExecutionService.php`
- Create: `src/Execution/OperationRunner.php`
- Create: `tests/integration/Execution/ExecutionServiceTest.php`
- Create: `tests/integration/Execution/OperationRunnerTest.php`

`ExecutionService` resolves Target + Operation from registries, validates, previews (returns token), and has a `run_sync(int $operation_id): BatchResult` used by CLI. `OperationRunner` registers the `content_ops_run_operation` Action Scheduler hook and dispatches to the same synchronous code path.

- [ ] **Step 1: Write the failing ExecutionService test**

```php
<?php
namespace ContentOps\Tests\Integration\Execution;

use ContentOps\Contracts\QueryArgs;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\DeleteOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class ExecutionServiceTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	private function service(): ExecutionService {
		global $wpdb;
		$targets    = new TargetRegistry();
		$operations = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$operations->register(
			new DeleteOperation(
				new TokenGenerator( 'salt' ),
				new TokenStore( 300 ),
				new OperationRepository( $wpdb )
			)
		);

		return new ExecutionService(
			$targets,
			$operations,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);
	}

	public function test_preview_returns_preview_result(): void {
		self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$preview = $this->service()->preview( 'post', 'delete', [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );

		$this->assertTrue( $preview->is_ok() );
		$this->assertSame( 2, $preview->count() );
	}

	public function test_preview_unknown_target_returns_error(): void {
		$preview = $this->service()->preview( 'bogus', 'delete', [], [] );
		$this->assertFalse( $preview->is_ok() );
		$this->assertSame( 'co.target.unknown', $preview->get_error()->code() );
	}

	public function test_preview_unknown_operation_returns_error(): void {
		$preview = $this->service()->preview( 'post', 'bogus', [], [] );
		$this->assertFalse( $preview->is_ok() );
		$this->assertSame( 'co.operation.unknown', $preview->get_error()->code() );
	}

	public function test_preview_target_rejects_operation(): void {
		global $wpdb;
		$targets = new TargetRegistry();
		$ops     = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );

		$rejecting = new class implements \ContentOps\Contracts\OperationInterface {
			public function slug(): string { return 'move'; }
			public function label(): string { return 'Move'; }
			public function get_params_schema(): array { return [ 'type' => 'object', 'properties' => [] ]; }
			public function validate( \ContentOps\Contracts\QueryArgs $a, array $p ): \ContentOps\Contracts\ValidationResult { return \ContentOps\Contracts\ValidationResult::ok(); }
			public function preview( \ContentOps\Contracts\QueryArgs $a, array $p, \ContentOps\Contracts\TargetInterface $t ): \ContentOps\Contracts\PreviewResult { return \ContentOps\Contracts\PreviewResult::of( 0, [], '' ); }
			public function execute_batch( array $ids, array $p, \ContentOps\Contracts\TargetInterface $t ): \ContentOps\Contracts\BatchResult { return \ContentOps\Contracts\BatchResult::of( 0, 0, 0 ); }
			public function supports_undo(): bool { return false; }
			public function undo( int $id ): \ContentOps\Contracts\UndoResult { return \ContentOps\Contracts\UndoResult::of( 0 ); }
		};
		$ops->register( $rejecting );

		$svc = new ExecutionService(
			$targets,
			$ops,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);
		$preview = $svc->preview( 'post', 'move', [], [] );
		$this->assertFalse( $preview->is_ok() );
		$this->assertSame( 'co.target.unsupported_operation', $preview->get_error()->code() );
	}

	public function test_record_creates_history_row_and_returns_id(): void {
		$id = $this->service()->record( 'post', 'delete', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );
		$this->assertGreaterThan( 0, $id );
	}

	public function test_run_sync_executes_delete_and_marks_completed(): void {
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$svc    = $this->service();
		$op_id  = $svc->record( 'post', 'delete', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );
		$result = $svc->run_sync( $op_id );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( 2, $result->succeeded() );

		global $wpdb;
		$row = ( new OperationRepository( $wpdb ) )->find( $op_id );
		$this->assertSame( 'completed', $row->status() );
		$this->assertSame( 2, $row->affected_count() );
		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter ExecutionServiceTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement `ExecutionService`**

Create `src/Execution/ExecutionService.php`:

```php
<?php
namespace ContentOps\Execution;

use ContentOps\Contracts\BatchResult;
use ContentOps\Contracts\PreviewResult;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Errors\ContentOpsError;
use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\DuplicateOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;

final class ExecutionService {

	private const BATCH_SIZE = 50;

	private TargetRegistry $targets;
	private OperationRegistry $operations_registry;
	private OperationRepository $operations_repo;
	private SnapshotRepository $snapshots_repo;
	private TokenGenerator $token_generator;
	private TokenStore $token_store;

	public function __construct(
		TargetRegistry $targets,
		OperationRegistry $operations_registry,
		OperationRepository $operations_repo,
		SnapshotRepository $snapshots_repo,
		TokenGenerator $token_generator,
		TokenStore $token_store
	) {
		$this->targets             = $targets;
		$this->operations_registry = $operations_registry;
		$this->operations_repo     = $operations_repo;
		$this->snapshots_repo      = $snapshots_repo;
		$this->token_generator     = $token_generator;
		$this->token_store         = $token_store;
	}

	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 */
	public function preview( string $target_slug, string $operation_slug, array $filters, array $params ): PreviewResult {
		$target = $this->targets->get( $target_slug );
		if ( null === $target ) {
			return PreviewResult::error( new ContentOpsError( 'co.target.unknown', 'Unknown target.', [ 'target' => $target_slug ] ) );
		}
		$op = $this->operations_registry->get( $operation_slug );
		if ( null === $op ) {
			return PreviewResult::error( new ContentOpsError( 'co.operation.unknown', 'Unknown operation.', [ 'operation' => $operation_slug ] ) );
		}
		if ( ! $target->supports_operation( $operation_slug ) ) {
			return PreviewResult::error(
				new ContentOpsError(
					'co.target.unsupported_operation',
					'Target does not support this operation.',
					[ 'target' => $target_slug, 'operation' => $operation_slug ]
				)
			);
		}

		$args       = QueryArgs::from_array( $filters );
		$validation = $op->validate( $args, $params );
		if ( ! $validation->is_ok() ) {
			return PreviewResult::error( $validation->get_error() );
		}

		return $op->preview( $args, $params, $target );
	}

	/**
	 * @param array<string, mixed> $filters
	 * @param array<string, mixed> $params
	 */
	public function record( string $target_slug, string $operation_slug, int $user_id, array $filters, array $params ): int {
		$op = Operation::newly_created( $operation_slug, $target_slug, $user_id, $filters, $params );
		return $this->operations_repo->create( $op )->id();
	}

	public function run_sync( int $operation_id ): BatchResult {
		$row = $this->operations_repo->find( $operation_id );
		if ( null === $row ) {
			return BatchResult::error( new ContentOpsError( 'co.run.not_found', 'Operation not found.', [ 'operation_id' => $operation_id ] ) );
		}

		$target = $this->targets->get( $row->target() );
		$op     = $this->operations_registry->get( $row->type() );
		if ( null === $target || null === $op ) {
			$this->operations_repo->mark_failed( $operation_id, 'Target or operation no longer registered.' );
			return BatchResult::error( new ContentOpsError( 'co.run.unresolvable', 'Target or operation missing at run time.' ) );
		}

		$this->operations_repo->mark_running( $operation_id );

		$args      = QueryArgs::from_array( $row->filters() );
		$all_ids   = $target->query( $args );
		$params    = $row->params();
		$params['__operation_id'] = $operation_id;

		$total_processed = 0;
		$total_succeeded = 0;
		$total_failed    = 0;
		$item_errors     = [];
		$affected        = [];

		foreach ( array_chunk( $all_ids, self::BATCH_SIZE ) as $chunk ) {
			$result = $op->execute_batch( $chunk, $params, $target );
			if ( ! $result->is_ok() ) {
				$this->operations_repo->mark_failed( $operation_id, $result->get_error()->message() );
				return $result;
			}
			$total_processed += $result->processed();
			$total_succeeded += $result->succeeded();
			$total_failed    += $result->failed();
			foreach ( $result->item_errors() as $k => $v ) {
				$item_errors[ $k ] = $v;
			}

			if ( $op instanceof DuplicateOperation ) {
				$affected = array_merge( $affected, $op->last_new_ids() );
			} else {
				foreach ( $chunk as $id ) {
					if ( ! isset( $result->item_errors()[ $id ] ) ) {
						$affected[] = (int) $id;
					}
				}
			}
		}

		$this->operations_repo->mark_completed( $operation_id, $affected );

		return BatchResult::of( $total_processed, $total_succeeded, $total_failed, $item_errors );
	}
}
```

- [ ] **Step 4: Implement `OperationRunner`**

Create `src/Execution/OperationRunner.php`:

```php
<?php
namespace ContentOps\Execution;

final class OperationRunner {

	public const HOOK = 'content_ops_run_operation';

	private ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	public function register(): void {
		add_action( self::HOOK, [ $this, 'handle' ], 10, 1 );
	}

	public function handle( int $operation_id ): void {
		$this->execution->run_sync( (int) $operation_id );
	}
}
```

- [ ] **Step 5: Write failing OperationRunner test**

Create `tests/integration/Execution/OperationRunnerTest.php`:

```php
<?php
namespace ContentOps\Tests\Integration\Execution;

use ContentOps\Execution\ExecutionService;
use ContentOps\Execution\OperationRunner;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\DeleteOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class OperationRunnerTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	public function test_handle_runs_operation_synchronously(): void {
		global $wpdb;
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$targets    = new TargetRegistry();
		$operations = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$operations->register( new DeleteOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), new OperationRepository( $wpdb ) ) );

		$svc = new ExecutionService(
			$targets,
			$operations,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);

		$op_id = $svc->record( 'post', 'delete', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] );

		$runner = new OperationRunner( $svc );
		$runner->register();
		do_action( OperationRunner::HOOK, $op_id );

		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
		$row = ( new OperationRepository( $wpdb ) )->find( $op_id );
		$this->assertSame( 'completed', $row->status() );
	}
}
```

- [ ] **Step 6: Run all Execution tests**

Run: `composer test:integration -- --filter "ExecutionServiceTest|OperationRunnerTest"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Execution/ tests/integration/Execution/
git commit -m "add ExecutionService and OperationRunner"
```

---

## Task 15: Plugin boot wiring — registries + operations + runner

**Files:**
- Modify: `src/Plugin.php`
- Modify: `src/REST/RouteRegistrar.php`
- Modify: `src/CLI/CommandRegistrar.php`
- Modify: `src/Abilities/AbilitiesBridge.php`
- Create: `tests/integration/PluginWiringTest.php`

Updates `on_plugins_loaded` to construct registries, register `PostTarget` for every configured public post type (default `[ 'post', 'page' ]`, filterable via `content_ops_post_types`), register the three operations, construct `ExecutionService` and `OperationRunner`, and pass these into the registrars/bridge. The registrars now take the whole `Plugin` container or an `ExecutionService` / registries — we use dedicated setter injection to avoid churning constructor signatures.

Decision: `RouteRegistrar`, `CommandRegistrar`, `AbilitiesBridge` get a second constructor arg — `ExecutionService` — plus the two registries (for `/catalog`). We update the three existing signatures now.

- [ ] **Step 1: Write the failing wiring test**

```php
<?php
namespace ContentOps\Tests\Integration;

use ContentOps\Plugin;

final class PluginWiringTest extends TestCase {

	public function test_registries_register_expected_entries(): void {
		$plugin = Plugin::instance();

		$targets    = $plugin->get( 'target.registry' );
		$operations = $plugin->get( 'operation.registry' );

		$this->assertInstanceOf( \ContentOps\Registry\TargetRegistry::class, $targets );
		$this->assertInstanceOf( \ContentOps\Registry\OperationRegistry::class, $operations );

		$this->assertTrue( $targets->has( 'post' ) );
		$this->assertTrue( $targets->has( 'page' ) );
		$this->assertTrue( $operations->has( 'delete' ) );
		$this->assertTrue( $operations->has( 'duplicate' ) );
		$this->assertTrue( $operations->has( 'edit' ) );
	}

	public function test_execution_service_is_registered(): void {
		$this->assertInstanceOf( \ContentOps\Execution\ExecutionService::class, Plugin::instance()->get( 'execution.service' ) );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter PluginWiringTest`
Expected: FAIL — services not registered.

- [ ] **Step 3: Update `Plugin::on_plugins_loaded()`**

Replace the body of `on_plugins_loaded` in `src/Plugin.php`:

```php
	public function on_plugins_loaded(): void {
		\load_plugin_textdomain( 'content-ops', false, dirname( \plugin_basename( $this->plugin_file ) ) . '/languages' );
		$this->load_action_scheduler();

		global $wpdb;

		$action_scheduler_bridge = new \ContentOps\Async\ActionSchedulerBridge();
		$this->set( 'async.action_scheduler', $action_scheduler_bridge );

		$token_generator = new \ContentOps\PreviewToken\TokenGenerator( (string) wp_salt() );
		$token_store     = new \ContentOps\PreviewToken\TokenStore();
		$operations_repo = new \ContentOps\History\OperationRepository( $wpdb );
		$snapshots_repo  = new \ContentOps\History\SnapshotRepository( $wpdb );
		$this->set( 'preview.token_generator', $token_generator );
		$this->set( 'preview.token_store', $token_store );
		$this->set( 'history.operations', $operations_repo );
		$this->set( 'history.snapshots', $snapshots_repo );

		$target_registry    = new \ContentOps\Registry\TargetRegistry();
		$operation_registry = new \ContentOps\Registry\OperationRegistry();

		$post_types = \apply_filters( 'content_ops_post_types', [ 'post', 'page' ] );
		foreach ( (array) $post_types as $post_type ) {
			$target_registry->register( new \ContentOps\Targets\PostTarget( (string) $post_type ) );
		}

		$operation_registry->register( new \ContentOps\Operations\DeleteOperation( $token_generator, $token_store, $operations_repo ) );
		$operation_registry->register( new \ContentOps\Operations\DuplicateOperation( $token_generator, $token_store, $operations_repo ) );
		$operation_registry->register( new \ContentOps\Operations\BulkEditOperation( $token_generator, $token_store, $operations_repo, $snapshots_repo ) );

		$this->set( 'target.registry', $target_registry );
		$this->set( 'operation.registry', $operation_registry );

		$execution = new \ContentOps\Execution\ExecutionService(
			$target_registry,
			$operation_registry,
			$operations_repo,
			$snapshots_repo,
			$token_generator,
			$token_store
		);
		$this->set( 'execution.service', $execution );

		$runner = new \ContentOps\Execution\OperationRunner( $execution );
		$runner->register();
		$this->set( 'execution.runner', $runner );

		$rest_registrar = new \ContentOps\REST\RouteRegistrar( $action_scheduler_bridge, $execution, $target_registry, $operation_registry, $operations_repo );
		$rest_registrar->register();
		$this->set( 'rest.registrar', $rest_registrar );

		$cli_registrar = new \ContentOps\CLI\CommandRegistrar( $action_scheduler_bridge, $execution, $target_registry, $operation_registry, $operations_repo );
		$cli_registrar->register();
		$this->set( 'cli.registrar', $cli_registrar );

		$abilities_bridge = new \ContentOps\Abilities\AbilitiesBridge( $action_scheduler_bridge, $execution, $target_registry, $operation_registry );
		$abilities_bridge->register();
		$this->set( 'abilities.bridge', $abilities_bridge );

		\do_action( 'content_ops_booted', $this );
	}
```

- [ ] **Step 4: Update `RouteRegistrar` signature (keep `/doctor` working)**

Replace `src/REST/RouteRegistrar.php`:

```php
<?php
namespace ContentOps\REST;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;

final class RouteRegistrar {

	public const REST_NAMESPACE = 'content-ops/v1';

	private ActionSchedulerBridge $action_scheduler;
	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;
	private OperationRepository $operations_repo;

	public function __construct(
		ActionSchedulerBridge $action_scheduler,
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations,
		OperationRepository $operations_repo
	) {
		$this->action_scheduler = $action_scheduler;
		$this->execution        = $execution;
		$this->targets          = $targets;
		$this->operations       = $operations;
		$this->operations_repo  = $operations_repo;
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$doctor = new DoctorController( $this->action_scheduler );

		register_rest_route(
			self::REST_NAMESPACE,
			'/doctor',
			[
				'methods'             => 'GET',
				'callback'            => [ $doctor, 'handle' ],
				'permission_callback' => [ $doctor, 'check_permission' ],
			]
		);
	}
}
```

- [ ] **Step 5: Update `CommandRegistrar` signature**

Replace `src/CLI/CommandRegistrar.php`:

```php
<?php
namespace ContentOps\CLI;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;

final class CommandRegistrar {

	private ActionSchedulerBridge $action_scheduler;
	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;
	private OperationRepository $operations_repo;

	public function __construct(
		ActionSchedulerBridge $action_scheduler,
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations,
		OperationRepository $operations_repo
	) {
		$this->action_scheduler = $action_scheduler;
		$this->execution        = $execution;
		$this->targets          = $targets;
		$this->operations       = $operations;
		$this->operations_repo  = $operations_repo;
	}

	public function register(): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		\WP_CLI::add_command(
			'content-ops doctor',
			new DoctorCommand( $this->action_scheduler ),
			[ 'shortdesc' => __( 'Check Content Ops environment health.', 'content-ops' ) ]
		);
	}
}
```

- [ ] **Step 6: Update `AbilitiesBridge` signature**

Replace `src/Abilities/AbilitiesBridge.php`:

```php
<?php
namespace ContentOps\Abilities;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\CLI\DoctorCommand;
use ContentOps\Execution\ExecutionService;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;

final class AbilitiesBridge {

	private ActionSchedulerBridge $action_scheduler;
	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;

	public function __construct(
		ActionSchedulerBridge $action_scheduler,
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations
	) {
		$this->action_scheduler = $action_scheduler;
		$this->execution        = $execution;
		$this->targets          = $targets;
		$this->operations       = $operations;
	}

	public function is_available(): bool {
		return function_exists( 'wp_register_ability' );
	}

	public function register(): void {
		if ( ! $this->is_available() ) {
			return;
		}
		add_action( 'abilities_api_init', [ $this, 'register_abilities' ] );
	}

	public function register_abilities(): void {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'content-ops',
				[
					'label'       => __( 'Content Ops', 'content-ops' ),
					'description' => __( 'Bulk operations for WordPress and WooCommerce content.', 'content-ops' ),
				]
			);
		}

		$doctor = new DoctorCommand( $this->action_scheduler );

		wp_register_ability(
			'content-ops/doctor',
			[
				'label'               => __( 'Content Ops: doctor', 'content-ops' ),
				'description'         => __( 'Report Content Ops environment health.', 'content-ops' ),
				'category'            => 'content-ops',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'schema_version'   => [ 'type' => 'string' ],
						'action_scheduler' => [ 'type' => 'object' ],
						'abilities_api'    => [ 'type' => 'object' ],
						'hpos'             => [ 'type' => 'object' ],
						'tables'           => [ 'type' => 'object' ],
						'cron'             => [ 'type' => 'object' ],
					],
				],
				'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				'execute_callback'    => static fn () => $doctor->collect_report(),
			]
		);
	}
}
```

Constructor signatures of existing Phase-0 tests must keep working. Update the two Phase-0 tests that instantiate these classes directly:

Edit `tests/integration/Abilities/AbilitiesBridgeTest.php` — replace both `new AbilitiesBridge( new ActionSchedulerBridge() )` calls with the full construction. Use a minimal setup helper:

```php
	private function bridge(): AbilitiesBridge {
		global $wpdb;
		$targets = new \ContentOps\Registry\TargetRegistry();
		$ops     = new \ContentOps\Registry\OperationRegistry();
		$exec    = new \ContentOps\Execution\ExecutionService(
			$targets,
			$ops,
			new \ContentOps\History\OperationRepository( $wpdb ),
			new \ContentOps\History\SnapshotRepository( $wpdb ),
			new \ContentOps\PreviewToken\TokenGenerator( 'salt' ),
			new \ContentOps\PreviewToken\TokenStore( 300 )
		);
		return new AbilitiesBridge( new ActionSchedulerBridge(), $exec, $targets, $ops );
	}
```

- [ ] **Step 7: Verify pass**

Run: `composer test:integration`
Expected: PASS for PluginWiringTest and all Phase-0 tests still green.

- [ ] **Step 8: Commit**

```bash
git add src/Plugin.php src/REST/RouteRegistrar.php src/CLI/CommandRegistrar.php src/Abilities/AbilitiesBridge.php tests/integration/PluginWiringTest.php tests/integration/Abilities/AbilitiesBridgeTest.php
git commit -m "wire PostTarget, operations, registries, and ExecutionService into Plugin boot"
```

---

## Task 16: REST `GET /catalog`

**Files:**
- Create: `src/REST/CatalogController.php`
- Modify: `src/REST/RouteRegistrar.php`
- Create: `tests/integration/REST/CatalogRouteTest.php`

Returns `{ targets: [ { slug, label, filters: [...] } ], operations: [ { slug, label, params_schema } ], presets: [] }`. Presets slot filled in Task 24.

Permission: `manage_options` (admin-level metadata). A Pro upgrade will later introduce finer permissions; Phase 1a keeps this simple.

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class CatalogRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_catalog_returns_targets_and_operations(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/catalog' ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'targets', $data );
		$this->assertArrayHasKey( 'operations', $data );
		$this->assertArrayHasKey( 'presets', $data );

		$target_slugs = array_column( $data['targets'], 'slug' );
		$this->assertContains( 'post', $target_slugs );
		$this->assertContains( 'page', $target_slugs );

		$op_slugs = array_column( $data['operations'], 'slug' );
		$this->assertContains( 'delete', $op_slugs );
		$this->assertContains( 'duplicate', $op_slugs );
		$this->assertContains( 'edit', $op_slugs );

		$post_row = null;
		foreach ( $data['targets'] as $t ) {
			if ( 'post' === $t['slug'] ) {
				$post_row = $t;
				break;
			}
		}
		$this->assertNotNull( $post_row );
		$this->assertNotEmpty( $post_row['filters'] );
	}

	public function test_catalog_rejects_non_admin(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/catalog' ) );
		$this->assertSame( 403, $response->get_status() );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter CatalogRouteTest`
Expected: FAIL — route not registered.

- [ ] **Step 3: Implement controller**

Create `src/REST/CatalogController.php`:

```php
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
```

- [ ] **Step 4: Wire route**

Append to `RouteRegistrar::register_routes()` in `src/REST/RouteRegistrar.php`:

```php
		$catalog = new CatalogController( $this->targets, $this->operations );
		register_rest_route(
			self::REST_NAMESPACE,
			'/catalog',
			[
				'methods'             => 'GET',
				'callback'            => [ $catalog, 'handle' ],
				'permission_callback' => [ $catalog, 'check_permission' ],
			]
		);
```

- [ ] **Step 5: Verify pass**

Run: `composer test:integration -- --filter CatalogRouteTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/REST/CatalogController.php src/REST/RouteRegistrar.php tests/integration/REST/CatalogRouteTest.php
git commit -m "add REST GET /catalog endpoint"
```

---

## Task 17: REST `POST /preview`

**Files:**
- Create: `src/REST/PreviewController.php`
- Modify: `src/REST/RouteRegistrar.php`
- Create: `tests/integration/REST/PreviewRouteTest.php`

Body: `{ target, operation, filters, params }`. Delegates to `ExecutionService::preview`. Returns `{ count, sample_ids, preview_token, warnings }` on success. Permission differs per operation slug; map: `delete → content_ops_delete`, `duplicate → content_ops_duplicate`, `edit → content_ops_edit`. Unknown slug → 400 `co.operation.unknown`.

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class PreviewRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$role  = get_role( 'administrator' );
		foreach ( \ContentOps\Capabilities\Capabilities::ALL as $cap ) {
			$role->add_cap( $cap );
		}
		wp_set_current_user( $admin );
	}

	public function test_preview_returns_count_and_token(): void {
		self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$req = new WP_REST_Request( 'POST', '/content-ops/v1/preview' );
		$req->set_body_params(
			[
				'target'    => 'post',
				'operation' => 'delete',
				'filters'   => [ 'status' => [ 'draft' ] ],
				'params'    => [ 'permanent' => false ],
			]
		);
		$response = $this->server->dispatch( $req );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 3, $data['count'] );
		$this->assertCount( 3, $data['sample_ids'] );
		$this->assertNotSame( '', $data['preview_token'] );
	}

	public function test_preview_unknown_operation_returns_400(): void {
		$req = new WP_REST_Request( 'POST', '/content-ops/v1/preview' );
		$req->set_body_params(
			[
				'target'    => 'post',
				'operation' => 'bogus',
				'filters'   => [],
				'params'    => [],
			]
		);
		$response = $this->server->dispatch( $req );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'co.operation.unknown', $response->get_data()['code'] );
	}

	public function test_preview_rejects_user_without_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$req = new WP_REST_Request( 'POST', '/content-ops/v1/preview' );
		$req->set_body_params(
			[
				'target'    => 'post',
				'operation' => 'delete',
				'filters'   => [],
				'params'    => [],
			]
		);
		$response = $this->server->dispatch( $req );
		$this->assertSame( 403, $response->get_status() );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter PreviewRouteTest`
Expected: FAIL — route missing.

- [ ] **Step 3: Implement controller**

Create `src/REST/PreviewController.php`:

```php
<?php
namespace ContentOps\REST;

use ContentOps\Execution\ExecutionService;
use WP_REST_Request;
use WP_REST_Response;

final class PreviewController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'content_ops_delete',
		'duplicate' => 'content_ops_duplicate',
		'edit'      => 'content_ops_edit',
	];

	private ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	public function check_permission( WP_REST_Request $request ) {
		$op  = (string) $request->get_param( 'operation' );
		$cap = self::CAP_MAP[ $op ] ?? 'manage_options';
		return $this->require_capability( $cap );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$target    = (string) $request->get_param( 'target' );
		$operation = (string) $request->get_param( 'operation' );
		$filters   = (array) ( $request->get_param( 'filters' ) ?? [] );
		$params    = (array) ( $request->get_param( 'params' ) ?? [] );

		$result = $this->execution->preview( $target, $operation, $filters, $params );

		if ( ! $result->is_ok() ) {
			$code   = $result->get_error()->code();
			$status = 0 === strpos( $code, 'co.target.' ) || 0 === strpos( $code, 'co.operation.' ) ? 400 : 422;
			return $this->error_response( $result->get_error(), $status );
		}

		return new WP_REST_Response(
			[
				'count'         => $result->count(),
				'sample_ids'    => $result->sample_ids(),
				'preview_token' => $result->preview_token(),
				'warnings'      => $result->warnings(),
			]
		);
	}
}
```

- [ ] **Step 4: Wire route**

Append to `RouteRegistrar::register_routes()`:

```php
		$preview = new PreviewController( $this->execution );
		register_rest_route(
			self::REST_NAMESPACE,
			'/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $preview, 'handle' ],
				'permission_callback' => [ $preview, 'check_permission' ],
				'args'                => [
					'target'    => [ 'type' => 'string', 'required' => true ],
					'operation' => [ 'type' => 'string', 'required' => true ],
					'filters'   => [ 'type' => 'object', 'default' => new \stdClass() ],
					'params'    => [ 'type' => 'object', 'default' => new \stdClass() ],
				],
			]
		);
```

- [ ] **Step 5: Verify pass**

Run: `composer test:integration -- --filter PreviewRouteTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/REST/PreviewController.php src/REST/RouteRegistrar.php tests/integration/REST/PreviewRouteTest.php
git commit -m "add REST POST /preview endpoint"
```

---

## Task 18: REST `POST /execute`

**Files:**
- Create: `src/REST/ExecuteController.php`
- Modify: `src/REST/RouteRegistrar.php`
- Create: `tests/integration/REST/ExecuteRouteTest.php`

Body: `{ preview_token, target, operation, filters, params }`. Re-runs `preview` (matching the original `preview()` call), verifies the submitted token matches, consumes it, creates the history row, and either runs synchronously (below threshold) or schedules via Action Scheduler (above). Threshold: 100, filterable via `content_ops_async_threshold`.

Returns `{ operation_id, status: 'queued' | 'completed', batch: { processed, succeeded, failed } }`.

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class ExecuteRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$role  = get_role( 'administrator' );
		foreach ( \ContentOps\Capabilities\Capabilities::ALL as $cap ) {
			$role->add_cap( $cap );
		}
		wp_set_current_user( $admin );
	}

	private function preview( array $filters = [ 'status' => [ 'draft' ] ], array $params = [ 'permanent' => false ] ): array {
		$req = new WP_REST_Request( 'POST', '/content-ops/v1/preview' );
		$req->set_body_params(
			[ 'target' => 'post', 'operation' => 'delete', 'filters' => $filters, 'params' => $params ]
		);
		return $this->server->dispatch( $req )->get_data();
	}

	public function test_execute_runs_synchronously_below_threshold(): void {
		$ids = self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$preview = $this->preview();
		$token   = $preview['preview_token'];

		$req = new WP_REST_Request( 'POST', '/content-ops/v1/execute' );
		$req->set_body_params(
			[
				'preview_token' => $token,
				'target'        => 'post',
				'operation'     => 'delete',
				'filters'       => [ 'status' => [ 'draft' ] ],
				'params'        => [ 'permanent' => false ],
			]
		);
		$response = $this->server->dispatch( $req );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'completed', $data['status'] );
		$this->assertGreaterThan( 0, $data['operation_id'] );
		$this->assertSame( 3, $data['batch']['succeeded'] );

		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}

	public function test_execute_rejects_invalid_token(): void {
		self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$req = new WP_REST_Request( 'POST', '/content-ops/v1/execute' );
		$req->set_body_params(
			[
				'preview_token' => 'not-a-real-token',
				'target'        => 'post',
				'operation'     => 'delete',
				'filters'       => [ 'status' => [ 'draft' ] ],
				'params'        => [ 'permanent' => false ],
			]
		);
		$response = $this->server->dispatch( $req );
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'co.preview.stale_token', $response->get_data()['code'] );
	}

	public function test_execute_queues_above_threshold(): void {
		add_filter( 'content_ops_async_threshold', static fn () => 2 );
		self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$preview = $this->preview();
		$req     = new WP_REST_Request( 'POST', '/content-ops/v1/execute' );
		$req->set_body_params(
			[
				'preview_token' => $preview['preview_token'],
				'target'        => 'post',
				'operation'     => 'delete',
				'filters'       => [ 'status' => [ 'draft' ] ],
				'params'        => [ 'permanent' => false ],
			]
		);
		$response = $this->server->dispatch( $req );

		$this->assertSame( 202, $response->get_status() );
		$this->assertSame( 'queued', $response->get_data()['status'] );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter ExecuteRouteTest`
Expected: FAIL — route missing.

- [ ] **Step 3: Implement controller**

Create `src/REST/ExecuteController.php`:

```php
<?php
namespace ContentOps\REST;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\Contracts\QueryArgs;
use ContentOps\Errors\ContentOpsError;
use ContentOps\Execution\ExecutionService;
use ContentOps\Execution\OperationRunner;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\PreviewToken\TokenVerifier;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class ExecuteController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'content_ops_delete',
		'duplicate' => 'content_ops_duplicate',
		'edit'      => 'content_ops_edit',
	];

	private ExecutionService $execution;
	private TargetRegistry $targets;
	private OperationRegistry $operations;
	private TokenVerifier $verifier;
	private TokenStore $token_store;
	private ActionSchedulerBridge $scheduler;

	public function __construct(
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations,
		TokenVerifier $verifier,
		TokenStore $token_store,
		ActionSchedulerBridge $scheduler
	) {
		$this->execution   = $execution;
		$this->targets     = $targets;
		$this->operations  = $operations;
		$this->verifier    = $verifier;
		$this->token_store = $token_store;
		$this->scheduler   = $scheduler;
	}

	public function check_permission( WP_REST_Request $request ) {
		$op  = (string) $request->get_param( 'operation' );
		$cap = self::CAP_MAP[ $op ] ?? 'manage_options';
		return $this->require_capability( $cap );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$token     = (string) $request->get_param( 'preview_token' );
		$target    = (string) $request->get_param( 'target' );
		$operation = (string) $request->get_param( 'operation' );
		$filters   = (array) ( $request->get_param( 'filters' ) ?? [] );
		$params    = (array) ( $request->get_param( 'params' ) ?? [] );

		$target_obj = $this->targets->get( $target );
		$op_obj     = $this->operations->get( $operation );
		if ( null === $target_obj || null === $op_obj ) {
			return $this->error_response(
				new ContentOpsError( null === $target_obj ? 'co.target.unknown' : 'co.operation.unknown', 'Unknown target or operation.' ),
				400
			);
		}

		$args       = QueryArgs::from_array( $filters );
		$sample_ids = $target_obj->query( $args, 20, 0 );
		$count      = $target_obj->count( $args );
		$payload    = [
			'target'     => $target,
			'operation'  => $operation,
			'filters'    => $filters,
			'params'     => $params,
			'sample_ids' => $sample_ids,
			'count'      => $count,
		];
		if ( ! $this->verifier->verify( $token, $payload ) ) {
			return $this->error_response(
				new ContentOpsError( 'co.preview.stale_token', 'Preview token invalid or expired. Re-preview before executing.' ),
				409
			);
		}
		$this->verifier->consume( $token );

		$user_id = (int) get_current_user_id();
		$op_id   = $this->execution->record( $target, $operation, $user_id, $filters, $params );

		$threshold = (int) apply_filters( 'content_ops_async_threshold', 100 );
		if ( $count > $threshold && $this->scheduler->is_available() ) {
			$this->scheduler->schedule_single_action(
				time(),
				OperationRunner::HOOK,
				[ $op_id ],
				'content-ops'
			);
			return new WP_REST_Response( [ 'operation_id' => $op_id, 'status' => 'queued' ], 202 );
		}

		$result = $this->execution->run_sync( $op_id );
		if ( ! $result->is_ok() ) {
			return $this->error_response( $result->get_error(), 500 );
		}

		return new WP_REST_Response(
			[
				'operation_id' => $op_id,
				'status'       => 'completed',
				'batch'        => [
					'processed' => $result->processed(),
					'succeeded' => $result->succeeded(),
					'failed'    => $result->failed(),
				],
			]
		);
	}
}
```

- [ ] **Step 4: Wire route**

Update `RouteRegistrar` constructor signature to also carry `TokenVerifier` and `TokenStore`. Add them to `Plugin::on_plugins_loaded` after `token_generator`/`token_store` are created:

```php
		$token_verifier = new \ContentOps\PreviewToken\TokenVerifier( $token_generator, $token_store );
		$this->set( 'preview.token_verifier', $token_verifier );
```

Edit `src/REST/RouteRegistrar.php`:

```php
	private \ContentOps\PreviewToken\TokenVerifier $verifier;
	private \ContentOps\PreviewToken\TokenStore $token_store;

	public function __construct(
		ActionSchedulerBridge $action_scheduler,
		ExecutionService $execution,
		TargetRegistry $targets,
		OperationRegistry $operations,
		OperationRepository $operations_repo,
		\ContentOps\PreviewToken\TokenVerifier $verifier,
		\ContentOps\PreviewToken\TokenStore $token_store
	) {
		$this->action_scheduler = $action_scheduler;
		$this->execution        = $execution;
		$this->targets          = $targets;
		$this->operations       = $operations;
		$this->operations_repo  = $operations_repo;
		$this->verifier         = $verifier;
		$this->token_store      = $token_store;
	}
```

Update `Plugin::on_plugins_loaded` to pass the extra args. Append inside `register_routes()`:

```php
		$execute = new ExecuteController( $this->execution, $this->targets, $this->operations, $this->verifier, $this->token_store, $this->action_scheduler );
		register_rest_route(
			self::REST_NAMESPACE,
			'/execute',
			[
				'methods'             => 'POST',
				'callback'            => [ $execute, 'handle' ],
				'permission_callback' => [ $execute, 'check_permission' ],
				'args'                => [
					'preview_token' => [ 'type' => 'string', 'required' => true ],
					'target'        => [ 'type' => 'string', 'required' => true ],
					'operation'     => [ 'type' => 'string', 'required' => true ],
					'filters'       => [ 'type' => 'object', 'default' => new \stdClass() ],
					'params'        => [ 'type' => 'object', 'default' => new \stdClass() ],
				],
			]
		);
```

- [ ] **Step 5: Verify pass**

Run: `composer test:integration -- --filter ExecuteRouteTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/REST/ExecuteController.php src/REST/RouteRegistrar.php src/Plugin.php tests/integration/REST/ExecuteRouteTest.php
git commit -m "add REST POST /execute with preview-token verification and async threshold"
```

---

## Task 19: REST `GET /operations` and `GET /operations/{id}`

**Files:**
- Create: `src/REST/OperationsController.php`
- Modify: `src/REST/RouteRegistrar.php`
- Create: `tests/integration/REST/OperationsRouteTest.php`

Paginated list and single fetch. Permission: `manage_options`. Operation row serialized as the JSON shape of `Operation`.

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class OperationsRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_list_returns_recent_operations(): void {
		global $wpdb;
		$repo = new \ContentOps\History\OperationRepository( $wpdb );
		$a    = $repo->create( \ContentOps\History\Operation::newly_created( 'delete', 'post', 1, [], [] ) );
		$b    = $repo->create( \ContentOps\History\Operation::newly_created( 'duplicate', 'post', 1, [], [] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/operations' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $b->id(), $data[0]['id'] );
		$this->assertSame( $a->id(), $data[1]['id'] );
	}

	public function test_single_returns_operation(): void {
		global $wpdb;
		$repo  = new \ContentOps\History\OperationRepository( $wpdb );
		$saved = $repo->create( \ContentOps\History\Operation::newly_created( 'delete', 'post', 1, [ 'status' => [ 'draft' ] ], [ 'permanent' => false ] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/operations/' . $saved->id() ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $saved->id(), $data['id'] );
		$this->assertSame( 'delete', $data['type'] );
		$this->assertSame( [ 'status' => [ 'draft' ] ], $data['filters'] );
	}

	public function test_single_404_when_missing(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/operations/999999' ) );
		$this->assertSame( 404, $response->get_status() );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter OperationsRouteTest`
Expected: FAIL — routes missing.

- [ ] **Step 3: Implement controller**

Create `src/REST/OperationsController.php`:

```php
<?php
namespace ContentOps\REST;

use ContentOps\Errors\ContentOpsError;
use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;
use WP_REST_Request;
use WP_REST_Response;

final class OperationsController extends RestController {

	private OperationRepository $operations;

	public function __construct( OperationRepository $operations ) {
		$this->operations = $operations;
	}

	public function check_permission() {
		return $this->require_capability( 'manage_options' );
	}

	public function handle_list( WP_REST_Request $request ): WP_REST_Response {
		$limit  = max( 1, min( 100, (int) ( $request->get_param( 'limit' ) ?? 20 ) ) );
		$offset = max( 0, (int) ( $request->get_param( 'offset' ) ?? 0 ) );

		$rows = $this->operations->list( $limit, $offset );

		return new WP_REST_Response( array_map( [ $this, 'serialize' ], $rows ) );
	}

	public function handle_single( WP_REST_Request $request ): WP_REST_Response {
		$op = $this->operations->find( (int) $request['id'] );
		if ( null === $op ) {
			return $this->error_response(
				new ContentOpsError( 'co.operation.not_found', 'Operation not found.', [ 'id' => (int) $request['id'] ] ),
				404
			);
		}
		return new WP_REST_Response( $this->serialize( $op ) );
	}

	/** @return array<string, mixed> */
	private function serialize( Operation $op ): array {
		return [
			'id'             => $op->id(),
			'type'           => $op->type(),
			'target'         => $op->target(),
			'user_id'        => $op->user_id(),
			'filters'        => $op->filters(),
			'params'         => $op->params(),
			'affected_count' => $op->affected_count(),
			'affected_ids'   => $op->affected_ids(),
			'status'         => $op->status(),
			'error_message'  => $op->error_message(),
			'created_at'     => $op->created_at(),
			'completed_at'   => $op->completed_at(),
		];
	}
}
```

- [ ] **Step 4: Wire routes**

Append to `RouteRegistrar::register_routes()`:

```php
		$operations_ctrl = new OperationsController( $this->operations_repo );
		register_rest_route(
			self::REST_NAMESPACE,
			'/operations',
			[
				'methods'             => 'GET',
				'callback'            => [ $operations_ctrl, 'handle_list' ],
				'permission_callback' => [ $operations_ctrl, 'check_permission' ],
				'args'                => [
					'limit'  => [ 'type' => 'integer', 'default' => 20 ],
					'offset' => [ 'type' => 'integer', 'default' => 0 ],
				],
			]
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/operations/(?P<id>\d+)',
			[
				'methods'             => 'GET',
				'callback'            => [ $operations_ctrl, 'handle_single' ],
				'permission_callback' => [ $operations_ctrl, 'check_permission' ],
			]
		);
```

- [ ] **Step 5: Verify pass**

Run: `composer test:integration -- --filter OperationsRouteTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/REST/OperationsController.php src/REST/RouteRegistrar.php tests/integration/REST/OperationsRouteTest.php
git commit -m "add REST operations list and detail endpoints"
```

---

## Task 20: REST `POST /operations/{id}/undo`

**Files:**
- Create: `src/REST/UndoController.php`
- Modify: `src/REST/RouteRegistrar.php`
- Create: `tests/integration/REST/UndoRouteTest.php`

Loads the operation row, resolves its operation slug in the registry, calls `undo($operation_id)`, returns `UndoResult`.

Permission maps operation.type → the matching cap (same table as preview/execute).

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\REST;

use ContentOps\Tests\Integration\TestCase;
use WP_REST_Request;
use WP_REST_Server;

final class UndoRouteTest extends TestCase {

	protected WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$role  = get_role( 'administrator' );
		foreach ( \ContentOps\Capabilities\Capabilities::ALL as $cap ) {
			$role->add_cap( $cap );
		}
		wp_set_current_user( $admin );
	}

	public function test_undo_restores_trashed_posts(): void {
		global $wpdb;
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$repo  = new \ContentOps\History\OperationRepository( $wpdb );
		$saved = $repo->create( \ContentOps\History\Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => false ] ) );
		$repo->mark_completed( $saved->id(), $ids );

		$response = $this->server->dispatch( new WP_REST_Request( 'POST', '/content-ops/v1/operations/' . $saved->id() . '/undo' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_data()['restored'] );
		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}

	public function test_undo_missing_returns_404(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'POST', '/content-ops/v1/operations/999999/undo' ) );
		$this->assertSame( 404, $response->get_status() );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter UndoRouteTest`
Expected: FAIL — route missing.

- [ ] **Step 3: Implement controller**

Create `src/REST/UndoController.php`:

```php
<?php
namespace ContentOps\REST;

use ContentOps\Errors\ContentOpsError;
use ContentOps\History\OperationRepository;
use ContentOps\Registry\OperationRegistry;
use WP_REST_Request;
use WP_REST_Response;

final class UndoController extends RestController {

	private const CAP_MAP = [
		'delete'    => 'content_ops_delete',
		'duplicate' => 'content_ops_duplicate',
		'edit'      => 'content_ops_edit',
	];

	private OperationRegistry $operations;
	private OperationRepository $repo;

	public function __construct( OperationRegistry $operations, OperationRepository $repo ) {
		$this->operations = $operations;
		$this->repo       = $repo;
	}

	public function check_permission( WP_REST_Request $request ) {
		$op = $this->repo->find( (int) $request['id'] );
		if ( null === $op ) {
			return $this->require_capability( 'manage_options' );
		}
		$cap = self::CAP_MAP[ $op->type() ] ?? 'manage_options';
		return $this->require_capability( $cap );
	}

	public function handle( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request['id'];
		$op = $this->repo->find( $id );
		if ( null === $op ) {
			return $this->error_response(
				new ContentOpsError( 'co.operation.not_found', 'Operation not found.', [ 'id' => $id ] ),
				404
			);
		}

		$runner = $this->operations->get( $op->type() );
		if ( null === $runner ) {
			return $this->error_response(
				new ContentOpsError( 'co.operation.unknown', 'Operation type no longer registered.', [ 'type' => $op->type() ] ),
				400
			);
		}

		$result = $runner->undo( $id );
		if ( ! $result->is_ok() ) {
			return $this->error_response( $result->get_error(), 422 );
		}
		return new WP_REST_Response( [ 'restored' => $result->restored() ] );
	}
}
```

- [ ] **Step 4: Wire route**

Append to `RouteRegistrar::register_routes()`:

```php
		$undo = new UndoController( $this->operations, $this->operations_repo );
		register_rest_route(
			self::REST_NAMESPACE,
			'/operations/(?P<id>\d+)/undo',
			[
				'methods'             => 'POST',
				'callback'            => [ $undo, 'handle' ],
				'permission_callback' => [ $undo, 'check_permission' ],
			]
		);
```

- [ ] **Step 5: Verify pass**

Run: `composer test:integration -- --filter UndoRouteTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/REST/UndoController.php src/REST/RouteRegistrar.php tests/integration/REST/UndoRouteTest.php
git commit -m "add REST undo endpoint"
```

---

## Task 21: WP-CLI `wp content-ops delete`

**Files:**
- Create: `src/CLI/DeleteCommand.php`
- Modify: `src/CLI/CommandRegistrar.php`
- Create: `tests/integration/CLI/DeleteCommandTest.php`

Signature:

```
wp content-ops delete [--post-type=<slug>] [--status=<status>] [--older-than=<duration>] [--permanent] [--dry-run] [--yes] [--format=table|json|count|ids]
```

`--older-than=90d` maps to `modified_before = now - 90 days`. Defaults: `--post-type=post`, no status filter, non-permanent. `--dry-run` prints preview only. `--yes` skips interactive confirmation (CLI is non-interactive by default in tests, so absent `--yes` the command still runs — no stdin prompting).

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\Async\ActionSchedulerBridge;
use ContentOps\CLI\DeleteCommand;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\DeleteOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DeleteCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	private function command(): DeleteCommand {
		global $wpdb;
		$targets = new TargetRegistry();
		$ops     = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$ops->register( new DeleteOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), new OperationRepository( $wpdb ) ) );

		$exec = new ExecutionService(
			$targets,
			$ops,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);

		return new DeleteCommand( $exec );
	}

	public function test_dry_run_returns_preview_and_does_not_delete(): void {
		$ids = self::factory()->post->create_many( 3, [ 'post_status' => 'draft' ] );

		$result = $this->command()->run(
			[
				'post-type' => 'post',
				'status'    => 'draft',
				'dry-run'   => true,
				'format'    => 'json',
			]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'preview', $data['status'] );
		$this->assertSame( 3, $data['count'] );

		foreach ( $ids as $id ) {
			$this->assertSame( 'draft', get_post_status( $id ) );
		}
	}

	public function test_execution_trashes_posts(): void {
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'draft' ] );

		$result = $this->command()->run(
			[ 'post-type' => 'post', 'status' => 'draft', 'format' => 'json' ]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'completed', $data['status'] );
		$this->assertSame( 2, $data['succeeded'] );

		foreach ( $ids as $id ) {
			$this->assertSame( 'trash', get_post_status( $id ) );
		}
	}

	public function test_older_than_parses_duration(): void {
		$old = (int) self::factory()->post->create(
			[
				'post_status'       => 'draft',
				'post_modified'     => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
				'post_modified_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) ),
			]
		);
		self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->command()->run(
			[ 'post-type' => 'post', 'status' => 'draft', 'older-than' => '90d', 'format' => 'json' ]
		);

		$this->assertSame( 'trash', get_post_status( $old ) );
	}

	public function test_count_format_emits_just_integer(): void {
		self::factory()->post->create_many( 4, [ 'post_status' => 'draft' ] );

		$result = $this->command()->run(
			[ 'post-type' => 'post', 'status' => 'draft', 'dry-run' => true, 'format' => 'count' ]
		);

		$this->assertSame( '4', trim( $result['output'] ) );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter DeleteCommandTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

Create `src/CLI/DeleteCommand.php`:

```php
<?php
namespace ContentOps\CLI;

use ContentOps\Execution\ExecutionService;

final class DeleteCommand {

	private ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	/**
	 * Trash or permanently delete posts in bulk.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<slug>]
	 * : Post type slug. Default: post.
	 *
	 * [--status=<status>]
	 * : Post status or comma-separated list.
	 *
	 * [--older-than=<duration>]
	 * : Modified-before cutoff, e.g. 90d.
	 *
	 * [--permanent]
	 * : Hard-delete instead of trashing.
	 *
	 * [--dry-run]
	 * : Preview only.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, count, ids. Default: table.
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
		if ( 0 !== $result['exit_code'] ) {
			\WP_CLI::halt( $result['exit_code'] );
		}
	}

	/**
	 * @param array<string, mixed> $assoc_args
	 * @return array{exit_code: int, output: string}
	 */
	public function run( array $assoc_args ): array {
		$post_type = (string) ( $assoc_args['post-type'] ?? 'post' );
		$format    = (string) ( $assoc_args['format'] ?? 'table' );
		$dry_run   = ! empty( $assoc_args['dry-run'] );
		$permanent = ! empty( $assoc_args['permanent'] );

		$filters = [];
		if ( isset( $assoc_args['status'] ) ) {
			$filters['status'] = array_map( 'trim', explode( ',', (string) $assoc_args['status'] ) );
		}
		if ( isset( $assoc_args['older-than'] ) ) {
			$filters['modified_before'] = $this->duration_to_date( (string) $assoc_args['older-than'] );
		}

		$params = [ 'permanent' => $permanent ];

		$preview = $this->execution->preview( $post_type, 'delete', $filters, $params );
		if ( ! $preview->is_ok() ) {
			return [ 'exit_code' => 1, 'output' => (string) wp_json_encode( $preview->get_error()->to_array() ) ];
		}

		if ( $dry_run ) {
			return [ 'exit_code' => 0, 'output' => $this->format_preview( $preview->count(), $preview->sample_ids(), $format, 'preview' ) ];
		}

		$op_id = $this->execution->record( $post_type, 'delete', (int) get_current_user_id(), $filters, $params );
		$batch = $this->execution->run_sync( $op_id );
		if ( ! $batch->is_ok() ) {
			return [ 'exit_code' => 1, 'output' => (string) wp_json_encode( $batch->get_error()->to_array() ) ];
		}

		return [
			'exit_code' => 0,
			'output'    => $this->format_batch( $op_id, $batch->processed(), $batch->succeeded(), $batch->failed(), $format ),
		];
	}

	private function duration_to_date( string $duration ): string {
		if ( preg_match( '/^(\d+)d$/', $duration, $m ) ) {
			return gmdate( 'Y-m-d H:i:s', time() - ( (int) $m[1] ) * DAY_IN_SECONDS );
		}
		return $duration;
	}

	/** @param int[] $sample_ids */
	private function format_preview( int $count, array $sample_ids, string $format, string $status ): string {
		if ( 'count' === $format ) {
			return (string) $count;
		}
		if ( 'ids' === $format ) {
			return implode( "\n", array_map( 'strval', $sample_ids ) );
		}
		$payload = [ 'status' => $status, 'count' => $count, 'sample_ids' => $sample_ids ];
		return (string) wp_json_encode( $payload );
	}

	private function format_batch( int $op_id, int $processed, int $succeeded, int $failed, string $format ): string {
		if ( 'count' === $format ) {
			return (string) $succeeded;
		}
		$payload = [
			'status'       => 'completed',
			'operation_id' => $op_id,
			'processed'    => $processed,
			'succeeded'    => $succeeded,
			'failed'       => $failed,
		];
		return (string) wp_json_encode( $payload );
	}
}
```

- [ ] **Step 4: Wire in `CommandRegistrar`**

Add inside `CommandRegistrar::register()`:

```php
		\WP_CLI::add_command(
			'content-ops delete',
			new DeleteCommand( $this->execution ),
			[ 'shortdesc' => __( 'Trash or permanently delete posts in bulk.', 'content-ops' ) ]
		);
```

- [ ] **Step 5: Verify pass**

Run: `composer test:integration -- --filter DeleteCommandTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/CLI/DeleteCommand.php src/CLI/CommandRegistrar.php tests/integration/CLI/DeleteCommandTest.php
git commit -m "add wp content-ops delete command"
```

---

## Task 22: WP-CLI `wp content-ops duplicate`

**Files:**
- Create: `src/CLI/DuplicateCommand.php`
- Modify: `src/CLI/CommandRegistrar.php`
- Create: `tests/integration/CLI/DuplicateCommandTest.php`

Signature:

```
wp content-ops duplicate [--post-type=<slug>] [--status=<status>] [--target-status=<status>] [--title-suffix=<string>] [--reassign-author=<id>] [--dry-run] [--yes] [--format=table|json|count|ids]
```

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\CLI\DuplicateCommand;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\DuplicateOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class DuplicateCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	private function command(): DuplicateCommand {
		global $wpdb;
		$targets = new TargetRegistry();
		$ops     = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$ops->register( new DuplicateOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), new OperationRepository( $wpdb ) ) );

		$exec = new ExecutionService(
			$targets,
			$ops,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);
		return new DuplicateCommand( $exec );
	}

	public function test_duplicate_creates_new_posts_as_draft(): void {
		$src = (int) self::factory()->post->create( [ 'post_title' => 'Hello', 'post_status' => 'publish' ] );

		$result = $this->command()->run(
			[ 'post-type' => 'post', 'status' => 'publish', 'format' => 'json', 'title-suffix' => ' (Copy)' ]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 1, $data['succeeded'] );

		$drafts = get_posts( [ 'post_status' => 'draft', 'numberposts' => -1 ] );
		$this->assertCount( 1, $drafts );
		$this->assertSame( 'Hello (Copy)', $drafts[0]->post_title );
		$this->assertNotNull( get_post( $src ) );
	}

	public function test_dry_run_does_not_create_posts(): void {
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$result = $this->command()->run(
			[ 'post-type' => 'post', 'status' => 'publish', 'dry-run' => true, 'format' => 'json' ]
		);

		$data = json_decode( $result['output'], true );
		$this->assertSame( 'preview', $data['status'] );
		$this->assertSame( 1, $data['count'] );

		$drafts = get_posts( [ 'post_status' => 'draft', 'numberposts' => -1 ] );
		$this->assertCount( 0, $drafts );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter DuplicateCommandTest`
Expected: FAIL.

- [ ] **Step 3: Implement**

Create `src/CLI/DuplicateCommand.php`:

```php
<?php
namespace ContentOps\CLI;

use ContentOps\Execution\ExecutionService;

final class DuplicateCommand {

	private ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	/**
	 * Duplicate posts in bulk.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<slug>]
	 * : Post type slug. Default: post.
	 *
	 * [--status=<status>]
	 * : Source post status or list (comma separated).
	 *
	 * [--target-status=<status>]
	 * : Status for the copy. Default: draft.
	 *
	 * [--title-suffix=<string>]
	 * : String appended to the copied title. Default: " (Copy)".
	 *
	 * [--reassign-author=<id>]
	 * : User ID to assign as the author of copies.
	 *
	 * [--dry-run]
	 * : Preview only.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * [--format=<format>]
	 * : Output format. table, json, count, ids. Default: table.
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
		if ( 0 !== $result['exit_code'] ) {
			\WP_CLI::halt( $result['exit_code'] );
		}
	}

	/**
	 * @param array<string, mixed> $assoc_args
	 * @return array{exit_code: int, output: string}
	 */
	public function run( array $assoc_args ): array {
		$post_type = (string) ( $assoc_args['post-type'] ?? 'post' );
		$format    = (string) ( $assoc_args['format'] ?? 'table' );
		$dry_run   = ! empty( $assoc_args['dry-run'] );

		$filters = [];
		if ( isset( $assoc_args['status'] ) ) {
			$filters['status'] = array_map( 'trim', explode( ',', (string) $assoc_args['status'] ) );
		}

		$params = [];
		if ( isset( $assoc_args['target-status'] ) ) {
			$params['target_status'] = (string) $assoc_args['target-status'];
		}
		if ( isset( $assoc_args['title-suffix'] ) ) {
			$params['title_suffix'] = (string) $assoc_args['title-suffix'];
		}
		if ( isset( $assoc_args['reassign-author'] ) ) {
			$params['reassign_author'] = (int) $assoc_args['reassign-author'];
		}

		$preview = $this->execution->preview( $post_type, 'duplicate', $filters, $params );
		if ( ! $preview->is_ok() ) {
			return [ 'exit_code' => 1, 'output' => (string) wp_json_encode( $preview->get_error()->to_array() ) ];
		}

		if ( $dry_run ) {
			if ( 'count' === $format ) {
				return [ 'exit_code' => 0, 'output' => (string) $preview->count() ];
			}
			return [
				'exit_code' => 0,
				'output'    => (string) wp_json_encode(
					[ 'status' => 'preview', 'count' => $preview->count(), 'sample_ids' => $preview->sample_ids() ]
				),
			];
		}

		$op_id = $this->execution->record( $post_type, 'duplicate', (int) get_current_user_id(), $filters, $params );
		$batch = $this->execution->run_sync( $op_id );
		if ( ! $batch->is_ok() ) {
			return [ 'exit_code' => 1, 'output' => (string) wp_json_encode( $batch->get_error()->to_array() ) ];
		}

		return [
			'exit_code' => 0,
			'output'    => (string) wp_json_encode(
				[
					'status'       => 'completed',
					'operation_id' => $op_id,
					'processed'    => $batch->processed(),
					'succeeded'    => $batch->succeeded(),
					'failed'       => $batch->failed(),
				]
			),
		];
	}
}
```

- [ ] **Step 4: Wire in `CommandRegistrar`**

Add inside `CommandRegistrar::register()`:

```php
		\WP_CLI::add_command(
			'content-ops duplicate',
			new DuplicateCommand( $this->execution ),
			[ 'shortdesc' => __( 'Duplicate posts in bulk.', 'content-ops' ) ]
		);
```

- [ ] **Step 5: Verify pass**

Run: `composer test:integration -- --filter DuplicateCommandTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/CLI/DuplicateCommand.php src/CLI/CommandRegistrar.php tests/integration/CLI/DuplicateCommandTest.php
git commit -m "add wp content-ops duplicate command"
```

---

## Task 23: WP-CLI `wp content-ops edit`

**Files:**
- Create: `src/CLI/EditCommand.php`
- Modify: `src/CLI/CommandRegistrar.php`
- Create: `tests/integration/CLI/EditCommandTest.php`

Signature:

```
wp content-ops edit [--post-type=<slug>] [--status=<source-status>] [--set-status=<new-status>] [--reassign-author=<id>] [--shift-dates=<days>] [--comment-status=open|closed] [--menu-order=<int>] [--dry-run] [--yes] [--format=table|json|count|ids]
```

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\CLI\EditCommand;
use ContentOps\Execution\ExecutionService;
use ContentOps\History\OperationRepository;
use ContentOps\History\SnapshotRepository;
use ContentOps\Operations\BulkEditOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Registry\TargetRegistry;
use ContentOps\Targets\PostTarget;
use ContentOps\Tests\Integration\TestCase;

final class EditCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	private function command(): EditCommand {
		global $wpdb;
		$targets = new TargetRegistry();
		$ops     = new OperationRegistry();
		$targets->register( new PostTarget( 'post' ) );
		$ops->register( new BulkEditOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), new OperationRepository( $wpdb ), new SnapshotRepository( $wpdb ) ) );

		$exec = new ExecutionService(
			$targets,
			$ops,
			new OperationRepository( $wpdb ),
			new SnapshotRepository( $wpdb ),
			new TokenGenerator( 'salt' ),
			new TokenStore( 300 )
		);
		return new EditCommand( $exec );
	}

	public function test_set_status_updates_posts(): void {
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );

		$result = $this->command()->run(
			[ 'post-type' => 'post', 'status' => 'publish', 'set-status' => 'draft', 'format' => 'json' ]
		);

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 2, $data['succeeded'] );

		foreach ( $ids as $id ) {
			$this->assertSame( 'draft', get_post_status( $id ) );
		}
	}

	public function test_validation_error_returns_nonzero_exit(): void {
		$result = $this->command()->run(
			[ 'post-type' => 'post', 'set-status' => 'banana', 'format' => 'json' ]
		);

		$this->assertNotSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'co.params.invalid_status', $data['code'] );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter EditCommandTest`
Expected: FAIL.

- [ ] **Step 3: Implement**

Create `src/CLI/EditCommand.php`:

```php
<?php
namespace ContentOps\CLI;

use ContentOps\Execution\ExecutionService;

final class EditCommand {

	private ExecutionService $execution;

	public function __construct( ExecutionService $execution ) {
		$this->execution = $execution;
	}

	/**
	 * Bulk-edit posts.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<slug>]
	 * : Post type slug. Default: post.
	 *
	 * [--status=<status>]
	 * : Source post status filter (comma separated).
	 *
	 * [--set-status=<status>]
	 * : New post status.
	 *
	 * [--reassign-author=<id>]
	 * : New author user ID.
	 *
	 * [--shift-dates=<days>]
	 * : Shift post_date by N days.
	 *
	 * [--comment-status=<status>]
	 * : open or closed.
	 *
	 * [--menu-order=<int>]
	 * : Set menu_order.
	 *
	 * [--dry-run]
	 * : Preview only.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * [--format=<format>]
	 * : table, json, count, ids. Default: table.
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
		if ( 0 !== $result['exit_code'] ) {
			\WP_CLI::halt( $result['exit_code'] );
		}
	}

	/**
	 * @param array<string, mixed> $assoc_args
	 * @return array{exit_code: int, output: string}
	 */
	public function run( array $assoc_args ): array {
		$post_type = (string) ( $assoc_args['post-type'] ?? 'post' );
		$format    = (string) ( $assoc_args['format'] ?? 'table' );
		$dry_run   = ! empty( $assoc_args['dry-run'] );

		$filters = [];
		if ( isset( $assoc_args['status'] ) ) {
			$filters['status'] = array_map( 'trim', explode( ',', (string) $assoc_args['status'] ) );
		}

		$params = [];
		if ( isset( $assoc_args['set-status'] ) ) {
			$params['set_status'] = (string) $assoc_args['set-status'];
		}
		if ( isset( $assoc_args['reassign-author'] ) ) {
			$params['reassign_author'] = (int) $assoc_args['reassign-author'];
		}
		if ( isset( $assoc_args['shift-dates'] ) ) {
			$params['shift_dates_days'] = (int) $assoc_args['shift-dates'];
		}
		if ( isset( $assoc_args['comment-status'] ) ) {
			$params['comment_status'] = (string) $assoc_args['comment-status'];
		}
		if ( isset( $assoc_args['menu-order'] ) ) {
			$params['menu_order'] = (int) $assoc_args['menu-order'];
		}

		$preview = $this->execution->preview( $post_type, 'edit', $filters, $params );
		if ( ! $preview->is_ok() ) {
			return [ 'exit_code' => 2, 'output' => (string) wp_json_encode( $preview->get_error()->to_array() ) ];
		}

		if ( $dry_run ) {
			if ( 'count' === $format ) {
				return [ 'exit_code' => 0, 'output' => (string) $preview->count() ];
			}
			return [
				'exit_code' => 0,
				'output'    => (string) wp_json_encode(
					[ 'status' => 'preview', 'count' => $preview->count(), 'sample_ids' => $preview->sample_ids() ]
				),
			];
		}

		$op_id = $this->execution->record( $post_type, 'edit', (int) get_current_user_id(), $filters, $params );
		$batch = $this->execution->run_sync( $op_id );
		if ( ! $batch->is_ok() ) {
			return [ 'exit_code' => 1, 'output' => (string) wp_json_encode( $batch->get_error()->to_array() ) ];
		}

		return [
			'exit_code' => 0,
			'output'    => (string) wp_json_encode(
				[
					'status'       => 'completed',
					'operation_id' => $op_id,
					'processed'    => $batch->processed(),
					'succeeded'    => $batch->succeeded(),
					'failed'       => $batch->failed(),
				]
			),
		];
	}
}
```

- [ ] **Step 4: Wire in `CommandRegistrar`**

Add:

```php
		\WP_CLI::add_command(
			'content-ops edit',
			new EditCommand( $this->execution ),
			[ 'shortdesc' => __( 'Bulk-edit posts.', 'content-ops' ) ]
		);
```

- [ ] **Step 5: Verify pass**

Run: `composer test:integration -- --filter EditCommandTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/CLI/EditCommand.php src/CLI/CommandRegistrar.php tests/integration/CLI/EditCommandTest.php
git commit -m "add wp content-ops edit command"
```

---

## Task 24: WP-CLI `wp content-ops history` and `wp content-ops undo`

**Files:**
- Create: `src/CLI/HistoryCommand.php`
- Create: `src/CLI/UndoCommand.php`
- Modify: `src/CLI/CommandRegistrar.php`
- Create: `tests/integration/CLI/HistoryCommandTest.php`
- Create: `tests/integration/CLI/UndoCommandTest.php`

Signatures:

```
wp content-ops history [--limit=<N>] [--format=json|table]
wp content-ops undo <operation_id> [--yes] [--format=json|table]
```

- [ ] **Step 1: Failing history test**

```php
<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\CLI\HistoryCommand;
use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;
use ContentOps\Tests\Integration\TestCase;

final class HistoryCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	public function test_history_lists_recent_first(): void {
		global $wpdb;
		$repo = new OperationRepository( $wpdb );
		$a    = $repo->create( Operation::newly_created( 'delete', 'post', 1, [], [] ) );
		$b    = $repo->create( Operation::newly_created( 'duplicate', 'post', 1, [], [] ) );

		$command = new HistoryCommand( $repo );
		$result  = $command->run( [ 'limit' => 10, 'format' => 'json' ] );

		$this->assertSame( 0, $result['exit_code'] );
		$rows = json_decode( $result['output'], true );
		$this->assertSame( $b->id(), $rows[0]['id'] );
		$this->assertSame( $a->id(), $rows[1]['id'] );
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:integration -- --filter HistoryCommandTest`
Expected: FAIL.

- [ ] **Step 3: Implement history**

Create `src/CLI/HistoryCommand.php`:

```php
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
	 * : table or json. Default: table.
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
			return [ 'exit_code' => 0, 'output' => (string) wp_json_encode( $rows ) ];
		}

		$buffer = [];
		foreach ( $rows as $row ) {
			$buffer[] = sprintf( '%d | %s | %s | %s | %d', $row['id'], $row['type'], $row['target'], $row['status'], $row['affected_count'] );
		}
		return [ 'exit_code' => 0, 'output' => implode( "\n", $buffer ) ];
	}

	/** @return array<string, mixed> */
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
```

- [ ] **Step 4: Failing undo test**

```php
<?php
namespace ContentOps\Tests\Integration\CLI;

use ContentOps\CLI\UndoCommand;
use ContentOps\History\Operation;
use ContentOps\History\OperationRepository;
use ContentOps\Operations\DeleteOperation;
use ContentOps\PreviewToken\TokenGenerator;
use ContentOps\PreviewToken\TokenStore;
use ContentOps\Registry\OperationRegistry;
use ContentOps\Tests\Integration\TestCase;

final class UndoCommandTest extends TestCase {

	public function set_up(): void {
		parent::set_up();
		\ContentOps\Database\Schema::install();
	}

	public function test_undo_restores_trashed_posts(): void {
		global $wpdb;
		$ids = self::factory()->post->create_many( 2, [ 'post_status' => 'publish' ] );
		foreach ( $ids as $id ) {
			wp_trash_post( $id );
		}

		$repo  = new OperationRepository( $wpdb );
		$saved = $repo->create( Operation::newly_created( 'delete', 'post', 0, [], [ 'permanent' => false ] ) );
		$repo->mark_completed( $saved->id(), $ids );

		$ops = new OperationRegistry();
		$ops->register( new DeleteOperation( new TokenGenerator( 'salt' ), new TokenStore( 300 ), $repo ) );

		$cmd    = new UndoCommand( $ops, $repo );
		$result = $cmd->run( [ 'id' => $saved->id(), 'format' => 'json' ] );

		$this->assertSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 2, $data['restored'] );
		foreach ( $ids as $id ) {
			$this->assertSame( 'publish', get_post_status( $id ) );
		}
	}

	public function test_undo_missing_operation_returns_error(): void {
		global $wpdb;
		$ops = new OperationRegistry();
		$cmd = new UndoCommand( $ops, new OperationRepository( $wpdb ) );

		$result = $cmd->run( [ 'id' => 999999, 'format' => 'json' ] );

		$this->assertNotSame( 0, $result['exit_code'] );
		$data = json_decode( $result['output'], true );
		$this->assertSame( 'co.operation.not_found', $data['code'] );
	}
}
```

- [ ] **Step 5: Implement undo command**

Create `src/CLI/UndoCommand.php`:

```php
<?php
namespace ContentOps\CLI;

use ContentOps\Errors\ContentOpsError;
use ContentOps\History\OperationRepository;
use ContentOps\Registry\OperationRegistry;

final class UndoCommand {

	private OperationRegistry $operations;
	private OperationRepository $repo;

	public function __construct( OperationRegistry $operations, OperationRepository $repo ) {
		$this->operations = $operations;
		$this->repo       = $repo;
	}

	/**
	 * Undo an operation by id.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Operation id.
	 *
	 * [--format=<format>]
	 * : table or json. Default: table.
	 *
	 * [--yes]
	 * : Skip confirmation.
	 *
	 * @when after_wp_load
	 *
	 * @param string[]             $args
	 * @param array<string, mixed> $assoc_args
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$assoc_args['id'] = (int) ( $args[0] ?? 0 );
		$result           = $this->run( $assoc_args );
		if ( '' !== $result['output'] ) {
			\WP_CLI::line( $result['output'] );
		}
		if ( 0 !== $result['exit_code'] ) {
			\WP_CLI::halt( $result['exit_code'] );
		}
	}

	/**
	 * @param array<string, mixed> $assoc_args
	 * @return array{exit_code: int, output: string}
	 */
	public function run( array $assoc_args ): array {
		$id = (int) ( $assoc_args['id'] ?? 0 );

		$row = $this->repo->find( $id );
		if ( null === $row ) {
			$err = new ContentOpsError( 'co.operation.not_found', 'Operation not found.', [ 'id' => $id ] );
			return [ 'exit_code' => 1, 'output' => (string) wp_json_encode( $err->to_array() ) ];
		}

		$op = $this->operations->get( $row->type() );
		if ( null === $op ) {
			$err = new ContentOpsError( 'co.operation.unknown', 'Operation not registered.', [ 'type' => $row->type() ] );
			return [ 'exit_code' => 1, 'output' => (string) wp_json_encode( $err->to_array() ) ];
		}

		$result = $op->undo( $id );
		if ( ! $result->is_ok() ) {
			return [ 'exit_code' => 1, 'output' => (string) wp_json_encode( $result->get_error()->to_array() ) ];
		}

		return [ 'exit_code' => 0, 'output' => (string) wp_json_encode( [ 'restored' => $result->restored() ] ) ];
	}
}
```

- [ ] **Step 6: Wire in `CommandRegistrar`**

Add:

```php
		\WP_CLI::add_command(
			'content-ops history',
			new HistoryCommand( $this->operations_repo ),
			[ 'shortdesc' => __( 'List Content Ops operations.', 'content-ops' ) ]
		);

		\WP_CLI::add_command(
			'content-ops undo',
			new UndoCommand( $this->operations, $this->operations_repo ),
			[ 'shortdesc' => __( 'Undo a Content Ops operation.', 'content-ops' ) ]
		);
```

- [ ] **Step 7: Verify pass**

Run: `composer test:integration -- --filter "HistoryCommandTest|UndoCommandTest"`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/CLI/HistoryCommand.php src/CLI/UndoCommand.php src/CLI/CommandRegistrar.php tests/integration/CLI/HistoryCommandTest.php tests/integration/CLI/UndoCommandTest.php
git commit -m "add wp content-ops history and undo commands"
```

---

## Task 25: Abilities matrix registration

**Files:**
- Modify: `src/Abilities/AbilitiesBridge.php`
- Create: `tests/integration/Abilities/AbilitiesMatrixTest.php`

Extend `register_abilities()` to walk every registered Target × Operation combo where `target.supports_operation(op.slug())` is true. Register one ability per combo under slug `content-ops/{target_slug}_{op_slug}`. Each ability's `execute_callback` calls `ExecutionService::preview`. Input schema: the union of the target's filter keys and the operation's params schema. Output schema: the preview shape. Permissions: map by operation slug using the same `CAP_MAP` used in REST controllers.

Keep the `content-ops/doctor` ability for backwards compatibility.

- [ ] **Step 1: Failing test**

```php
<?php
namespace ContentOps\Tests\Integration\Abilities;

use ContentOps\Tests\Integration\TestCase;

final class AbilitiesMatrixTest extends TestCase {

	public function test_abilities_registered_for_target_operation_matrix(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'Abilities API not installed in test env.' );
		}

		do_action( 'abilities_api_init' );

		$this->assertNotNull( wp_get_ability( 'content-ops/post_delete' ) );
		$this->assertNotNull( wp_get_ability( 'content-ops/post_duplicate' ) );
		$this->assertNotNull( wp_get_ability( 'content-ops/post_edit' ) );
		$this->assertNotNull( wp_get_ability( 'content-ops/page_delete' ) );
		$this->assertNotNull( wp_get_ability( 'content-ops/doctor' ) );
	}
}
```

If the test env does not have the Abilities API loaded, this test skips. (The real smoke test runs in Task 26's env matrix with the Abilities plugin installed.)

- [ ] **Step 2: Confirm failure (or skip)**

Run: `composer test:integration -- --filter AbilitiesMatrixTest`
Expected: FAIL (assertions failing) or SKIP (if Abilities API absent).

- [ ] **Step 3: Implement matrix registration**

Replace `register_abilities()` in `src/Abilities/AbilitiesBridge.php`:

```php
	public function register_abilities(): void {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				'content-ops',
				[
					'label'       => __( 'Content Ops', 'content-ops' ),
					'description' => __( 'Bulk operations for WordPress and WooCommerce content.', 'content-ops' ),
				]
			);
		}

		$doctor = new \ContentOps\CLI\DoctorCommand( $this->action_scheduler );
		wp_register_ability(
			'content-ops/doctor',
			[
				'label'               => __( 'Content Ops: doctor', 'content-ops' ),
				'description'         => __( 'Report Content Ops environment health.', 'content-ops' ),
				'category'            => 'content-ops',
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => new \stdClass(),
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'schema_version'   => [ 'type' => 'string' ],
						'action_scheduler' => [ 'type' => 'object' ],
						'abilities_api'    => [ 'type' => 'object' ],
						'hpos'             => [ 'type' => 'object' ],
						'tables'           => [ 'type' => 'object' ],
						'cron'             => [ 'type' => 'object' ],
					],
				],
				'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				'execute_callback'    => static fn () => $doctor->collect_report(),
			]
		);

		$cap_map = [
			'delete'    => 'content_ops_delete',
			'duplicate' => 'content_ops_duplicate',
			'edit'      => 'content_ops_edit',
		];

		$execution = $this->execution;

		foreach ( $this->targets->all() as $target ) {
			foreach ( $this->operations->all() as $op ) {
				if ( ! $target->supports_operation( $op->slug() ) ) {
					continue;
				}

				$target_slug = $target->slug();
				$op_slug     = $op->slug();
				$name        = 'content-ops/' . $target_slug . '_' . $op_slug;
				$cap         = $cap_map[ $op_slug ] ?? 'manage_options';

				wp_register_ability(
					$name,
					[
						'label'               => sprintf( '%s: %s', $target->label(), $op->label() ),
						'description'         => sprintf( 'Preview a %1$s %2$s bulk operation.', $target_slug, $op_slug ),
						'category'            => 'content-ops',
						'input_schema'        => [
							'type'       => 'object',
							'properties' => [
								'filters' => [ 'type' => 'object' ],
								'params'  => $op->get_params_schema(),
							],
						],
						'output_schema'       => [
							'type'       => 'object',
							'properties' => [
								'count'         => [ 'type' => 'integer' ],
								'sample_ids'    => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
								'preview_token' => [ 'type' => 'string' ],
								'warnings'      => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
							],
						],
						'permission_callback' => static fn () => current_user_can( $cap ),
						'execute_callback'    => static function ( $input ) use ( $execution, $target_slug, $op_slug ) {
							$filters = isset( $input['filters'] ) && is_array( $input['filters'] ) ? $input['filters'] : [];
							$params  = isset( $input['params'] ) && is_array( $input['params'] ) ? $input['params'] : [];
							$result  = $execution->preview( $target_slug, $op_slug, $filters, $params );
							if ( ! $result->is_ok() ) {
								return $result->get_error()->to_wp_error();
							}
							return [
								'count'         => $result->count(),
								'sample_ids'    => $result->sample_ids(),
								'preview_token' => $result->preview_token(),
								'warnings'      => $result->warnings(),
							];
						},
					]
				);
			}
		}
	}
```

- [ ] **Step 4: Verify pass**

Run: `composer test:integration -- --filter AbilitiesMatrixTest`
Expected: PASS (or SKIP when Abilities not installed).

- [ ] **Step 5: Commit**

```bash
git add src/Abilities/AbilitiesBridge.php tests/integration/Abilities/AbilitiesMatrixTest.php
git commit -m "register Target x Operation abilities on the Abilities API"
```

---

## Task 26: Common Cleanups preset catalog

**Files:**
- Create: `src/Presets/PresetCatalog.php`
- Create: `tests/unit/Presets/PresetCatalogTest.php`
- Modify: `src/REST/CatalogController.php` (already calls `apply_filters( 'content_ops_presets', [] )`)
- Modify: `src/Plugin.php` (register catalog into the filter)

Two curated presets for Phase 1a:
1. `trash-old-drafts` — `target=post`, `operation=delete`, `filters={ status: ['draft'], modified_before: now - 90 days }`, `params={ permanent: false }`.
2. `trash-auto-drafts` — `target=post`, `operation=delete`, `filters={ status: ['auto-draft'] }`, `params={ permanent: false }`.

- [ ] **Step 1: Failing unit test**

```php
<?php
namespace ContentOps\Tests\Unit\Presets;

use ContentOps\Presets\PresetCatalog;
use ContentOps\Tests\Unit\TestCase;

final class PresetCatalogTest extends TestCase {

	public function test_all_returns_built_in_presets(): void {
		$catalog = new PresetCatalog();
		$slugs   = array_map( static fn ( $p ) => $p['slug'], $catalog->all() );

		$this->assertContains( 'trash-old-drafts', $slugs );
		$this->assertContains( 'trash-auto-drafts', $slugs );
	}

	public function test_preset_shape_has_required_keys(): void {
		foreach ( ( new PresetCatalog() )->all() as $preset ) {
			$this->assertArrayHasKey( 'slug', $preset );
			$this->assertArrayHasKey( 'label', $preset );
			$this->assertArrayHasKey( 'target', $preset );
			$this->assertArrayHasKey( 'operation', $preset );
			$this->assertArrayHasKey( 'filters', $preset );
			$this->assertArrayHasKey( 'params', $preset );
		}
	}
}
```

- [ ] **Step 2: Confirm failure**

Run: `composer test:unit -- --filter PresetCatalogTest`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

Create `src/Presets/PresetCatalog.php`:

```php
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
				'filters'     => [ 'status' => [ 'draft' ], 'modified_before' => $ninety_days_ago ],
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
```

- [ ] **Step 4: Wire into Plugin boot**

Append inside `Plugin::on_plugins_loaded()` just before the final `do_action`:

```php
		$preset_catalog = new \ContentOps\Presets\PresetCatalog();
		$this->set( 'preset.catalog', $preset_catalog );
		\add_filter(
			'content_ops_presets',
			static fn ( array $presets ) => array_merge( $presets, $preset_catalog->all() )
		);
```

- [ ] **Step 5: Add integration assertion to `CatalogRouteTest`**

Append to `tests/integration/REST/CatalogRouteTest.php`:

```php
	public function test_catalog_exposes_built_in_presets(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/content-ops/v1/catalog' ) );
		$data     = $response->get_data();
		$slugs    = array_map( static fn ( $p ) => $p['slug'], $data['presets'] );
		$this->assertContains( 'trash-old-drafts', $slugs );
		$this->assertContains( 'trash-auto-drafts', $slugs );
	}
```

- [ ] **Step 6: Verify pass**

Run: `composer test:unit -- --filter PresetCatalogTest && composer test:integration -- --filter CatalogRouteTest`
Expected: PASS for both.

- [ ] **Step 7: Commit**

```bash
git add src/Presets/PresetCatalog.php src/Plugin.php tests/unit/Presets/PresetCatalogTest.php tests/integration/REST/CatalogRouteTest.php
git commit -m "add Common Cleanups preset catalog and expose via /catalog"
```

---

## Task 27: End-to-end verification, CHANGELOG, tag v0.2.0-alpha

- [ ] **Step 1: Unit tests green**

```bash
composer test:unit
```
Expected: all pass.

- [ ] **Step 2: Integration tests green**

```bash
npm run env:start
npm run env:test
```
Expected: all pass.

- [ ] **Step 3: PHPCS clean**

```bash
composer lint
```
Expected: no errors.

- [ ] **Step 4: PHPStan clean**

```bash
composer stan
```
Expected: `[OK] No errors`.

- [ ] **Step 5: Delete smoke test**

```bash
wp-env run cli --env-cwd=wp-content/plugins/content-ops wp post generate --count=3 --post_status=draft
wp-env run cli --env-cwd=wp-content/plugins/content-ops wp content-ops delete --post-type=post --status=draft --dry-run --format=json
wp-env run cli --env-cwd=wp-content/plugins/content-ops wp content-ops delete --post-type=post --status=draft --format=json
```
Expected: dry-run JSON shows `status: "preview"` with correct count; execute JSON shows `status: "completed"` with matching `succeeded`.

- [ ] **Step 6: Undo smoke test**

```bash
wp-env run cli --env-cwd=wp-content/plugins/content-ops wp content-ops history --limit=1 --format=json
wp-env run cli --env-cwd=wp-content/plugins/content-ops wp content-ops undo <id-from-history> --format=json
```
Expected: `restored` > 0; posts return to `publish`/`draft` status.

- [ ] **Step 7: REST smoke test**

```bash
wp-env run cli wp eval '
wp_set_current_user(1);
$catalog = rest_do_request( new WP_REST_Request( "GET", "/content-ops/v1/catalog" ) );
echo wp_json_encode( $catalog->get_data() );
'
```
Expected: JSON catalog containing `targets`, `operations`, `presets`.

- [ ] **Step 8: Update CHANGELOG**

Replace the `[Unreleased]` section in `CHANGELOG.md`:

```markdown
## [Unreleased]

## [0.2.0-alpha] - 2026-04-22

### Added
- `PostTarget` — implements `TargetInterface` for any registered public post type; 12 built-in filters (post_type, status, author, dates, taxonomy, comments, featured image, parent, children).
- `DeleteOperation` — trash or hard-delete with per-operation undo (rejects undo on permanent deletes).
- `DuplicateOperation` — duplicates posts with meta + taxonomy + featured-image copy; undo removes the duplicates.
- `BulkEditOperation` — updates status, author, dates, password, comment_status, menu_order, taxonomy add/remove; undo restores from snapshot rows.
- `ExecutionService` — shared preview + record + run_sync pipeline for REST, CLI, and async runner.
- `OperationRunner` — Action Scheduler hook handler for queued operations above the async threshold (default 100, filterable).
- REST endpoints under `content-ops/v1`: `GET /catalog`, `POST /preview`, `POST /execute`, `GET /operations`, `GET /operations/{id}`, `POST /operations/{id}/undo`.
- WP-CLI commands: `wp content-ops delete`, `duplicate`, `edit`, `history`, `undo`.
- Abilities: every Target × Operation combo registered as an Abilities API ability (soft dependency).
- Common Cleanups presets shipped: trash old drafts (>90 days), trash auto-drafts.
```

- [ ] **Step 9: Tag and commit**

```bash
git add CHANGELOG.md
git commit -m "mark Phase 1a backend MVP complete in changelog"
git tag v0.2.0-alpha
```

---

## What Phase 1a does NOT include (intentional)

- **No admin UI.** Phase 1b builds the Operations Builder screen, Dashboard, History page.
- **No find/replace, CSV, or move operations.** Phase 3.
- **No WooCommerce integration.** Phase 4.
- **No scheduling runtime.** Phase 3.
- **No interactive `--yes` confirmation prompt.** CLI runs non-interactively; UI confirmation is the preview token (also used in REST). `--yes` is parsed and accepted but is a no-op in Phase 1a.

After this plan completes, the next plan is `docs/superpowers/plans/<date>-phase-1b-admin-ui.md`.

---

## Self-review (completed during plan authoring)

**Spec coverage** — the Phase 1a goal (PostTarget + Delete/Duplicate/BulkEdit + REST + CLI + Abilities + presets + undo everywhere) maps to:
- PostTarget: Tasks 1-4.
- DeleteOperation: Tasks 5-7.
- DuplicateOperation: Tasks 8-10.
- BulkEditOperation: Tasks 11-13.
- ExecutionService + OperationRunner: Task 14.
- Plugin wiring: Task 15.
- REST `/catalog`, `/preview`, `/execute`, `/operations`, `/operations/{id}`, `/operations/{id}/undo`: Tasks 16-20.
- WP-CLI `delete`, `duplicate`, `edit`, `history`, `undo`: Tasks 21-24.
- Abilities matrix registration: Task 25.
- Common Cleanups presets: Task 26.
- End-to-end verification + changelog + tag: Task 27.

**Placeholder scan** — each task ships the complete code for its classes and tests. Error codes use the `co.*` dot namespace. `__operation_id` reserved param is documented where used. No TBD/TODO/later placeholders.

**Type consistency** — `PostTarget`, `DeleteOperation`, `DuplicateOperation`, `BulkEditOperation`, `ExecutionService`, `OperationRunner`, `CatalogController`, `PreviewController`, `ExecuteController`, `OperationsController`, `UndoController`, `DeleteCommand`, `DuplicateCommand`, `EditCommand`, `HistoryCommand`, `UndoCommand`, `PresetCatalog`, `AbilitiesBridge` — identifiers consistent across tasks. Operation slugs locked: `delete`, `duplicate`, `edit`. Target slug for posts: `post` (+ `page` via second PostTarget instance). REST namespace constant `REST_NAMESPACE = 'content-ops/v1'` matches Phase 0. Preview-token payload shape consistent across DeleteOperation::preview, DuplicateOperation::preview, BulkEditOperation::preview, and ExecuteController re-verification (`target, operation, filters, params, sample_ids, count`). `OperationRunner::HOOK = 'content_ops_run_operation'` matches spec. `ExecutionService::BATCH_SIZE = 50` matches the async batch-size default from Phase 0 spec section 7.4.

**Deferred decisions locked during planning:**
- `PostTarget` is one instance per post type, slug = post type slug.
- `DeleteOperation::supports_undo()` returns `true` unconditionally; `undo()` checks `params.permanent` at undo time.
- `DuplicateOperation::execute_batch` exposes created IDs via `last_new_ids()` for `ExecutionService` to persist.
- `BulkEditOperation` requires `$params['__operation_id']` to snapshot correctly; `ExecutionService` always sets it.
- CLI commands run synchronously regardless of size (no dispatch to Action Scheduler from CLI), prioritizing predictable exit codes and output.
- Async threshold filter is `content_ops_async_threshold` (default 100); REST `/execute` queues above the threshold.
