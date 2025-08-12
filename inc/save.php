<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// AJAX: gem antal/dato/placering (WooCommerce API for lager/status)
add_action( 'wp_ajax_loevegaarden_save_position_data', 'lgpp_save' );
function lgpp_save() {
	check_admin_referer( 'lgpp_save', 'nonce' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }
	global $wpdb; $webis = $wpdb->prefix . 'webis_pbet';

	$id      = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	$qty     = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
	$date    = isset($_POST['date']) ? sanitize_text_field( $_POST['date'] ) : '';
	$place   = isset($_POST['placement']) ? sanitize_text_field( $_POST['placement'] ) : '';
	$enabled = isset($_POST['expiry_enabled']) && sanitize_text_field( $_POST['expiry_enabled'] ) === 'yes';

	if ( $id <= 0 ) {
		wp_send_json_error( ['message' => 'Ugyldigt produkt-id'] );
	}

	// --- Placering: sørg for at global attribut + term er korrekt opsat ---
	if ( $place !== '' ) {
		$taxonomy = 'pa_placering';

		// 1) Opret term hvis den ikke findes
		$term = get_term_by( 'name', $place, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			$created = wp_insert_term( $place, $taxonomy );
			if ( is_wp_error( $created ) ) {
				wp_send_json_error( ['message' => 'Kunne ikke oprette placeringen: ' . $created->get_error_message()] );
			}
		}

		// 2) Sørg for at produktet har attributten i _product_attributes
		$attrs = get_post_meta( $id, '_product_attributes', true );
		if ( ! is_array( $attrs ) ) {
			$attrs = [];
		}
		if ( ! isset( $attrs[ $taxonomy ] ) || empty( $attrs[ $taxonomy ]['is_taxonomy'] ) ) {
			$attrs[ $taxonomy ] = [
				'name'         => $taxonomy,
				'value'        => '',
				'position'     => is_array( $attrs ) ? count( $attrs ) : 0,
				'is_visible'   => 0, // visning på produktsiden? 0 = nej, sæt 1 hvis I ønsker det
				'is_variation' => 0,
				'is_taxonomy'  => 1,
			];
			update_post_meta( $id, '_product_attributes', $attrs );
		}

		// 3) Knyt termen til produktet (erstatter eksisterende)
		wp_set_object_terms( $id, $place, $taxonomy, false );

		// 4) Ryd caches og gem produkt (så backend viser korrekt)
		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $id );
		}
		if ( function_exists( 'wc_get_product' ) ) {
			$product_for_attr = wc_get_product( $id );
			if ( $product_for_attr && method_exists( $product_for_attr, 'save' ) ) {
				$product_for_attr->save();
			}
		}
	}

	// --- Sæt/ryd tracking-flag og mode ---
	update_post_meta( $id, 'wpbet_product_tracking', $enabled ? 'yes' : 'no' );
	if ( $enabled ) {
		update_post_meta( $id, 'wpbet-product-tracking-mode', 'expiry_only' );
	}

	// --- Lagerlogik ---
	if ( ! $enabled ) {
		// Uden bedst-før: læg antal til lager hvis > 0, ellers kun placering
		if ( $qty > 0 ) {
			$product = function_exists('wc_get_product') ? wc_get_product( $id ) : null;
			if ( $product && method_exists( $product, 'managing_stock' ) && $product->managing_stock() ) {
				wc_update_product_stock( $product, $qty, 'increase' );
			} elseif ( $product ) {
				$new = (int) $product->get_stock_quantity() + $qty;
				$product->set_stock_quantity( $new );
				wc_update_product_stock_status( $id, $new > 0 ? 'instock' : 'outofstock' );
				$product->save();
			} else {
				$stock = (int) get_post_meta( $id, '_stock', true );
				$stock += $qty;
				update_post_meta( $id, '_stock', $stock );
				if ( $stock > 0 ) { wc_update_product_stock_status( $id, 'instock' ); }
			}
			wp_send_json_success( ['message' => 'Placering og lager opdateret.'] );
		}
		// Kun placering ændret
		wp_send_json_success( ['message' => 'Placering gemt. Ingen lagerændring foretaget.'] );
	}

	// Med bedst-før: tillad ren placering uden antal/dato
	if ( $qty <= 0 && empty( $date ) ) {
		wp_send_json_success( ['message' => 'Placering gemt. Ingen lagerændring foretaget.'] );
	}
	if ( ( $qty > 0 && empty( $date ) ) || ( $qty <= 0 && ! empty( $date ) ) ) {
		wp_send_json_error( ['message' => 'Når “Anvend bedst før” er markeret og du vil tilføje antal, skal du udfylde både Antal og Bedst-før dato. Hvis du kun vil ændre placering, så lad begge felter være tomme.'] );
	}

	// Begge felter udfyldt korrekt → indsæt batch og sæt lager
	$wpdb->insert( $webis, [ 'post_id' => $id, 'quantity' => $qty, 'expiry_date' => $date ], [ '%d', '%d', '%s' ] );
	$sum = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(quantity) FROM {$webis} WHERE post_id=%d", $id ) );

	$product = function_exists('wc_get_product') ? wc_get_product( $id ) : null;
	if ( $product && method_exists( $product, 'managing_stock' ) && $product->managing_stock() ) {
		wc_update_product_stock( $product, $sum, 'set' );
	} elseif ( $product ) {
		$product->set_stock_quantity( $sum );
		wc_update_product_stock_status( $id, $sum > 0 ? 'instock' : 'outofstock' );
		$product->save();
	} else {
		update_post_meta( $id, '_stock', $sum );
		wc_update_product_stock_status( $id, $sum > 0 ? 'instock' : 'outofstock' );
	}

	wp_send_json_success( ['message' => 'Placering og lager (med bedst-før) opdateret.'] );
}
