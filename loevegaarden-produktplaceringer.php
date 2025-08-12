<?php
/**
 * Plugin Name: Løvegården Produktplaceringer
 * Description: Viser en søgbar tabel over produktplaceringer; mulighed for at rette placering samt registrere antal og bedst-før-dato med eller uden batchtracking.
 * Version: 1.18.5
 * Author: Løvegården
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Basale konstanter til brug i inc-filer
if ( ! defined( 'LGPP_PLUGIN_FILE' ) ) {
	define( 'LGPP_PLUGIN_FILE', __FILE__ );
	define( 'LGPP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'LGPP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

// === Inkludér modulopdelte filer ===
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/json.php';
require_once __DIR__ . '/inc/menu.php';
require_once __DIR__ . '/inc/admin-page.php';
require_once __DIR__ . '/inc/assets.php';
require_once __DIR__ . '/inc/save.php'; // Trin 3: flyt gem-lagik hertil