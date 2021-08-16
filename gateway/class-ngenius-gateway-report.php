<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
/**
 * Ngenius_Gateway_Report class.
 */
class Ngenius_Gateway_Report extends WP_List_Table {

	/**
	 *
	 * @var array data
	 */
	public $found_data = array();
	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => __( 'n-genius Report', 'ngenius_table' ),
				'plural'   => __( 'n-genius Report', 'ngenius_table' ),
				'ajax'     => false,
			)
		);
		add_action( 'admin_head', array( &$this, 'admin_header' ) );
	}
	/**
	 * Admin Header
	 *
	 * @return null
	 */
	public function admin_header() {
		$page = ( isset( $_GET['page'] ) ) ? esc_attr( $_GET['page'] ) : false;
		if ( 'ngenius-report' !== $page ) {
			return;
		}
		echo '<style type="text/css">';
		echo '.wp-list-table .column-order_id { width: 7%; }';
		echo '.wp-list-table .column-amount { width: 7%; }';
		echo '.wp-list-table .column-reference { width: 14%; }';
		echo '.wp-list-table .column-payment_action { width: 6%;}';

		echo '.wp-list-table .column-state { width: 8%; }';
		echo '.wp-list-table .column-status { width: 9%; }';
		echo '.wp-list-table .column-payment_id { width: 15%; }';
		echo '.wp-list-table .column-capture_id { width: 15%;}';

		echo '.wp-list-table .column-capture_amount { width: 8%; }';
		echo '.wp-list-table .column-created_at { width: 11%; }';
		echo '</style>';
	}
	/**
	 * Default Column
	 *
	 * @param array $item
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'order_id':
			case 'amount':
			case 'reference':
			case 'action':
			case 'state':
			case 'status':
			case 'payment_id':
			case 'capture_id':
			case 'captured_amt':
			case 'created_at':
				return $item[ $column_name ];
			default:
				return print_r( $item, true );
		}
	}
	/**
	 * Sortable Columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		$sortable_columns = array(
			'order_id'   => array( 'order_id', false ),
			'amount'     => array( 'amount', false ),
			'created_at' => array( 'created_at', false ),
		);
		return $sortable_columns;
	}
	/**
	 * Columns
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'order_id'     => __( 'Order ID', 'ngenius-report' ),
			'amount'       => __( 'Amount', 'ngenius-report' ),
			'reference'    => __( 'Order Ref', 'ngenius-report' ),
			'action'       => __( 'Payment Action', 'ngenius-report' ),
			'state'        => __( 'State', 'ngenius-report' ),
			'status'       => __( 'Status', 'ngenius-report' ),
			'payment_id'   => __( 'Payment ID', 'ngenius-report' ),
			'capture_id'   => __( 'Capture ID', 'ngenius-report' ),
			'captured_amt' => __( 'Captured Amount', 'ngenius-report' ),
			'created_at'   => __( 'Created At', 'ngenius-report' ),
		);
		return $columns;
	}
	/**
	 * Sort - Reorder
	 *
	 * @param array $a
	 * @param array $b
	 * @return string
	 */
	public function usort_reorder( $a, $b ) {
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'order_id';
		$order   = ( ! empty( $_GET['order'] ) ) ? $_GET['order'] : 'desc';
		$result  = strcmp( $a[ $orderby ], $b[ $orderby ] );
		return ( 'asc' === $order ) ? $result : -$result;
	}
	/**
	 * Format Amount
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_amount( $item ) {
		return $item['currency'] . ' ' . number_format( $item['amount'], 2 );
	}
	/**
	 * Format Amount
	 *
	 * @param array $item
	 * @return string
	 */
	public function column_captured_amt( $item ) {
		return $item['currency'] . ' ' . number_format( $item['captured_amt'], 2 );
	}
	/**
	 * Prepare Items
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();
		$orders                = $this->fetch_order();
		usort( $orders, array( &$this, 'usort_reorder' ) );
		$per_page         = $this->get_items_per_page( 'records_per_page', 10 );
		$current_page     = $this->get_pagenum();
		$total_items      = count( $orders );
		$this->found_data = array_slice( $orders, ( $current_page - 1 ) * $per_page, $per_page );
		$this->set_pagination_args(
			array(
				'total_items' => $total_items, //WE have to calculate the total number of items
				'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
			)
		);
		$this->items = $this->found_data;
	}
	/**
	 * Fetch data
	 *
	 * @global object $wpdb
	 * @return array
	 */
	public function fetch_order() {
		global $wpdb;
		$where = '';
		if ( isset( $_POST['s'] ) && ! empty( $_POST['s'] ) ) {
			$s     = $_POST['s'];
			$where = " WHERE `reference` LIKE '%$s%' OR `created_at` LIKE '%$s%' OR `captured_amt` LIKE '%$s%' OR `payment_id` LIKE '%$s%' OR  `capture_id` LIKE '%$s%' OR `action` LIKE '%$s%' OR `order_id` LIKE '%$s%' OR `amount` LIKE '%$s%' OR `state` LIKE '%$s%' OR `status` LIKE '%$s%'";
		}
		return $wpdb->get_results( 'SELECT * FROM ' . NGENIUS_TABLE . $where, ARRAY_A );
	}

}
