import { useEffect, useState } from '@wordpress/element';
import { normalizeError } from '../api';

export const useCatalog = ( api ) => {
	const [ catalog, setCatalog ] = useState( null );
	const [ error, setError ] = useState( null );
	useEffect( () => {
		let cancelled = false;
		api.fetchCatalog()
			.then( ( c ) => {
				if ( ! cancelled ) {
					setCatalog( c );
				}
			} )
			.catch( ( e ) => {
				if ( ! cancelled ) {
					setError( normalizeError( e ) );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ api ] );
	return { catalog, error };
};
