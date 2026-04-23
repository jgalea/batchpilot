import { render, screen } from '@testing-library/react';
import OperationDetailsModal from '../components/OperationDetailsModal';

describe( 'OperationDetailsModal', () => {
	it( 'renders JSON of filters and params and the list of affected IDs', () => {
		render(
			<OperationDetailsModal
				operation={ {
					id: 1,
					filters: { status: 'draft' },
					params: { permanent: false },
					affected_ids: [ 10, 11, 12 ],
				} }
				onClose={ () => {} }
			/>
		);
		expect( screen.getByText( /"status": "draft"/ ) ).toBeInTheDocument();
		expect( screen.getByText( /10, 11, 12/ ) ).toBeInTheDocument();
	} );
} );
