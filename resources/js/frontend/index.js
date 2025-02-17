/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import { PAYMENT_METHOD_NAME } from './constants';
import { getNgeniusServerData } from './ngenius-utils';

const Content = () => {
	return decodeEntities(getNgeniusServerData()?.description || '');
};

const Label = () => {
	return (
		<img
			src={getNgeniusServerData()?.logo_url}
			alt={getNgeniusServerData()?.title}
		/>
	);
};

registerPaymentMethod({
	name: PAYMENT_METHOD_NAME,
	label: <Label />,
	ariaLabel: __('N-Genius payment method', 'woocommerce-gateway-ngenius'),
	canMakePayment: () => true,
	content: <Content />,
	edit: <Content />,
	supports: {
		features: getNgeniusServerData()?.supports ?? [],
	},
});
