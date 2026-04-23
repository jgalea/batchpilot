import { render, screen, waitFor } from '@testing-library/react';
import OperationsBuilder from '../pages/OperationsBuilder';

beforeAll( () => {
	global.window.contentOpsAdmin = {
		namespace: 'content-ops/v1',
		restUrl: 'x',
		nonce: 'n',
		capabilities: {},
		pages: { history: 'h', operations: 'o', dashboard: 'd', settings: 's' },
		adminUrl: 'a/',
		pluginUrl: 'p/',
		version: 'v',
	};
} );

describe( 'OperationsBuilder prefill', () => {
	it( 'prefills target/operation/filters from a matched preset', async () => {
		const api = {
			fetchCatalog: jest.fn().mockResolvedValue( {
				targets: [
					{
						slug: 'post',
						label: 'Posts',
						filters: [
							{
								key: 'status',
								label: 'Status',
								type: 'enum',
								schema: {},
							},
						],
					},
				],
				operations: [
					{
						slug: 'delete',
						label: 'Delete',
						params_schema: {
							type: 'object',
							properties: { permanent: { type: 'boolean' } },
						},
						supports_undo: true,
					},
				],
				presets: [
					{
						slug: 'trash-old-drafts',
						label: 'Trash',
						description: '',
						target: 'post',
						operation: 'delete',
						filters: { status: 'draft' },
						params: { permanent: false },
					},
				],
			} ),
			preview: jest.fn().mockResolvedValue( {
				count: 0,
				sample_ids: [],
				preview_token: '',
				warnings: [],
				display_rows: [],
			} ),
		};

		window.history.replaceState( {}, '', '/?preset=trash-old-drafts' );
		render( <OperationsBuilder api={ api } /> );

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: 'Posts' } )
			).toHaveAttribute( 'aria-pressed', 'true' )
		);
		expect(
			screen.getByRole( 'button', { name: 'Delete' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	it( 'prefills from a rerun operation id via api.getOperation', async () => {
		const api = {
			fetchCatalog: jest.fn().mockResolvedValue( {
				targets: [
					{
						slug: 'post',
						label: 'Posts',
						filters: [
							{
								key: 'status',
								label: 'Status',
								type: 'enum',
								schema: {},
							},
						],
					},
				],
				operations: [
					{
						slug: 'delete',
						label: 'Delete',
						params_schema: {
							type: 'object',
							properties: { permanent: { type: 'boolean' } },
						},
						supports_undo: true,
					},
				],
				presets: [],
			} ),
			preview: jest.fn().mockResolvedValue( {
				count: 0,
				sample_ids: [],
				preview_token: '',
				warnings: [],
				display_rows: [],
			} ),
			getOperation: jest.fn().mockResolvedValue( {
				id: 42,
				target: 'post',
				type: 'delete',
				filters: { status: 'draft' },
				params: { permanent: true },
			} ),
		};

		window.history.replaceState( {}, '', '/?rerun=42' );
		render( <OperationsBuilder api={ api } /> );

		await waitFor( () =>
			expect( api.getOperation ).toHaveBeenCalledWith( 42 )
		);
		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: 'Posts' } )
			).toHaveAttribute( 'aria-pressed', 'true' )
		);
		expect(
			screen.getByRole( 'button', { name: 'Delete' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	it( 'prefills target + operation + ids filter from raw query string', async () => {
		const api = {
			fetchCatalog: jest.fn().mockResolvedValue( {
				targets: [
					{
						slug: 'post',
						label: 'Posts',
						filters: [
							{
								key: 'ids',
								label: 'IDs',
								type: 'post',
								schema: {},
							},
						],
					},
				],
				operations: [
					{
						slug: 'delete',
						label: 'Delete',
						params_schema: {
							type: 'object',
							properties: { permanent: { type: 'boolean' } },
						},
						supports_undo: true,
					},
				],
				presets: [],
			} ),
			preview: jest.fn().mockResolvedValue( {
				count: 0,
				sample_ids: [],
				preview_token: '',
				warnings: [],
				display_rows: [],
			} ),
		};

		window.history.replaceState(
			{},
			'',
			'/?target=post&operation=delete&filters[ids][]=10&filters[ids][]=11'
		);
		render( <OperationsBuilder api={ api } /> );

		await waitFor( () =>
			expect(
				screen.getByRole( 'button', { name: 'Posts' } )
			).toHaveAttribute( 'aria-pressed', 'true' )
		);
		expect(
			screen.getByRole( 'button', { name: 'Delete' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
	} );
} );
