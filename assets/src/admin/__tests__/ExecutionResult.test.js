import { render, screen } from '@testing-library/react';
import ExecutionResult from '../components/ExecutionResult';

describe( 'ExecutionResult', () => {
	it( 'shows completed batch stats', () => {
		render(
			<ExecutionResult
				execution={ {
					status: 'completed',
					operation_id: 12,
					batch: { processed: 10, succeeded: 10, failed: 0 },
				} }
				historyUrl="http://x?page=batchpilot-history"
			/>
		);
		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			/10 succeeded/i
		);
	} );

	it( 'shows queued state with history link', () => {
		render(
			<ExecutionResult
				execution={ { status: 'queued', operation_id: 5 } }
				historyUrl="http://x?page=batchpilot-history"
			/>
		);
		expect(
			screen.getByRole( 'link', { name: /view in history/i } )
		).toHaveAttribute(
			'href',
			expect.stringContaining( 'batchpilot-history' )
		);
	} );
} );
