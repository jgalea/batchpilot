import { render, screen } from '@testing-library/react';
import PresetCards from '../components/PresetCards';

describe( 'PresetCards', () => {
	it( 'renders a card per preset and links to Operations Builder with prefill', () => {
		const presets = [
			{
				slug: 'trash-old-drafts',
				label: 'Trash old drafts',
				description: 'Modified > 90d ago',
				target: 'post',
				operation: 'delete',
				filters: { status: 'draft' },
				params: {},
			},
		];
		const baseUrl =
			'http://example.test/wp-admin/admin.php?page=content-ops-operations';
		render( <PresetCards presets={ presets } operationsUrl={ baseUrl } /> );
		expect( screen.getByText( 'Trash old drafts' ) ).toBeInTheDocument();
		const link = screen.getByRole( 'link', { name: /trash old drafts/i } );
		expect( link.getAttribute( 'href' ) ).toContain(
			'preset=trash-old-drafts'
		);
	} );
} );
