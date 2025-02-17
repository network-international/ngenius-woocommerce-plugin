/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * Payfast data comes form the server passed on a global object.
 */
export const getNgeniusServerData = () => {
	const ngeniusServerData = getSetting('ngenius_data', null);
	if (!ngeniusServerData) {
		throw new Error('N-Genius initialization data is not available');
	}
	return ngeniusServerData;
};
