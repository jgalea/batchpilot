import { render, screen } from '@testing-library/react';
import PreviewPanel from '../components/PreviewPanel';

describe( 'PreviewPanel', () => {
	it( 'shows count and sample rows', () => {
		render(
			<PreviewPanel
				preview={ {
					count: 42,
					sample_ids: [ 1, 2 ],
					display_rows: [
						{
							id: 1,
							title: 'First',
							status: 'draft',
							date: '2026-04-01 10:00:00',
							edit_url: 'http://x/edit/1',
							thumbnail_url: null,
						},
						{
							id: 2,
							title: 'Second',
							status: 'draft',
							date: '2026-04-02 11:00:00',
							edit_url: 'http://x/edit/2',
							thumbnail_url: null,
						},
					],
					warnings: [],
				} }
				previewing={ false }
				previewError={ null }
			/>
		);
		const matches = screen.getAllByText( /42/ );
		expect( matches.length ).toBeGreaterThan( 0 );
		expect( screen.getByText( 'First' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Second' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: /first/i } )
		).toHaveAttribute( 'href', 'http://x/edit/1' );
	} );

	it( 'shows error message when previewError is set', () => {
		render(
			<PreviewPanel
				preview={ null }
				previewing={ false }
				previewError={ {
					code: 'co.x',
					message: 'bad filter',
					context: {},
				} }
			/>
		);
		expect( screen.getByRole( 'alert' ) ).toHaveTextContent( /bad filter/ );
	} );
} );
