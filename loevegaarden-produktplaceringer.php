<?php
/**
 * Plugin Name: Løvegården Produktplaceringer
 * Description: Viser en søgbar tabel over produktplaceringer; mulighed for at rette placering samt registrere antal og bedst-før-dato med eller uden batchtracking.
 * Version: 1.18
 * Author: Løvegården
 */

if (! defined('ABSPATH')) exit;
global $wpdb;

function lgpp_get_json_paths() {
    $u = wp_upload_dir();
    $d = trailingslashit($u['basedir']) . 'loevegaarden-placeringer';
    $url = trailingslashit($u['baseurl']) . 'loevegaarden-placeringer';
    if (!file_exists($d)) wp_mkdir_p($d);
    return ['path'=>"{$d}/produktplaceringer.json", 'url'=>"{$url}/produktplaceringer.json"];
}

function lgpp_get_local_time($ts) {
    $tzs = get_option('timezone_string');
    $tz = $tzs ? new DateTimeZone($tzs) : new DateTimeZone('UTC');
    $dt = new DateTime("@{$ts}"); $dt->setTimezone($tz);
    return $dt->format(get_option('date_format').' '.get_option('time_format')); 
}

add_action('admin_menu', function() {
    add_submenu_page('edit.php?post_type=product','Produktplaceringer','Produktplaceringer','manage_woocommerce','loevegaarden_placeringer','lgpp_render_page');
});

function lgpp_render_page() {
    $paths=lgpp_get_json_paths();
    $upd = file_exists($paths['path'])? lgpp_get_local_time(filemtime($paths['path'])) : __('Ikke genereret','loevegaarden');
    ?>
    <div class="wrap" id="loevegaarden-placeringer-wrapper">
      <h1><?php esc_html_e('Produktplaceringer','loevegaarden'); ?></h1>
      <div class="lgpp-controls">
        <input type="search" id="searchInput" placeholder="<?php esc_attr_e('Indtast mindst 3 tegn…','loevegaarden'); ?>" />
        <div class="lgpp-controls-right">
          <span id="json-updated-at"><?php echo esc_html($upd); ?></span>
          <button id="update-json" class="button"><?php esc_html_e('Opdater liste','loevegaarden'); ?></button>
        </div>
      </div>
      <div id="lgpp-overlay" class="hidden"><div class="lgpp-spinner"></div><p><?php esc_html_e('Opdaterer…','loevegaarden'); ?></p></div>
      <table id="productTable"><thead><tr>
        <th>ID</th><th><?php esc_html_e('Navn','loevegaarden'); ?></th><th><?php esc_html_e('GTIN','loevegaarden'); ?></th>
        <th><?php esc_html_e('Placering','loevegaarden'); ?></th><th><?php esc_html_e('Anvend bedst før','loevegaarden'); ?></th>
        <th><?php esc_html_e('Aktuel lagertal','loevegaarden'); ?></th><th><?php esc_html_e('Antal i åbne ordrer','loevegaarden'); ?></th>
        <th><?php esc_html_e('Antal','loevegaarden'); ?></th><th><?php esc_html_e('Bedst før','loevegaarden'); ?></th><th><?php esc_html_e('Gem','loevegaarden'); ?></th>
      </tr></thead><tbody>
        <tr><td colspan="10" style="text-align:center"><?php esc_html_e('Indtast mindst 3 tegn for at søge','loevegaarden'); ?></td></tr>
      </tbody></table>
    </div>
    <?php
}

