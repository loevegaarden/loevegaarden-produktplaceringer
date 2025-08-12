<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Admin menu (submenu under Produkter)
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