<?php
/**
 * Plugin Name: Løvegården Produktplaceringer
 * Description: Viser en søgbar tabel over produktplaceringer; mulighed for at rette placering samt registrere antal og bedst-før-dato med eller uden batchtracking.
 * Version: 1.18.2
 * Author: Løvegården
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Stier til JSON i uploads-mappen
 */
function lgpp_get_json_paths() {
	$u   = wp_upload_dir();
	$dir = trailingslashit( $u['basedir'] ) . 'loevegaarden-placeringer';
	$url = trailingslashit( $u['baseurl'] ) . 'loevegaarden-placeringer';
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return [ 'path' => "$dir/produktplaceringer.json", 'url' => "$url/produktplaceringer.json" ];
}

/**
 * Lokal tid med WP’s timezone/sommertid
 */
function lgpp_get_local_time( $ts ) {
	return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
}

/** Admin menu */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=product',
		'Produktplaceringer',
		'Produktplaceringer',
		'manage_woocommerce',
		'loevegaarden_placeringer',
		'lgpp_render_page'
	);
} );

/** Render siden */
function lgpp_render_page() {
	$paths = lgpp_get_json_paths();
	$upd   = file_exists( $paths['path'] ) ? lgpp_get_local_time( filemtime( $paths['path'] ) ) : __( 'Ikke genereret', 'loevegaarden' );
	?>
	<div class="wrap" id="loevegaarden-placeringer-wrapper">
	  <h1><?php esc_html_e( 'Produktplaceringer', 'loevegaarden' ); ?></h1>
	  <div class="lgpp-controls">
		<input type="search" id="searchInput" placeholder="<?php esc_attr_e( 'Indtast mindst 3 tegn…', 'loevegaarden' ); ?>" />
		<div class="lgpp-controls-right">
		  <span id="json-updated-at"><?php echo esc_html( $upd ); ?></span>
		  <button id="update-json" class="button"><?php esc_html_e( 'Opdater liste', 'loevegaarden' ); ?></button>
		</div>
	  </div>
	  <div id="lgpp-overlay" class="hidden"><div class="lgpp-spinner"></div><p><?php esc_html_e( 'Opdaterer…', 'loevegaarden' ); ?></p></div>
	  <table id="productTable"><thead><tr>
		<th>ID</th><th><?php esc_html_e( 'Navn', 'loevegaarden' ); ?></th><th><?php esc_html_e( 'GTIN', 'loevegaarden' ); ?></th>
		<th><?php esc_html_e( 'Placering', 'loevegaarden' ); ?></th><th><?php esc_html_e( 'Anvend bedst før', 'loevegaarden' ); ?></th>
		<th><?php esc_html_e( 'Aktuel lagertal', 'loevegaarden' ); ?></th><th><?php esc_html_e( 'Antal i åbne ordrer', 'loevegaarden' ); ?></th>
		<th><?php esc_html_e( 'Antal', 'loevegaarden' ); ?></th><th><?php esc_html_e( 'Bedst før', 'loevegaarden' ); ?></th><th><?php esc_html_e( 'Gem', 'loevegaarden' ); ?></th>
	  </tr></thead><tbody>
		<tr><td colspan="10" style="text-align:center"><?php esc_html_e( 'Indtast mindst 3 tegn for at søge', 'loevegaarden' ); ?></td></tr>
	  </tbody></table>
	</div>
	<?php
}

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
 * AJAX: gem antal/dato/placering
 */
add_action( 'wp_ajax_loevegaarden_save_position_data', 'lgpp_save' );
function lgpp_save() {
	check_admin_referer( 'lgpp_save', 'nonce' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) { wp_send_json_error(); }
	global $wpdb; $webis = $wpdb->prefix . 'webis_pbet';

	$id     = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
	$qty    = isset($_POST['quantity']) ? (int) $_POST['quantity'] : 0;
	$date   = isset($_POST['date']) ? sanitize_text_field( $_POST['date'] ) : '';
	$place  = isset($_POST['placement']) ? sanitize_text_field( $_POST['placement'] ) : '';
	$enabled= isset($_POST['expiry_enabled']) && sanitize_text_field( $_POST['expiry_enabled'] ) === 'yes';

	if ( $id <= 0 ) { wp_send_json_error( ['message' => 'Ugyldigt produkt-id'] ); }

	// Placering opdateres hvis angivet
	if ( $place ) {
		wp_set_object_terms( $id, $place, 'pa_placering', false );
	}

	// Sæt/ryd tracking-flag og mode
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
				// Brug WooCommerce API så status også opdateres
				wc_update_product_stock( $product, $qty, 'increase' );
			} elseif ( $product ) {
				$new = (int) $product->get_stock_quantity() + $qty;
				$product->set_stock_quantity( $new );
				wc_update_product_stock_status( $id, $new > 0 ? 'instock' : 'outofstock' );
				$product->save();
			} else {
				// Fallback (skulle sjældent rammes)
				$stock = (int) get_post_meta( $id, '_stock', true );
				$stock += $qty;
				update_post_meta( $id, '_stock', $stock );
				if ( $stock > 0 ) { wc_update_product_stock_status( $id, 'instock' ); }
			}
			wp_send_json_success( ['message' => 'Placering og lager opdateret.'] );
		}
		wp_send_json_success( ['message' => 'Placering gemt. Ingen lagerændring foretaget.'] );
	}

	// Med bedst-før: tillad ren placering uden antal/dato
	if ( $qty <= 0 && empty( $date ) ) {
		wp_send_json_success( ['message' => 'Placering gemt. Ingen lagerændring foretaget.'] );
	}
	// Hvis kun det ene felt er udfyldt, giv venlig fejl
	if ( ($qty > 0 && empty($date)) || ($qty <= 0 && !empty($date)) ) {
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

/**
 * Assets + data til JS
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'product_page_loevegaarden_placeringer' !== $hook ) { return; }
	$paths = lgpp_get_json_paths();

	wp_enqueue_script( 'lgpp-js', plugin_dir_url( __FILE__ ) . 'script.js', [ 'jquery' ], null, true );
	wp_localize_script( 'lgpp-js', 'lgppData', [
		'jsonUrl'    => esc_url( $paths['url'] ),
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonceGen'   => wp_create_nonce( 'lgpp_gen' ),
		'nonceSave'  => wp_create_nonce( 'lgpp_save' ),
		'updated_at' => file_exists( $paths['path'] ) ? lgpp_get_local_time( filemtime( $paths['path'] ) ) : '',
		'placements' => wp_list_pluck( get_terms( [ 'taxonomy' => 'pa_placering', 'hide_empty' => false ] ), 'name' ),
	] );
	wp_enqueue_style( 'lgpp-css', plugin_dir_url( __FILE__ ) . 'style.css' );
} );