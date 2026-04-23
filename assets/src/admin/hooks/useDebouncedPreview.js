import { useEffect, useRef, useState } from '@wordpress/element';
import { normalizeError } from '../api';

export const useDebouncedPreview = (
	api,
	state,
	filtersToArgs,
	delayMs = 300
) => {
	const [ preview, setPreview ] = useState( null );
	const [ previewing, setPreviewing ] = useState( false );
	const [ previewError, setPreviewError ] = useState( null );
	const abortRef = useRef( null );
	const timerRef = useRef( null );

	useEffect( () => {
		if ( ! state.target || ! state.operation ) {
			return undefined;
		}
		if ( timerRef.current ) {
			clearTimeout( timerRef.current );
		}
		timerRef.current = setTimeout( async () => {
			if ( abortRef.current ) {
				abortRef.current.abort();
			}
			abortRef.current = new AbortController();
			setPreviewing( true );
			setPreviewError( null );
			try {
				const res = await api.preview(
					{
						target: state.target,
						operation: state.operation,
						filters: filtersToArgs( state.filters ),
						params: state.params,
					},
					abortRef.current.signal
				);
				setPreview( res );
			} catch ( err ) {
				const n = normalizeError( err );
				if ( ! n.aborted ) {
					setPreviewError( n );
				}
			} finally {
				setPreviewing( false );
			}
		}, delayMs );

		return () => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
		};
	}, [ api, state, filtersToArgs, delayMs ] );

	return { preview, previewing, previewError };
};
