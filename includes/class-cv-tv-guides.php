<?php
/**
 * Guide TV — CPT cv_tv_guide con editor visivo a blocchi.
 * Una "guida" è una pagina light-mode full-screen mostrabile sul player TV
 * quando un ospite preme un pulsante di tipo "guide".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Guides {

	const PT = 'cv_tv_guide';

	/* ── Init ─────────────────────────────────────────────── */

	public static function init() {
		add_action( 'init',                             array( __CLASS__, 'register_post_type' ) );
		add_filter( 'use_block_editor_for_post_type',   array( __CLASS__, 'disable_gutenberg' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::PT,       array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::PT,            array( __CLASS__, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts',            array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . self::PT . '_posts_columns',       array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::PT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		register_post_type( self::PT, array(
			'labels'          => array(
				'name'          => __( 'Guide TV', 'casa-volterra-tv-launcher' ),
				'singular_name' => __( 'Guida TV', 'casa-volterra-tv-launcher' ),
				'add_new'       => __( 'Nuova guida', 'casa-volterra-tv-launcher' ),
				'add_new_item'  => __( 'Nuova guida TV', 'casa-volterra-tv-launcher' ),
				'edit_item'     => __( 'Modifica guida TV', 'casa-volterra-tv-launcher' ),
				'not_found'     => __( 'Nessuna guida trovata', 'casa-volterra-tv-launcher' ),
				'menu_name'     => __( 'Guide TV', 'casa-volterra-tv-launcher' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=cv_tv_screen',
			'menu_position'   => 60,
			'menu_icon'       => 'dashicons-book-alt',
			'supports'        => array( 'title' ),
			'show_in_rest'    => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
	}

	public static function disable_gutenberg( $use, $pt ) {
		return self::PT === $pt ? false : $use;
	}

	public static function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::PT !== $screen->post_type ) return;
		wp_enqueue_media();
		wp_enqueue_script(
			'cv-tv-admin-guides',
			CV_TV_URL . 'assets/admin-guides.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			CV_TV_VERSION, true
		);
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/* ── Meta boxes ───────────────────────────────────────── */

	public static function register_meta_boxes() {
		add_meta_box( 'cv_tv_guide_design', __( '🎨 Design della pagina', 'casa-volterra-tv-launcher' ),
			array( __CLASS__, 'render_design' ), self::PT, 'normal', 'high' );
		add_meta_box( 'cv_tv_guide_blocks', __( '📄 Contenuto — Blocchi', 'casa-volterra-tv-launcher' ),
			array( __CLASS__, 'render_blocks' ), self::PT, 'normal', 'default' );
		add_meta_box( 'cv_tv_guide_preview', __( '👁 Anteprima TV', 'casa-volterra-tv-launcher' ),
			array( __CLASS__, 'render_preview' ), self::PT, 'side', 'default' );
	}

	/* ── Design panel ─────────────────────────────────────── */

	public static function render_design( $post ) {
		wp_nonce_field( 'cv_tv_save_guide_' . $post->ID, 'cv_tv_guide_nonce' );
		$bg_color    = get_post_meta( $post->ID, '_cv_tg_bg_color',    true ) ?: '#ffffff';
		$accent      = get_post_meta( $post->ID, '_cv_tg_accent',      true ) ?: '#1a56a0';
		$text_color  = get_post_meta( $post->ID, '_cv_tg_text_color',  true ) ?: '#1a1a2e';
		$bg_image    = get_post_meta( $post->ID, '_cv_tg_bg_image',    true );
		$layout      = get_post_meta( $post->ID, '_cv_tg_layout',      true ) ?: 'centered';
		$show_back   = get_post_meta( $post->ID, '_cv_tg_show_back',   true );
		$show_back   = '' === $show_back ? '1' : $show_back;
		$back_label  = get_post_meta( $post->ID, '_cv_tg_back_label',  true ) ?: '← Torna alla home';
		?>
		<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;align-items:start;">

			<div>
				<h4 style="margin-top:0"><?php esc_html_e( 'Colori', 'casa-volterra-tv-launcher' ); ?></h4>
				<table class="form-table" style="margin:0" role="presentation">
					<tr>
						<th><label for="cv_tg_bg_color"><?php esc_html_e( 'Sfondo', 'casa-volterra-tv-launcher' ); ?></label></th>
						<td><input id="cv_tg_bg_color" type="text" name="cv_tg_bg_color" value="<?php echo esc_attr( $bg_color ); ?>" class="cv-color-picker" /></td>
					</tr>
					<tr>
						<th><label for="cv_tg_text_color"><?php esc_html_e( 'Testo', 'casa-volterra-tv-launcher' ); ?></label></th>
						<td><input id="cv_tg_text_color" type="text" name="cv_tg_text_color" value="<?php echo esc_attr( $text_color ); ?>" class="cv-color-picker" /></td>
					</tr>
					<tr>
						<th><label for="cv_tg_accent"><?php esc_html_e( 'Accento', 'casa-volterra-tv-launcher' ); ?></label></th>
						<td><input id="cv_tg_accent" type="text" name="cv_tg_accent" value="<?php echo esc_attr( $accent ); ?>" class="cv-color-picker" /></td>
					</tr>
				</table>
			</div>

			<div>
				<h4 style="margin-top:0"><?php esc_html_e( 'Layout', 'casa-volterra-tv-launcher' ); ?></h4>
				<?php $layouts = array(
					'centered'    => '⬛ Centrato (colonna unica)',
					'two-column'  => '▦ Due colonne',
					'hero-text'   => '🖼 Immagine + testo',
					'magazine'    => '📰 Magazine (griglia)',
				); ?>
				<select name="cv_tg_layout" style="width:100%">
					<?php foreach ( $layouts as $v => $l ) : ?>
						<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $layout, $v ); ?>><?php echo esc_html( $l ); ?></option>
					<?php endforeach; ?>
				</select>

				<p style="margin-top:16px"><label>
					<input type="checkbox" name="cv_tg_show_back" value="1" <?php checked( '1', $show_back ); ?> />
					<?php esc_html_e( 'Mostra pulsante "Torna indietro"', 'casa-volterra-tv-launcher' ); ?>
				</label></p>
				<input type="text" name="cv_tg_back_label" value="<?php echo esc_attr( $back_label ); ?>" class="widefat" placeholder="← Torna alla home" />
			</div>

			<div>
				<h4 style="margin-top:0"><?php esc_html_e( 'Immagine di sfondo (opzionale)', 'casa-volterra-tv-launcher' ); ?></h4>
				<input type="url" name="cv_tg_bg_image" id="cv_tg_bg_image" value="<?php echo esc_attr( $bg_image ); ?>" class="widefat" placeholder="https://..." />
				<button type="button" class="button cv-tv-media-btn" style="margin-top:6px" data-target="cv_tg_bg_image">
					<?php esc_html_e( 'Scegli dalla Libreria', 'casa-volterra-tv-launcher' ); ?>
				</button>
				<p class="description"><?php esc_html_e( 'Se impostata, la sovrappone allo sfondo colorato con opacità ridotta.', 'casa-volterra-tv-launcher' ); ?></p>
			</div>

		</div>
		<?php
	}

	/* ── Blocks panel ─────────────────────────────────────── */

	public static function render_blocks( $post ) {
		$blocks = get_post_meta( $post->ID, '_cv_tg_blocks', true );
		if ( ! is_array( $blocks ) ) $blocks = array();
		$block_types = array(
			'heading'    => '📌 Titolo sezione',
			'text'       => '📝 Testo libero',
			'icon_text'  => '✨ Icona + Testo',
			'two_col'    => '▦ Due colonne di testo',
			'image_text' => '🖼 Immagine + Testo',
			'info_box'   => '💡 Riquadro informativo',
			'list'       => '📋 Lista puntata',
			'divider'    => '─── Separatore',
			'qr'         => '📱 QR Code + Link',
			'cta_button' => '🔘 Pulsante CTA',
		);
		?>
		<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
			<?php foreach ( $block_types as $type => $label ) : ?>
				<button type="button" class="button cv-tg-add-block" data-type="<?php echo esc_attr( $type ); ?>">
					+ <?php echo esc_html( $label ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<div id="cv-tg-blocks-list" style="min-height:60px">
			<?php foreach ( $blocks as $i => $block ) : ?>
				<?php self::render_block_row( $i, $block ); ?>
			<?php endforeach; ?>
		</div>

		<input type="hidden" id="cv_tg_blocks_json" name="cv_tg_blocks_json" value="<?php echo esc_attr( wp_json_encode( $blocks ) ); ?>" />

		<!-- Block templates (hidden, cloned by JS) -->
		<div id="cv-tg-block-templates" style="display:none">
			<?php foreach ( array_keys( $block_types ) as $type ) : ?>
				<div data-tpl="<?php echo esc_attr( $type ); ?>">
					<?php self::render_block_row( '__IDX__', array( 'type' => $type ) ); ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function render_block_row( $i, $block ) {
		$type    = $block['type'] ?? 'text';
		$labels  = array(
			'heading'    => '📌 Titolo sezione',
			'text'       => '📝 Testo libero',
			'icon_text'  => '✨ Icona + Testo',
			'two_col'    => '▦ Due colonne',
			'image_text' => '🖼 Immagine + Testo',
			'info_box'   => '💡 Riquadro informativo',
			'list'       => '📋 Lista',
			'divider'    => '─── Separatore',
			'qr'         => '📱 QR Code',
			'cta_button' => '🔘 Pulsante CTA',
		);
		$label   = $labels[ $type ] ?? $type;
		$pfx     = "cv_tg_blocks[{$i}]";
		$style_card = 'background:#f8f9fa;border:1px solid #ddd;border-radius:8px;padding:14px 16px;margin-bottom:10px;position:relative';
		?>
		<div class="cv-tg-block-row" data-type="<?php echo esc_attr( $type ); ?>" style="<?php echo esc_attr( $style_card ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $pfx ); ?>[type]" value="<?php echo esc_attr( $type ); ?>" />

			<!-- Header bar -->
			<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;cursor:move" class="cv-tg-drag-handle">
				<span style="color:#999;font-size:1.2em">⠿</span>
				<strong style="flex:1"><?php echo esc_html( $label ); ?></strong>
				<button type="button" class="button button-small cv-tg-block-toggle">▼</button>
				<button type="button" class="button button-small cv-tg-block-remove" style="color:#b00">✕</button>
			</div>

			<!-- Fields (per tipo) -->
			<div class="cv-tg-block-fields">
			<?php
			switch ( $type ) {

				case 'heading':
					?>
					<table class="form-table" style="margin:0" role="presentation">
						<tr>
							<th><label><?php esc_html_e( 'Testo titolo', 'casa-volterra-tv-launcher' ); ?></label></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[heading]" value="<?php echo esc_attr( $block['heading'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'es. 🏠 La vostra casa', 'casa-volterra-tv-launcher' ); ?>" /></td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Sottotitolo', 'casa-volterra-tv-launcher' ); ?></label></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[subtitle]" value="<?php echo esc_attr( $block['subtitle'] ?? '' ); ?>" class="widefat" /></td>
						</tr>
					</table>
					<?php break;

				case 'text':
					?>
					<textarea name="<?php echo esc_attr( $pfx ); ?>[text]" rows="4" class="widefat" placeholder="<?php esc_attr_e( 'Inserisci il testo del paragrafo...', 'casa-volterra-tv-launcher' ); ?>"><?php echo esc_textarea( $block['text'] ?? '' ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Usa **testo** per grassetto, _testo_ per corsivo.', 'casa-volterra-tv-launcher' ); ?></p>
					<?php break;

				case 'icon_text':
					?>
					<table class="form-table" style="margin:0" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Icona (emoji)', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[icon]" value="<?php echo esc_attr( $block['icon'] ?? '' ); ?>" style="width:60px;font-size:1.5em;text-align:center" placeholder="🏠" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Titolo', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[heading]" value="<?php echo esc_attr( $block['heading'] ?? '' ); ?>" class="widefat" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Testo', 'casa-volterra-tv-launcher' ); ?></th>
							<td><textarea name="<?php echo esc_attr( $pfx ); ?>[text]" rows="3" class="widefat"><?php echo esc_textarea( $block['text'] ?? '' ); ?></textarea></td>
						</tr>
					</table>
					<?php break;

				case 'two_col':
					?>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
						<div>
							<label><strong><?php esc_html_e( 'Colonna sinistra', 'casa-volterra-tv-launcher' ); ?></strong></label>
							<input type="text" name="<?php echo esc_attr( $pfx ); ?>[col1_heading]" value="<?php echo esc_attr( $block['col1_heading'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Titolo', 'casa-volterra-tv-launcher' ); ?>" style="margin:4px 0" />
							<textarea name="<?php echo esc_attr( $pfx ); ?>[col1_text]" rows="4" class="widefat" placeholder="<?php esc_attr_e( 'Testo...', 'casa-volterra-tv-launcher' ); ?>"><?php echo esc_textarea( $block['col1_text'] ?? '' ); ?></textarea>
						</div>
						<div>
							<label><strong><?php esc_html_e( 'Colonna destra', 'casa-volterra-tv-launcher' ); ?></strong></label>
							<input type="text" name="<?php echo esc_attr( $pfx ); ?>[col2_heading]" value="<?php echo esc_attr( $block['col2_heading'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Titolo', 'casa-volterra-tv-launcher' ); ?>" style="margin:4px 0" />
							<textarea name="<?php echo esc_attr( $pfx ); ?>[col2_text]" rows="4" class="widefat" placeholder="<?php esc_attr_e( 'Testo...', 'casa-volterra-tv-launcher' ); ?>"><?php echo esc_textarea( $block['col2_text'] ?? '' ); ?></textarea>
						</div>
					</div>
					<?php break;

				case 'image_text':
					?>
					<table class="form-table" style="margin:0" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Immagine URL', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<input type="url" name="<?php echo esc_attr( $pfx ); ?>[image]" value="<?php echo esc_attr( $block['image'] ?? '' ); ?>" class="widefat" placeholder="https://..." />
								<button type="button" class="button cv-tv-media-btn" data-target-input="cv_tg_img_<?php echo esc_attr( (string) $i ); ?>" style="margin-top:4px"><?php esc_html_e( 'Scegli', 'casa-volterra-tv-launcher' ); ?></button>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Posizione immagine', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( $pfx ); ?>[img_side]">
									<option value="left"  <?php selected( $block['img_side'] ?? 'left', 'left' ); ?>><?php esc_html_e( 'Sinistra', 'casa-volterra-tv-launcher' ); ?></option>
									<option value="right" <?php selected( $block['img_side'] ?? 'left', 'right' ); ?>><?php esc_html_e( 'Destra', 'casa-volterra-tv-launcher' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Titolo', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[heading]" value="<?php echo esc_attr( $block['heading'] ?? '' ); ?>" class="widefat" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Testo', 'casa-volterra-tv-launcher' ); ?></th>
							<td><textarea name="<?php echo esc_attr( $pfx ); ?>[text]" rows="4" class="widefat"><?php echo esc_textarea( $block['text'] ?? '' ); ?></textarea></td>
						</tr>
					</table>
					<?php break;

				case 'info_box':
					?>
					<table class="form-table" style="margin:0" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Stile', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( $pfx ); ?>[style]">
									<option value="info"    <?php selected( $block['style'] ?? 'info',    'info' ); ?>>💡 Info (blu)</option>
									<option value="success" <?php selected( $block['style'] ?? 'info', 'success' ); ?>>✅ Successo (verde)</option>
									<option value="warning" <?php selected( $block['style'] ?? 'info', 'warning' ); ?>>⚠️ Attenzione (arancio)</option>
									<option value="tip"     <?php selected( $block['style'] ?? 'info',     'tip' ); ?>>🌟 Tip (dorato)</option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Icona', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[icon]" value="<?php echo esc_attr( $block['icon'] ?? '' ); ?>" style="width:60px;font-size:1.4em;text-align:center" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Testo', 'casa-volterra-tv-launcher' ); ?></th>
							<td><textarea name="<?php echo esc_attr( $pfx ); ?>[text]" rows="3" class="widefat"><?php echo esc_textarea( $block['text'] ?? '' ); ?></textarea></td>
						</tr>
					</table>
					<?php break;

				case 'list':
					?>
					<table class="form-table" style="margin:0" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Titolo lista', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[heading]" value="<?php echo esc_attr( $block['heading'] ?? '' ); ?>" class="widefat" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Voci (una per riga)', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<textarea name="<?php echo esc_attr( $pfx ); ?>[items]" rows="6" class="widefat" placeholder="📶 Wi-Fi: CasaVolterra&#10;🔑 Codice: 12345&#10;🕙 Check-out: ore 10:00"><?php echo esc_textarea( $block['items'] ?? '' ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Ogni riga diventa una voce della lista. Puoi iniziare con un emoji.', 'casa-volterra-tv-launcher' ); ?></p>
							</td>
						</tr>
					</table>
					<?php break;

				case 'divider':
					?>
					<p class="description" style="margin:0"><?php esc_html_e( 'Linea separatrice — nessun campo da configurare.', 'casa-volterra-tv-launcher' ); ?></p>
					<?php break;

				case 'qr':
					?>
					<table class="form-table" style="margin:0" role="presentation">
						<tr>
							<th><?php esc_html_e( 'URL', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="url" name="<?php echo esc_attr( $pfx ); ?>[url]" value="<?php echo esc_attr( $block['url'] ?? '' ); ?>" class="widefat" placeholder="https://..." /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Titolo sopra il QR', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[heading]" value="<?php echo esc_attr( $block['heading'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Scansiona per aprire', 'casa-volterra-tv-launcher' ); ?>" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Didascalia', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[label]" value="<?php echo esc_attr( $block['label'] ?? '' ); ?>" class="widefat" /></td>
						</tr>
					</table>
					<?php break;

				case 'cta_button':
					?>
					<table class="form-table" style="margin:0" role="presentation">
						<tr>
							<th><?php esc_html_e( 'Testo pulsante', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="text" name="<?php echo esc_attr( $pfx ); ?>[label]" value="<?php echo esc_attr( $block['label'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Scopri di più →', 'casa-volterra-tv-launcher' ); ?>" /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'URL destinazione', 'casa-volterra-tv-launcher' ); ?></th>
							<td><input type="url" name="<?php echo esc_attr( $pfx ); ?>[url]" value="<?php echo esc_attr( $block['url'] ?? '' ); ?>" class="widefat" placeholder="https://..." /></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Stile', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<select name="<?php echo esc_attr( $pfx ); ?>[style]">
									<option value="primary"   <?php selected( $block['style'] ?? 'primary', 'primary' ); ?>><?php esc_html_e( 'Primario (colorato)', 'casa-volterra-tv-launcher' ); ?></option>
									<option value="secondary" <?php selected( $block['style'] ?? 'primary', 'secondary' ); ?>><?php esc_html_e( 'Secondario (contorno)', 'casa-volterra-tv-launcher' ); ?></option>
									<option value="large"     <?php selected( $block['style'] ?? 'primary', 'large' ); ?>><?php esc_html_e( 'Grande (full-width)', 'casa-volterra-tv-launcher' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
					<?php break;

			} // end switch
			?>
			</div><!-- .cv-tg-block-fields -->
		</div><!-- .cv-tg-block-row -->
		<?php
	}

	/* ── Preview panel ────────────────────────────────────── */

	public static function render_preview( $post ) {
		$guide_url = home_url( '/?cv_tv_guide_preview=' . $post->ID );
		?>
		<p><?php esc_html_e( 'Salva la guida e poi apri l\'anteprima per vedere come appare sulla TV.', 'casa-volterra-tv-launcher' ); ?></p>
		<p>
			<a href="<?php echo esc_url( $guide_url ); ?>" target="_blank" class="button button-primary">
				📺 <?php esc_html_e( 'Apri anteprima TV', 'casa-volterra-tv-launcher' ); ?>
			</a>
		</p>
		<hr>
		<p class="description">
			<strong><?php esc_html_e( 'ID guida:', 'casa-volterra-tv-launcher' ); ?></strong> <code><?php echo (int) $post->ID; ?></code><br>
			<?php esc_html_e( 'Inserisci questo ID nel pulsante della Screen usando il tipo "Guida TV".', 'casa-volterra-tv-launcher' ); ?>
		</p>
		<?php
	}

	/* ── Save meta ────────────────────────────────────────── */

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['cv_tv_guide_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_tv_guide_nonce'] ) ), 'cv_tv_save_guide_' . $post_id ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Design fields
		$design_fields = array(
			'cv_tg_bg_color'   => '_cv_tg_bg_color',
			'cv_tg_accent'     => '_cv_tg_accent',
			'cv_tg_text_color' => '_cv_tg_text_color',
			'cv_tg_bg_image'   => '_cv_tg_bg_image',
			'cv_tg_layout'     => '_cv_tg_layout',
			'cv_tg_back_label' => '_cv_tg_back_label',
		);
		foreach ( $design_fields as $field => $meta ) {
			$val = sanitize_text_field( wp_unslash( (string) ( $_POST[ $field ] ?? '' ) ) );
			update_post_meta( $post_id, $meta, $val );
		}
		update_post_meta( $post_id, '_cv_tg_show_back', ! empty( $_POST['cv_tg_show_back'] ) ? '1' : '0' );

		// Blocks
		$raw_blocks = isset( $_POST['cv_tg_blocks'] ) && is_array( $_POST['cv_tg_blocks'] )
			? $_POST['cv_tg_blocks'] : array();

		$valid_types = array( 'heading','text','icon_text','two_col','image_text','info_box','list','divider','qr','cta_button' );
		$blocks = array();
		foreach ( $raw_blocks as $raw ) {
			if ( ! is_array( $raw ) ) continue;
			$type = sanitize_key( $raw['type'] ?? 'text' );
			if ( ! in_array( $type, $valid_types, true ) ) continue;
			$block = array( 'type' => $type );
			$text_keys = array( 'heading','subtitle','text','icon','col1_heading','col1_text','col2_heading','col2_text','label','style','img_side','items' );
			foreach ( $text_keys as $k ) {
				if ( isset( $raw[ $k ] ) ) {
					$block[ $k ] = sanitize_textarea_field( wp_unslash( (string) $raw[ $k ] ) );
				}
			}
			if ( isset( $raw['url'] ) )   $block['url']   = esc_url_raw( wp_unslash( (string) $raw['url'] ) );
			if ( isset( $raw['image'] ) ) $block['image'] = esc_url_raw( wp_unslash( (string) $raw['image'] ) );
			$blocks[] = $block;
		}
		update_post_meta( $post_id, '_cv_tg_blocks', $blocks );
	}

	/* ── Admin columns ────────────────────────────────────── */

	public static function columns( $cols ) {
		$cols['cv_tg_blocks'] = __( 'Blocchi', 'casa-volterra-tv-launcher' );
		$cols['cv_tg_id']     = 'ID';
		return $cols;
	}

	public static function column_content( $col, $post_id ) {
		if ( 'cv_tg_blocks' === $col ) {
			$blocks = get_post_meta( $post_id, '_cv_tg_blocks', true );
			echo esc_html( is_array( $blocks ) ? count( $blocks ) . ' blocchi' : '0 blocchi' );
		}
		if ( 'cv_tg_id' === $col ) {
			echo '<code>' . (int) $post_id . '</code>';
		}
	}

	/* ── Data helper ──────────────────────────────────────── */

	public static function get_guide_data( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || self::PT !== $post->post_type ) return null;
		return array(
			'id'         => $post_id,
			'title'      => get_the_title( $post_id ),
			'bg_color'   => get_post_meta( $post_id, '_cv_tg_bg_color',   true ) ?: '#ffffff',
			'accent'     => get_post_meta( $post_id, '_cv_tg_accent',     true ) ?: '#1a56a0',
			'text_color' => get_post_meta( $post_id, '_cv_tg_text_color', true ) ?: '#1a1a2e',
			'bg_image'   => get_post_meta( $post_id, '_cv_tg_bg_image',   true ),
			'layout'     => get_post_meta( $post_id, '_cv_tg_layout',     true ) ?: 'centered',
			'show_back'  => '1' === get_post_meta( $post_id, '_cv_tg_show_back', true ),
			'back_label' => get_post_meta( $post_id, '_cv_tg_back_label', true ) ?: '← Torna alla home',
			'blocks'     => get_post_meta( $post_id, '_cv_tg_blocks',     true ) ?: array(),
		);
	}
}
