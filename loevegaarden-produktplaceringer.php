<?php
/*
Plugin Name: Løvegården Produktplaceringer
Description: Viser en søgbar tabel over produktplaceringer baseret på GTIN, titel og attributten "placering". Giver mulighed for at tilføje "Bedst før" dato og antal via interface.
Version: 1.7
Author: Løvegården
GitHub Plugin URI: https://github.com/loevegaarden/loevegaarden-produktplaceringer
*/

if (!defined('ABSPATH')) exit;

// Indlæs admin-menu
add_action('admin_menu', 'loevegaarden_add_menu', 20);
function loevegaarden_add_menu() {
    add_submenu_page(
        'edit.php?post_type=product',
        'Produktplaceringer',
        'Produktplaceringer',
        'manage_options',
        'loevegaarden_placeringer',
        'loevegaarden_placeringer_page'
    );
}

// Tilføj scripts og styles til plugin-siden
add_action('admin_enqueue_scripts', function () {
    if (isset($_GET['page']) && $_GET['page'] === 'loevegaarden_placeringer') {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('loevegaarden-placeringer', plugin_dir_url(__FILE__) . 'script.js', ['jquery', 'jquery-ui-datepicker'], false, true);
        wp_localize_script('loevegaarden-placeringer', 'loevegaardenPlaceringerData', [
            'jsonUrl' => plugin_dir_url(__FILE__) . 'produktplaceringer.json',
            'ajaxUrl' => admin_url('admin-ajax.php')
        ]);
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_enqueue_style('loevegaarden-style', plugin_dir_url(__FILE__) . 'style.css');
    }
});

// Vis plugin-siden
function loevegaarden_placeringer_page() {
    $json_file = plugin_dir_path(__FILE__) . 'produktplaceringer.json';
    $timestamp = file_exists($json_file) ? filemtime($json_file) : false;
    $last_updated = $timestamp ? date_i18n("Y-m-d H:i", $timestamp, true) : 'Aldrig';
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
                        <th>Bedst før</th>
                        <th>Antal</th>
                        <th>Gem</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>';
}

// AJAX: Opdater JSON
add_action('wp_ajax_loevegaarden_generate_json', function () {
    if (!current_user_can('manage_options')) wp_die();
    loevegaarden_generate_json();
    wp_send_json_success(['message' => 'JSON opdateret', 'timestamp' => current_time('mysql')]);
});

// AJAX: Gem bedst før data
add_action('wp_ajax_loevegaarden_save_expiry_data', function () {
    if (!current_user_can('manage_options')) wp_die();

    $post_id = intval($_POST['product_id'] ?? 0);
    $expiry_date = sanitize_text_field($_POST['expiry_date'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);

    global $wpdb;
    $table = $wpdb->prefix . 'webis_pbet';

    if ($post_id > 0 && $expiry_date && $quantity > 0) {
        // Sæt Enable Expiry Tracking til yes
        update_post_meta($post_id, 'wpbet_product_tracking', 'yes');

        // Indsæt ny række i batch/expiry-tabellen
        $wpdb->insert($table, [
            'post_id' => $post_id,
            'expiry_date' => $expiry_date,
            'quantity' => $quantity,
        ]);
        wp_send_json_success(['message' => '✔️ Gemt', 'product_id' => $post_id]);
    } else {
        wp_send_json_error(['message' => 'Ugyldige data']);
    }
});


// Cron-job setup ved aktivering
register_activation_hook(__FILE__, function () {
    loevegaarden_generate_json();
    if (!wp_next_scheduled('loevegaarden_daily_json')) {
        wp_schedule_event(time(), 'daily', 'loevegaarden_daily_json');
    }
});

// Fjern cron ved deaktivering
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('loevegaarden_daily_json');
});

add_action('loevegaarden_daily_json', 'loevegaarden_generate_json');

// Generer produktplaceringer JSON
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