add_action('wp_ajax_loevegaarden_generate_json','lgpp_json');
function lgpp_json(){ global $wpdb; $paths=lgpp_get_json_paths();
    $ids=$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'");
    if(empty($ids)){ file_put_contents($paths['path'],wp_json_encode([])); wp_send_json_success(); }
    $in=implode(',',array_map('intval',$ids));
    // postmeta
    $meta_rows=$wpdb->get_results("SELECT post_id,meta_key,meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) AND meta_key IN ('_global_unique_id','wpbet_product_tracking','_stock')");
    $meta=[]; foreach($meta_rows as $r) $meta[$r->post_id][$r->meta_key]=$r->meta_value;
    // terms
    $rel=$wpdb->get_results("SELECT tr.object_id,tt.term_taxonomy_id FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id=tt.term_taxonomy_id WHERE tt.taxonomy='pa_placering' AND tr.object_id IN ({$in})");
    $ids_tt=wp_list_pluck($rel,'term_taxonomy_id'); $names=[];
    if($ids_tt){ $tt=implode(',',array_map('intval',$ids_tt));
        $terms=$wpdb->get_results("SELECT tt.term_taxonomy_id,t.name FROM {$wpdb->term_taxonomy} tt JOIN {$wpdb->terms} t ON tt.term_id=t.term_id WHERE tt.term_taxonomy_id IN ({$tt})");
        foreach($terms as $t) $names[$t->term_taxonomy_id]=$t->name;
    }
    $place=[]; foreach($rel as $r) $place[$r->object_id]=$names[$r->term_taxonomy_id]??'';
    // batches
    $tb=$wpdb->prefix.'webis_pbet';
    $br=$wpdb->get_results("SELECT post_id,SUM(quantity) sum_qty,GROUP_CONCAT(CONCAT(quantity,'@',expiry_date)) batches FROM {$tb} WHERE post_id IN ({$in}) GROUP BY post_id");
    $batches=[]; $sum=[]; foreach($br as $r){ $sum[$r->post_id]=(int)$r->sum_qty; $batches[$r->post_id]=explode(',',$r->batches);}    
    // open orders
    $or=$wpdb->get_results("SELECT pm_pid.meta_value pid,pm_qty.meta_value+0 qty FROM {$wpdb->posts} o JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.ID=oi.order_id JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_pid ON oi.order_item_id=pm_pid.order_item_id AND pm_pid.meta_key='_product_id' JOIN {$wpdb->prefix}woocommerce_order_itemmeta pm_qty ON oi.order_item_id=pm_qty.order_item_id AND pm_qty.meta_key='_qty' WHERE o.post_type='shop_order' AND o.post_status='wc-processing' AND pm_pid.meta_value IN ({$in})");
    $open=[]; foreach($or as $r){ $pid=intval($r->pid); $open[$pid]=($open[$pid]??0)+intval($r->qty);}    
    $data=[]; foreach($ids as $id){ $e=($meta[$id]['wpbet_product_tracking']??'')==='yes'; $cur=$e?($batches[$id]??[]):intval($meta[$id]['_stock']??0);
        $data[]=['id'=>$id,'title'=>get_the_title($id),'gtin'=>$meta[$id]['_global_unique_id']??'','placering'=>$place[$id]??'','expiry_enabled'=>$e,'current_stock'=>$cur,'open_orders'=>$open[$id]??0]; }
    file_put_contents($paths['path'],wp_json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)); wp_send_json_success();
}

add_action('wp_ajax_loevegaarden_save_position_data','lgpp_save');
function lgpp_save(){ check_admin_referer('lgpp_save','nonce'); if(!current_user_can('manage_woocommerce')) wp_send_json_error(); global $wpdb; $webis=$wpdb->prefix.'webis_pbet';
    $id=intval($_POST['post_id']); $qty=intval($_POST['quantity']); $date=sanitize_text_field($_POST['date']); $place=sanitize_text_field($_POST['placement']);
    // Brug user-input til enabled
    $enabled = (sanitize_text_field($_POST['expiry_enabled'])==='yes');
    update_post_meta($id,'wpbet_product_tracking',$enabled?'yes':'no');
    if($enabled) update_post_meta($id,'wpbet-product-tracking-mode','expiry_only');
    if($place) wp_set_object_terms($id,$place,'pa_placering',false);
    if($qty<=0||empty($date)) wp_send_json_success();
    if($enabled){ $wpdb->insert($webis,['post_id'=>$id,'quantity'=>$qty,'expiry_date'=>$date],['%d','%d','%s']); $s=(int)$wpdb->get_var($wpdb->prepare("SELECT SUM(quantity) FROM {$webis} WHERE post_id=%d",$id)); update_post_meta($id,'_stock',$s);
    } else { $s=intval(get_post_meta($id,'_stock',true)); update_post_meta($id,'_stock',$s+$qty);} wp_send_json_success(); }

add_action('init',function(){ if(!wp_next_scheduled('lgpp_daily')) wp_schedule_event(time(),'daily','lgpp_daily'); add_action('lgpp_daily','lgpp_json'); });
add_action('admin_enqueue_scripts',function($hook){ if($hook!=='product_page_loevegaarden_placeringer')return; $paths=lgpp_get_json_paths(); wp_enqueue_script('lgpp-js',plugin_dir_url(__FILE__).'script.js',['jquery'],null,true); wp_localize_script('lgpp-js','lgppData',['jsonUrl'=>esc_url($paths['url']),'ajaxUrl'=>admin_url('admin-ajax.php'),'nonceGen'=>wp_create_nonce('lgpp_gen'),'nonceSave'=>wp_create_nonce('lgpp_save'),'updated_at'=>file_exists($paths['path'])?lgpp_get_local_time(filemtime($paths['path'])):'','placements'=>wp_list_pluck(get_terms(['taxonomy'=>'pa_placering','hide_empty'=>false]),'name')]); wp_enqueue_style('lgpp-css',plugin_dir_url(__FILE__).'style.css'); });