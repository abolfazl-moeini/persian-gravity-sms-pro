<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class GF_SMS_Sent_List_Table extends WP_List_Table {

	function __construct() {
		global $status, $page;
		parent::__construct( array(
			'singular' => __( 'SMS', 'GF_SMS' ),
			'plural'   => __( 'SMS', 'GF_SMS' ),
			'ajax'     => false
		) );
	}

	function no_items() {
		_e( 'No items found, dude.', 'GF_SMS' );
	}

	function column_default( $item, $column_name ) {
		$align = is_rtl() ? 'right' : 'left';
		switch ( $column_name ) {
			case 'form_id':
			case 'entry_id':
				return ! empty( $item[ $column_name ] ) && is_numeric( str_replace( ',', '', $item[ $column_name ] ) ) ? esc_html( $item[ $column_name ] ) : '-';
			case 'message':
				return nl2br( esc_html( $item[ $column_name ] ) );
			case 'date':
				return esc_html( date_i18n( 'Y-m-d H:i:s', strtotime( $item[ $column_name ] ) ) );
			case 'sender':
			case 'reciever':
				return '<div style="direction:ltr !important;text-align:' . esc_attr( $align ) . ';">' . esc_html( $item[ $column_name ] ) . '</div>';
			default:
				return esc_html( print_r( $item, true ) );
		}
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'date'     => array( 'date', false ),
			'form_id'  => array( 'form_id', false ),
			'entry_id' => array( 'entry_id', false ),
			'sender'   => array( 'sender', false ),
			'reciever' => array( 'reciever', false ),
			'message'  => array( 'message', false )
		);

		return $sortable_columns;
	}

	function get_columns() {
		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'date'     => __( 'Date', 'GF_SMS' ),
			'form_id'  => __( 'Form ID', 'GF_SMS' ),
			'entry_id' => __( 'Entry ID', 'GF_SMS' ),
			'sender'   => __( 'From', 'GF_SMS' ),
			'reciever' => __( 'Recievers', 'GF_SMS' ),
			'message'  => __( 'Message', 'GF_SMS' )
		);

		return $columns;
	}

	function column_date( $item ) {

		$delete_nonce = wp_create_nonce( 'gf_delete_sms' );
		$page         = isset( $_REQUEST['page'] ) ? sanitize_text_field( $_REQUEST['page'] ) : 'gf_hannansms';

		$actions = array(
			'delete' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( add_query_arg( array(
					'page'     => $page,
					'view'     => 'sent',
					'action'   => 'delete',
					'item'     => absint( $item['id'] ),
					'_wpnonce' => $delete_nonce,
				), admin_url( 'admin.php' ) ) ),
				esc_html__( 'Delete', 'GF_SMS' )
			),
		);

		return sprintf( '%1$s %2$s', esc_html( date_i18n( 'Y-m-d H:i:s', strtotime( $item['date'] ) ) ), $this->row_actions( $actions ) );
	}

	function get_bulk_actions() {
		$actions = array(
			'bulk_delete' => __( 'Delete', 'GF_SMS' )
		);

		return $actions;
	}

	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="item[]" value="%d" />',
			absint( $item['id'] )
		);
	}

	function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$total_items  = $this->record_count();

		$this->set_pagination_args( array(
			'total_items' => $total_items,                  //WE have to calculate the total number of items
			'per_page'    => $per_page                     //WE have to determine how many items to show on a page
		) );
		$this->items = $this->get_items( $per_page, $current_page );
	}


	/**
	 * Returns the count of records in the database.
	 *
	 * @return int
	 */
	public function record_count() {
		global $wpdb;

		$sent_table_name = GFHANNANSMS_Pro_SQL::sent_table();

		if ( isset( $_REQUEST['s'] ) && ! empty( trim( $_REQUEST['s'] ) ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $_REQUEST['s'] ) ) . '%';
			$sql  = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$sent_table_name} WHERE `message` LIKE %s OR `reciever` LIKE %s OR `sender` LIKE %s",
				$like,
				$like,
				$like
			);
			return (int) $wpdb->get_var( $sql );
		} elseif ( isset( $_REQUEST['id'] ) && is_numeric( $_REQUEST['id'] ) ) {
			$form_id = absint( $_REQUEST['id'] );
			$sql     = $wpdb->prepare( "SELECT COUNT(*) FROM {$sent_table_name} WHERE `form_id` = %d", $form_id );
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$sent_table_name}" );
	}

	public function process_bulk_action() {

		$action = $this->current_action();

		if ( 'delete' === $action ) {

			if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'gf_delete_sms' ) ) {
				wp_die( esc_html__( 'Unauthorized request.', 'GF_SMS' ), 403 );
			}

			if ( ! GFCommon::current_user_can_any( array( 'gravityforms_edit_forms', 'gravityforms_hannansms' ) ) ) {
				wp_die( esc_html__( 'You do not have permission to delete messages.', 'GF_SMS' ), 403 );
			}

			if ( ! empty( $_REQUEST['item'] ) ) {
				$this->delete_item( absint( $_REQUEST['item'] ) );
				echo '<div class="updated notice is-dismissible below-h2"><p>' . esc_html__( 'Item removed.', 'GF_SMS' ) . '</p></div>';
			}

		} else if ( 'bulk_delete' === $action ) {

			if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'bulk-' . $this->_args['plural'] ) ) {
				wp_die( esc_html__( 'Unauthorized request.', 'GF_SMS' ), 403 );
			}

			if ( ! GFCommon::current_user_can_any( array( 'gravityforms_edit_forms', 'gravityforms_hannansms' ) ) ) {
				wp_die( esc_html__( 'You do not have permission to delete messages.', 'GF_SMS' ), 403 );
			}

			$delete_ids = isset( $_REQUEST['item'] ) && is_array( $_REQUEST['item'] ) ? array_map( 'absint', $_REQUEST['item'] ) : array();
			foreach ( $delete_ids as $id ) {
				if ( $id > 0 ) {
					$this->delete_item( $id );
				}
			}

			echo '<div class="updated notice is-dismissible below-h2"><p>' . esc_html__( 'Items removed.', 'GF_SMS' ) . '</p></div>';
		}
	}


	/**
	 * Delete a item record.
	 *
	 * @param int $id item ID
	 */
	public function delete_item( $id ) {
		global $wpdb;

		$sent_table_name = GFHANNANSMS_Pro_SQL::sent_table();

		$wpdb->delete( $sent_table_name, array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Retrieve items data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public function get_items( $per_page = 20, $page_number = 1 ) {

		global $wpdb;

		$sent_table_name = GFHANNANSMS_Pro_SQL::sent_table();
		$per_page        = absint( $per_page );
		$page_number     = absint( $page_number );
		$offset          = ( $page_number - 1 ) * $per_page;

		// Whitelist sorting
		$valid_columns = array( 'id', 'date', 'form_id', 'entry_id', 'sender', 'reciever', 'message' );
		$orderby       = isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $valid_columns, true ) ? sanitize_key( $_REQUEST['orderby'] ) : 'id';
		$order         = isset( $_REQUEST['order'] ) && strtolower( $_REQUEST['order'] ) === 'asc' ? 'ASC' : 'DESC';

		if ( isset( $_REQUEST['s'] ) && ! empty( trim( $_REQUEST['s'] ) ) ) {
			$like = '%' . $wpdb->esc_like( sanitize_text_field( $_REQUEST['s'] ) ) . '%';
			$sql  = $wpdb->prepare(
				"SELECT * FROM {$sent_table_name} WHERE `message` LIKE %s OR `reciever` LIKE %s OR `sender` LIKE %s ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$like,
				$like,
				$like,
				$per_page,
				$offset
			);
		} elseif ( isset( $_REQUEST['id'] ) && is_numeric( $_REQUEST['id'] ) ) {
			$form_id = absint( $_REQUEST['id'] );
			$sql     = $wpdb->prepare(
				"SELECT * FROM {$sent_table_name} WHERE `form_id` = %d ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$form_id,
				$per_page,
				$offset
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$sent_table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

} //class


class GFHANNANSMS_Pro_Sent {

	public static function table() {

		$gf_sms_sent = new GF_SMS_Sent_List_Table();
		echo '<div class="wrap"><h2>' . __( 'Sent Messages', 'GF_SMS' );

		if ( isset( $_GET['id'] ) ) {

			$form = RGFormsModel::get_form_meta( rgget( 'id' ) );
			if ( ! empty( $form ) ) {
				echo ' (' . esc_html( $form['title'] ) . ')</h2>';
			} else {
				echo '</h2>';
			}
		} else {
			echo '</h2>';
		}

		$gf_sms_sent->prepare_items();

		echo '<style type="text/css">';
		echo '.wp-list-table .column-id { width: 5%; }';
		echo '</style>';


		?>
        <form method="post">
            <input type="hidden" name="page" value="GF_SMS_list_table">
			<?php
			$gf_sms_sent->search_box( __( 'Search', 'GF_SMS' ), 'search_id' );
			$gf_sms_sent->display();
			?>
        </form></div>
		<?php

	}
}