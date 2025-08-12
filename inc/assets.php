<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'product_page_loevegaarden_placeringer' !== $hook ) { return; }
	$paths = lgpp_get_json_paths();

	wp_enqueue_script( 'lgpp-js', LGPP_PLUGIN_URL . 'script.js', [ 'jquery' ], null, true );
	wp_localize_script( 'lgpp-js', 'lgppData', [
		'jsonUrl'    => esc_url( $paths['url'] ),
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonceGen'   => wp_create_nonce( 'lgpp_gen' ),
		'nonceSave'  => wp_create_nonce( 'lgpp_save' ),
		'updated_at' => file_exists( $paths['path'] ) ? lgpp_get_local_time( filemtime( $paths['path'] ) ) : '',
		'placements' => wp_list_pluck( get_terms( [ 'taxonomy' => 'pa_placering', 'hide_empty' => false ] ), 'name' ),
	] );
	wp_enqueue_style( 'lgpp-css', LGPP_PLUGIN_URL . 'style.css' );
} );