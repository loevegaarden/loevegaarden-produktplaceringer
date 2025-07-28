<?php
/*
Plugin Name: Løvegården Produktplaceringer
Description: Viser en søgbar tabel over produktplaceringer baseret på GTIN, titel og attributten "placering".
Version: 1.3
Author: Løvegården
GitHub Plugin URI: https://github.com/loevegaarden/loevegaarden-produktplaceringer
*/

if (!defined('ABSPATH')) exit;

// Debug log for at sikre plugin indlæses
error_log('Løvegården Produktplaceringer plugin loaded');

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        'Produktplaceringer',
        'Produktplaceringer',
        'manage_woocommerce',
        'loevegaarden-produktplaceringer',
        'loevegaarden_render_placeringer_page'
    );
});

function loevegaarden_render_placeringer_page() {
    $json_file = plugin_dir_path(__FILE__) . 'loevegaarden-placeringer.json';
    $json_url = plugin_dir_url(__FILE__) . 'loevegaarden-placeringer.json';

    echo '<div class="wrap">
        <h1>Produktplaceringer</h1>
        <div class="topbar">
            <button id="update-json" class="button">Opdater liste</button>
            <span class="updated-time">Sidst opdateret: <strong>' . date_i18n("Y-m-d H:i", filemtime($json_file)) . '</strong></span>
        </div>
        <input type="text" id="searchInput" placeholder="Søg produkt..." />
        <table id="productTable" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Navn</th>
                    <th>GTIN</th>
                    <th>Placering</th>
                    <th>Expiry date</th>
                    <th>Antal</th>
                    <th>Gem</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>';

    wp_enqueue_script('loevegaarden-placeringer-js', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], null, true);
    wp_localize_script('loevegaarden-placeringer-js', 'loevegaardenPlaceringerData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'jsonUrl' => $json_url
    ]);
    wp_enqueue_style('loevegaarden-placeringer-css', plugin_dir_url(__FILE__) . 'style.css');
}

add_action('wp_ajax_loevegaarden_generate_json', function () {
    $args = [
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'meta_query' => [
            [
                'key' => '_gtin',
                'compare' => 'EXISTS'
            ]
        ]
    ];
    $query = new WP_Query($args);
    $data = [];

    foreach ($query->posts as $post) {
        $id = $post->ID;
        $gtin = get_post_meta($id, '_gtin', true);
        $placering = get_post_meta($id, '_loevegaarden_placering', true);
        $data[] = [
            'id' => $id,
            'title' => get_the_title($id),
            'gtin' => $gtin,
            'placering' => $placering
        ];
    }

    $file_path = plugin_dir_path(__FILE__) . 'loevegaarden-placeringer.json';
    file_put_contents($file_path, json_encode($data));
    wp_send_json_success(['timestamp' => current_time('mysql')]);
});

add_action('wp_ajax_loevegaarden_save_expiry_data', function () {
    $product_id = intval($_POST['product_id'] ?? 0);
    $expiry = sanitize_text_field($_POST['expiry_date'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);

    if ($product_id && $expiry && $quantity >= 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'webis_pbet';
        $wpdb->insert($table, [
            'post_id' => $product_id,
            'expiry_date' => $expiry,
            'quantity' => $quantity,
            'created_at' => current_time('mysql')
        ]);
        wp_send_json_success();
    }
    wp_send_json_error();
});