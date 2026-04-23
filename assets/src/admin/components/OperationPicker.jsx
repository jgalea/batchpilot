import { Button } from '@wordpress/components';

const OperationPicker = ( { operations, supported, selected, onSelect } ) => {
	const visible = operations.filter( ( op ) =>
		supported.includes( op.slug )
	);
	return (
		<div className="content-ops-operation-picker">
			{ visible.map( ( op ) => (
				<Button
					key={ op.slug }
					variant={ selected === op.slug ? 'primary' : 'secondary' }
					aria-pressed={ selected === op.slug }
					onClick={ () => onSelect( op.slug ) }
				>
					{ op.label }
				</Button>
			) ) }
		</div>
	);
};

export default OperationPicker;
