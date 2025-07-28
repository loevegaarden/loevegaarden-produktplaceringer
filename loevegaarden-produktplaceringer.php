<?php
/*
Plugin Name: Løvegården Produktplaceringer
Description: Viser en søgbar tabel over produktplaceringer baseret på GTIN, titel og attributten "placering".
Version: 1.2
Author: Løvegården
GitHub Plugin URI: https://github.com/loevegaarden/loevegaarden-produktplaceringer
*/

if (!defined('ABSPATH')) exit;

// Indlæs admin-menu
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        'Produktplaceringer',
        'Produktplaceringer',
        'manage_woocommerce',
        'loevegaarden_placeringer',
        'loevegaarden_placeringer_page'
    );
});

// Tilføj script og stil kun til vores plugin-side
add_action('admin_enqueue_scripts', function ($hook) {
    if (isset($_GET['page']) && $_GET['page'] === 'loevegaarden_placeringer') {
        wp_enqueue_script('loevegaarden-placeringer', plugin_dir_url(__FILE__) . 'script.js', [], false, true);
        wp_localize_script('loevegaarden-placeringer', 'loevegaardenPlaceringerData', [
            'jsonUrl' => plugin_dir_url(__FILE__) . 'produktplaceringer.json',
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);
        wp_enqueue_style('loevegaarden-style', plugin_dir_url(__FILE__) . 'style.css');
    }
});

// Vis plugin-siden
function loevegaarden_placeringer_page() {
    $json_file = plugin_dir_path(__FILE__) . 'produktplaceringer.json';
    $last_updated = file_exists($json_file) ? date_i18n("Y-m-d H:i", filemtime($json_file)) : 'Aldrig';
    echo '<div class="wrap">
        <h1>Produktplaceringer</h1>
        <div id="loevegaarden-placeringer-wrapper">
            <div class="topbar">
                <button id="update-json" class="button"><span class="dashicons dashicons-update"></span> Opdater liste</button>
                <span class="updated-time" style="margin-left: 20px;">Senest opdateret: <strong>' . esc_html($last_updated) . '</strong></span>
            </div>
            <input type="text" id="searchInput" placeholder="Søg i GTIN, titel eller placering (min. 3 tegn)">
            <table id="productTable" class="widefat fixed">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titel</th>
                        <th>GTIN</th>
                        <th>Placering</th>
                        <th>Ret</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>';
}

// Admin-ajax til manuel opdatering
add_action('wp_ajax_loevegaarden_generate_json', function () {
    if (!current_user_can('manage_woocommerce')) wp_die();
    loevegaarden_generate_json();
    wp_send_json_success('JSON opdateret');
});

// Cron-job setup ved aktivering
register_activation_hook(__FILE__, function () {
    loevegaarden_generate_json(); // Generer JSON straks ved aktivering
    if (!wp_next_scheduled('loevegaarden_daily_json')) {
        wp_schedule_event(time(), 'daily', 'loevegaarden_daily_json');
    }
});

// Fjern cron ved deaktivering
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('loevegaarden_daily_json');
});

add_action('loevegaarden_daily_json', 'loevegaarden_generate_json');

// Funktion der genererer JSON
function loevegaarden_generate_json() {
    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ];

    $products = get_posts($args);
    $result = [];

    foreach ($products as $product_id) {
        $product = wc_get_product($product_id);
        if (!$product) continue;

        $gtin = get_post_meta($product_id, '_global_unique_id', true);
        $title = $product->get_name();
        $placering_terms = wp_get_post_terms($product_id, 'pa_placering', ['fields' => 'names']);
        $placering = implode(', ', $placering_terms);

        $result[] = [
            'id' => $product_id,
            'title' => $title,
            'gtin' => $gtin,
            'placering' => $placering
        ];
    }

    file_put_contents(plugin_dir_path(__FILE__) . 'produktplaceringer.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}