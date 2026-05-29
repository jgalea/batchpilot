export const getBootstrap = () => {
	const data =
		typeof window !== 'undefined' ? window.batchPilotAdmin : undefined;
	if ( ! data || ! data.restUrl || ! data.nonce ) {
		throw new Error( 'batchPilotAdmin bootstrap payload missing' );
	}
	return data;
};
