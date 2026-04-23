import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import OperationPicker from '../components/OperationPicker';

describe( 'OperationPicker', () => {
	it( 'renders only operations supported by the target', async () => {
		const onSelect = jest.fn();
		render(
			<OperationPicker
				operations={ [
					{ slug: 'delete', label: 'Delete' },
					{ slug: 'duplicate', label: 'Duplicate' },
					{ slug: 'edit', label: 'Bulk edit' },
				] }
				supported={ [ 'delete', 'edit' ] }
				selected={ null }
				onSelect={ onSelect }
			/>
		);
		expect(
			screen.getByRole( 'button', { name: 'Delete' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Duplicate' } )
		).not.toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Bulk edit' } )
		);
		expect( onSelect ).toHaveBeenCalledWith( 'edit' );
	} );
} );
