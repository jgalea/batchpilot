import { Button } from '@wordpress/components';

const TargetPicker = ( { targets, selected, onSelect } ) => (
	<div className="content-ops-target-picker" role="group" aria-label="Target">
		{ targets.map( ( t ) => (
			<Button
				key={ t.slug }
				variant={ selected === t.slug ? 'primary' : 'secondary' }
				aria-pressed={ selected === t.slug }
				onClick={ () => onSelect( t.slug ) }
			>
				{ t.label }
			</Button>
		) ) }
	</div>
);

export default TargetPicker;
