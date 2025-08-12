<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function lgpp_render_page() {
	$paths = lgpp_get_json_paths();
	$upd   = file_exists( $paths['path'] ) ? lgpp_get_local_time( filemtime( $paths['path'] ) ) : __( 'Ikke genereret', 'loevegaarden' );
	?>
	<div class="wrap" id="loevegaarden-placeringer-wrapper">
	  <h1><?php esc_html_e( 'Produktplaceringer', 'loevegaarden' ); ?></h1>
	  <div class="lgpp-controls">
		<input type="search" id="searchInput" placeholder="<?php esc_attr_e( 'Indtast mindst 4 tegn…', 'loevegaarden' ); ?>" />
		<input type="search" id="placementScan" class="hidden" placeholder="<?php esc_attr_e( 'Scan placering…', 'loevegaarden' ); ?>" />
		<span id="placement-status" class="hidden"><?php esc_html_e('Valgt:','loevegaarden'); ?> <strong id="placement-product-title"></strong></span>
		<div class="lgpp-controls-right">
		  <label class="lgpp-placements-only"><input type="checkbox" id="placements-only"> Registrer kun placeringer</label>
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
		<tr><td colspan="10" style="text-align:center"><?php esc_html_e( 'Indtast mindst 4 tegn for at søge', 'loevegaarden' ); ?></td></tr>
	  </tbody></table>
	</div>
	<?php
}