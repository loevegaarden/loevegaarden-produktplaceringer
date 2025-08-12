<?php
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