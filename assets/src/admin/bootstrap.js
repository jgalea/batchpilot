export const getBootstrap = () => {
	const data =
		typeof window !== 'undefined' ? window.contentOpsAdmin : undefined;
	if ( ! data || ! data.restUrl || ! data.nonce ) {
		throw new Error( 'contentOpsAdmin bootstrap payload missing' );
	}
	return data;
};
