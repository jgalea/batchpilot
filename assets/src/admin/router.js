const PAGE_IDS = {
	dashboard: 'batchpilot-dashboard-root',
	operations: 'batchpilot-operations-root',
	history: 'batchpilot-history-root',
	settings: 'batchpilot-settings-root',
};

export const detectPage = ( doc = document ) => {
	for ( const [ page, id ] of Object.entries( PAGE_IDS ) ) {
		const mount = doc.getElementById( id );
		if ( mount ) {
			return { page, mount };
		}
	}
	return null;
};
