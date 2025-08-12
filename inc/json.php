<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * AJAX: generér JSON (manuel opdatering fra knap) – kræver nonce
 */
add_action( 'wp_ajax_loevegaarden_generate_json', 'lgpp_json' );
function lgpp_json() {
	check_admin_referer( 'lgpp_gen', 'nonce' );
	$updated = lgpp_generate_json_core();
	wp_send_json_success( [ 'updated_at' => $updated ] );
}

/**
 * Kernegenerator – bruges af både AJAX og cron
 */
function lgpp_generate_json_core() {
	global $wpdb;
	$paths = lgpp_get_json_paths();

	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" );
	if ( empty( $ids ) ) {
		file_put_contents( $paths['path'], wp_json_encode( [] ) );
		return lgpp_get_local_time( time() );
	}
	$in = implode( ',', array_map( 'intval', $ids ) );

	// postmeta batch
	$meta_rows = $wpdb->get_results( "SELECT post_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) AND meta_key IN ('_global_unique_id','wpbet_product_tracking','_stock')" );
	$meta      = [];
	foreach ( $meta_rows as $r ) { $meta[ $r->post_id ][ $r->meta_key ] = $r->meta_value; }

	// placering (term)
	$rel     = $wpdb->get_results( "SELECT tr.object_id,tt.term_taxonomy_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id WHERE tt.taxonomy='pa_placering' AND tr.object_id IN ({$in})" );
	$tt_ids  = wp_list_pluck( $rel, 'term_taxonomy_id' );
	$names   = [];
	if ( $tt_ids ) {
		$list  = implode( ',', array_map( 'intval', $tt_ids ) );
		$terms = $wpdb->get_results( "SELECT tt.term_taxonomy_id,t.name FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON tt.term_id=t.term_id WHERE tt.term_taxonomy_id IN ({$list})" );
		foreach ( $terms as $t ) { $names[ $t->term_taxonomy_id ] = $t->name; }
	}
	$place = [];
	foreach ( $rel as $r ) { $place[ $r->object_id ] = $names[ $r->term_taxonomy_id ] ?? ''; }

	// batches (expiry)
	$tb = $wpdb->prefix . 'webis_pbet';
	$br = $wpdb->get_results( "SELECT post_id,SUM(quantity) sum_qty,GROUP_CONCAT(CONCAT(quantity,'@',expiry_date)) batches FROM {$tb} WHERE post_id IN ({$in}) GROUP BY post_id" );
	$batches = [];
	foreach ( $br as $r ) { $batches[ $r->post_id ] = $r->batches ? explode( ',', $r->batches ) : []; }

	// åbne ordrer (processing)
	$or = $wpdb->get_results( "SELECT pm_pid.meta_value pid,pm_qty.meta_value+0 qty FROM {$wpdb->posts} o JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.ID=oi.order_id JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_pid ON oi.order_item_id=pm_pid.order_item_id AND pm_pid.meta_key='_product_id' JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_qty ON oi.order_item_id=pm_qty.order_item_id AND pm_qty.meta_key='_qty' WHERE o.post_type='shop_order' AND o.post_status='wc-processing' AND pm_pid.meta_value IN ({$in})" );
	$open = [];
	foreach ( $or as $r ) { $pid = (int) $r->pid; $open[ $pid ] = ( $open[ $pid ] ?? 0 ) + (int) $r->qty; }

	// saml JSON-data
	$data = [];
	foreach ( $ids as $id ) {
		$enabled = ( $meta[ $id ]['wpbet_product_tracking'] ?? '' ) === 'yes';
		$current = $enabled ? ( $batches[ $id ] ?? [] ) : (int) ( $meta[ $id ]['_stock'] ?? 0 );
		$data[] = [
			'id'             => $id,
			'title'          => get_the_title( $id ),
			'gtin'           => $meta[ $id ]['_global_unique_id'] ?? '',
			'placering'      => $place[ $id ] ?? '',
			'expiry_enabled' => $enabled,
			'current_stock'  => $current,
			'open_orders'    => $open[ $id ] ?? 0,
		];
	}

	file_put_contents( $paths['path'], wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
	return lgpp_get_local_time( time() );
}

/**
 * Cron: kør hver time
 */
add_action( 'init', function () {
	// Ryd evt. gammel daily
	if ( wp_next_scheduled( 'lgpp_daily' ) ) {
		wp_clear_scheduled_hook( 'lgpp_daily' );
	}
	if ( ! wp_next_scheduled( 'lgpp_hourly' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'lgpp_hourly' );
	}
} );
add_action( 'lgpp_hourly', 'lgpp_generate_json_core' );