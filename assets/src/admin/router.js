const PAGE_IDS = {
	dashboard: 'content-ops-dashboard-root',
	operations: 'content-ops-operations-root',
	history: 'content-ops-history-root',
	settings: 'content-ops-settings-root',
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
