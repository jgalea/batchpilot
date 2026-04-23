import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import TargetPicker from '../components/TargetPicker';

describe( 'TargetPicker', () => {
	it( 'renders a pill per target and selects on click', async () => {
		const onSelect = jest.fn();
		render(
			<TargetPicker
				targets={ [
					{ slug: 'post', label: 'Posts' },
					{ slug: 'page', label: 'Pages' },
				] }
				selected={ null }
				onSelect={ onSelect }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Posts' } )
		).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Pages' } )
		);
		expect( onSelect ).toHaveBeenCalledWith( 'page' );
	} );

	it( 'marks selected target aria-pressed=true', () => {
		render(
			<TargetPicker
				targets={ [ { slug: 'post', label: 'Posts' } ] }
				selected="post"
				onSelect={ () => {} }
			/>
		);
		expect(
			screen.getByRole( 'button', { name: 'Posts' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
	} );
} );
