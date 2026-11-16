<?php
/**
 * Screens CPT — ogni schermata è un'unità TV completa con zone configurabili.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Screens {

	const PT = 'cv_tv_screen';

	public static function init() {
		add_action( 'init',        array( __CLASS__, 'register_post_type' ) );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'disable_gutenberg' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::PT, array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::PT,    array( __CLASS__, 'save_meta' ) );
		add_action( 'admin_enqueue_scripts',    array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'manage_' . self::PT . '_posts_columns',       array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::PT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'TV Screens', 'casa-volterra-tv-launcher' ),
			'singular_name'      => __( 'TV Screen', 'casa-volterra-tv-launcher' ),
			'add_new'            => __( 'Aggiungi schermata', 'casa-volterra-tv-launcher' ),
			'add_new_item'       => __( 'Nuova schermata TV', 'casa-volterra-tv-launcher' ),
			'edit_item'          => __( 'Modifica schermata TV', 'casa-volterra-tv-launcher' ),
			'not_found'          => __( 'Nessuna schermata trovata', 'casa-volterra-tv-launcher' ),
			'not_found_in_trash' => __( 'Nessuna schermata nel cestino', 'casa-volterra-tv-launcher' ),
			'menu_name'          => __( 'Casa Volterra TV', 'casa-volterra-tv-launcher' ),
		);
		register_post_type(
			self::PT,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_position'   => 59,
				'menu_icon'       => 'dashicons-desktop',
				'supports'        => array( 'title' ),
				'show_in_rest'    => false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	public static function disable_gutenberg( $use, $post_type ) {
		if ( self::PT === $post_type ) {
			return false;
		}
		return $use;
	}

	public static function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || self::PT !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'cv-tv-admin-screens',
			CV_TV_URL . 'assets/admin-screens.js',
			array( 'jquery' ),
			CV_TV_VERSION,
			true
		);
	}

	/* ── Meta boxes ─────────────────────────────────────────── */

	public static function register_meta_boxes() {
		$boxes = array(
			array( 'cv_tv_mb_hero',    __( '🖼 Hero — Sfondo e overlay', 'casa-volterra-tv-launcher' ),    'render_hero' ),
			array( 'cv_tv_mb_brand',   __( '🏷 Branding — Logo', 'casa-volterra-tv-launcher' ),            'render_brand' ),
			array( 'cv_tv_mb_clock',   __( '🕐 Orologio e meteo', 'casa-volterra-tv-launcher' ),           'render_clock' ),
			array( 'cv_tv_mb_texts',   __( '✏️ Testi — Titolo e sottotitolo', 'casa-volterra-tv-launcher' ), 'render_texts' ),
			array( 'cv_tv_mb_badges',  __( '💠 Badge informativi', 'casa-volterra-tv-launcher' ),           'render_badges' ),
			array( 'cv_tv_mb_buttons', __( '🟦 Pulsanti navigabili (max 8)', 'casa-volterra-tv-launcher' ), 'render_buttons' ),
			array( 'cv_tv_mb_btn_layout', __( 'Layout pulsanti', 'casa-volterra-tv-launcher' ), 'render_btn_layout' ),
			array( 'cv_tv_mb_qr',      __( '📱 QR Code', 'casa-volterra-tv-launcher' ),                    'render_qr' ),
			array( 'cv_tv_mb_ticker',   __( '📰 News / Ticker', 'casa-volterra-tv-launcher' ),              'render_ticker' ),
			array( 'cv_tv_mb_webcams', __( '📷 Webcam Live (carosello)', 'casa-volterra-tv-launcher' ),    'render_webcams' ),
			array( 'cv_tv_mb_footer',  __( '📋 Nota footer schermata', 'casa-volterra-tv-launcher' ),      'render_footer' ),
		);
		foreach ( $boxes as $box ) {
			add_meta_box( $box[0], $box[1], array( __CLASS__, $box[2] ), self::PT, 'normal', 'default' );
		}
	}

	/* ── Meta box renderers ─────────────────────────────────── */

	private static function nonce( $post ) {
		wp_nonce_field( 'cv_tv_save_screen_' . $post->ID, 'cv_tv_screen_nonce' );
	}

	public static function render_hero( $post ) {
		self::nonce( $post );
		$bg_image = get_post_meta( $post->ID, '_cv_tv_bg_image', true );
		$bg_video = get_post_meta( $post->ID, '_cv_tv_bg_video', true );
		$overlay  = get_post_meta( $post->ID, '_cv_tv_overlay', true );
		$overlay  = '' === $overlay ? '55' : $overlay;
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cv_tv_bg_image"><?php esc_html_e( 'Immagine di sfondo', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_bg_image" type="url" class="widefat" name="cv_tv_bg_image" value="<?php echo esc_attr( $bg_image ); ?>" placeholder="https://..." />
					<button type="button" class="button cv-tv-media-btn" style="margin-top:6px;" data-target="cv_tv_bg_image"><?php esc_html_e( 'Scegli dalla Libreria', 'casa-volterra-tv-launcher' ); ?></button>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cv_tv_bg_video"><?php esc_html_e( 'Video MP4 di sfondo', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_bg_video" type="url" class="widefat" name="cv_tv_bg_video" value="<?php echo esc_attr( $bg_video ); ?>" placeholder="https://..." />
					<button type="button" class="button cv-tv-media-btn" style="margin-top:6px;" data-target="cv_tv_bg_video"><?php esc_html_e( 'Scegli dalla Libreria', 'casa-volterra-tv-launcher' ); ?></button>
					<p class="description"><?php esc_html_e( 'Se presente, il video ha priorità sull\'immagine.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cv_tv_overlay"><?php esc_html_e( 'Oscuramento sfondo (%)', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_overlay" type="range" name="cv_tv_overlay" min="0" max="90" step="5" value="<?php echo esc_attr( $overlay ); ?>" oninput="document.getElementById('cv_tv_overlay_val').textContent=this.value+'%'" />
					<span id="cv_tv_overlay_val"><?php echo esc_html( $overlay ); ?>%</span>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function render_brand( $post ) {
		$logo = get_post_meta( $post->ID, '_cv_tv_logo_url', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cv_tv_logo_url"><?php esc_html_e( 'URL logo', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_logo_url" type="url" class="widefat" name="cv_tv_logo_url" value="<?php echo esc_attr( $logo ); ?>" placeholder="https://..." />
					<button type="button" class="button cv-tv-media-btn" style="margin-top:6px;" data-target="cv_tv_logo_url"><?php esc_html_e( 'Scegli dalla Libreria', 'casa-volterra-tv-launcher' ); ?></button>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function render_clock( $post ) {
		$show_clock   = get_post_meta( $post->ID, '_cv_tv_show_clock', true );
		$show_weather = get_post_meta( $post->ID, '_cv_tv_show_weather', true );
		$weather_city = get_post_meta( $post->ID, '_cv_tv_weather_city', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Mostra orologio', 'casa-volterra-tv-launcher' ); ?></th>
				<td><label><input type="checkbox" name="cv_tv_show_clock" value="1" <?php checked( '1', $show_clock ); ?> /> <?php esc_html_e( 'Mostra orologio in alto a destra', 'casa-volterra-tv-launcher' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Mostra meteo', 'casa-volterra-tv-launcher' ); ?></th>
				<td><label><input type="checkbox" name="cv_tv_show_weather" value="1" <?php checked( '1', $show_weather ); ?> /> <?php esc_html_e( 'Mostra meteo (Open-Meteo, gratuito)', 'casa-volterra-tv-launcher' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="cv_tv_weather_city"><?php esc_html_e( 'Città meteo', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_weather_city" type="text" class="regular-text" name="cv_tv_weather_city" value="<?php echo esc_attr( $weather_city ); ?>" placeholder="Volterra" />
					<p class="description"><?php esc_html_e( 'Nome città in inglese (es. Volterra, Rome, Florence).', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function render_texts( $post ) {
		$eyebrow  = get_post_meta( $post->ID, '_cv_tv_eyebrow', true );
		$title    = get_post_meta( $post->ID, '_cv_tv_title', true );
		$subtitle = get_post_meta( $post->ID, '_cv_tv_subtitle', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cv_tv_eyebrow"><?php esc_html_e( 'Testo sopra titolo (eyebrow)', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td><input id="cv_tv_eyebrow" type="text" class="widefat" name="cv_tv_eyebrow" value="<?php echo esc_attr( $eyebrow ); ?>" placeholder="Benvenuti a Volterra" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="cv_tv_title"><?php esc_html_e( 'Titolo principale', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td><input id="cv_tv_title" type="text" class="widefat" name="cv_tv_title" value="<?php echo esc_attr( $title ); ?>" placeholder="Casa Volterra" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="cv_tv_subtitle"><?php esc_html_e( 'Sottotitolo', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td><textarea id="cv_tv_subtitle" class="widefat" rows="3" name="cv_tv_subtitle"><?php echo esc_textarea( $subtitle ); ?></textarea></td>
			</tr>
		</table>
		<p class="description" style="margin-top:10px;">
			<strong>Slug Smoobu disponibili</strong> — vengono sostituiti automaticamente con i dati della prenotazione:<br>
			<code>{nome_ospite}</code> &rarr; nome ospite presente (o in arrivo nella finestra) &nbsp;
			<code>{data_checkin}</code> &rarr; data arrivo &nbsp;
			<code>{ora_checkin}</code> &rarr; ora arrivo &nbsp;
			<code>{data_checkout}</code> &rarr; data partenza &nbsp;
			<code>{ora_checkout}</code> &rarr; ora partenza<br>
			<em>Esempio eyebrow:</em> <code>Benvenuto/a, {nome_ospite}!</code> &nbsp;
			<em>Esempio sottotitolo:</em> <code>Check-in {data_checkin} alle {ora_checkin} &bull; Check-out {data_checkout}</code>
		</p>
		<?php
	}

	public static function render_badges( $post ) {
		$badges = get_post_meta( $post->ID, '_cv_tv_badges', true );
		if ( ! is_array( $badges ) ) {
			$badges = array( '', '', '' );
		}
		$badges = array_pad( $badges, 3, '' );
		?>
		<p><?php esc_html_e( 'Piccole pillole di testo mostrate sotto il titolo.', 'casa-volterra-tv-launcher' ); ?></p>
		<table class="form-table" role="presentation">
			<?php for ( $i = 0; $i < 3; $i++ ) : ?>
			<tr>
				<th scope="row"><label for="cv_tv_badge_<?php echo esc_attr( (string) $i ); ?>"><?php echo esc_html( sprintf( __( 'Badge %d', 'casa-volterra-tv-launcher' ), $i + 1 ) ); ?></label></th>
				<td><input id="cv_tv_badge_<?php echo esc_attr( (string) $i ); ?>" type="text" class="regular-text" name="cv_tv_badges[<?php echo esc_attr( (string) $i ); ?>]" value="<?php echo esc_attr( $badges[ $i ] ); ?>" placeholder="<?php echo esc_attr( array( 'Wi-Fi incluso', 'Self check-in', 'Guida Volterra' )[ $i ] ); ?>" /></td>
			</tr>
			<?php endfor; ?>
		</table>
		<?php
	}

	public static function render_buttons( $post ) {
		$buttons = get_post_meta( $post->ID, '_cv_tv_buttons', true );
		if ( ! is_array( $buttons ) ) {
			$buttons = array();
		}
		for ( $i = count( $buttons ); $i < 8; $i++ ) {
			$buttons[] = array( 'icon' => '', 'label' => '', 'desc' => '', 'type' => 'url', 'target' => '' );
		}
		$types = array(
			'url'           => __( 'URL remota', 'casa-volterra-tv-launcher' ),
			'browser'       => __( '🌐 Browser kiosk (sito esterno + tasto Home)', 'casa-volterra-tv-launcher' ),
			'smoobu_guide'  => __( '📖 Guida Ospite Smoobu (URL automatica)', 'casa-volterra-tv-launcher' ),
			'guide'         => __( 'Guida TV (ID)', 'casa-volterra-tv-launcher' ),
			'ai_chat'       => __( '🤖 AI Assistant (QR chat)', 'casa-volterra-tv-launcher' ),
			'pdf_viewer'    => __( '📄 Visualizzatore PDF', 'casa-volterra-tv-launcher' ),
			'ha'            => __( 'Home Assistant', 'casa-volterra-tv-launcher' ),
			'protocol'      => __( 'Protocollo locale', 'casa-volterra-tv-launcher' ),
		);
		?>
		<table class="widefat striped" role="presentation">
			<thead>
				<tr>
					<th style="width:40px">#</th>
					<th style="width:60px"><?php esc_html_e( 'Icona', 'casa-volterra-tv-launcher' ); ?></th>
					<th><?php esc_html_e( 'Etichetta', 'casa-volterra-tv-launcher' ); ?></th>
					<th><?php esc_html_e( 'Descrizione', 'casa-volterra-tv-launcher' ); ?></th>
					<th style="width:140px"><?php esc_html_e( 'Tipo', 'casa-volterra-tv-launcher' ); ?></th>
					<th><?php esc_html_e( 'Destinazione', 'casa-volterra-tv-launcher' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php for ( $i = 0; $i < 8; $i++ ) :
				$b = $buttons[ $i ];
				?>
				<tr>
					<td><strong><?php echo esc_html( (string) ( $i + 1 ) ); ?></strong></td>
					<td><input type="text" style="width:50px;font-size:1.4em;text-align:center;" name="cv_tv_buttons[<?php echo esc_attr( (string) $i ); ?>][icon]" value="<?php echo esc_attr( $b['icon'] ?? '' ); ?>" placeholder="🎬" /></td>
					<td><input type="text" class="widefat" name="cv_tv_buttons[<?php echo esc_attr( (string) $i ); ?>][label]" value="<?php echo esc_attr( $b['label'] ?? '' ); ?>" /></td>
					<td><input type="text" class="widefat" name="cv_tv_buttons[<?php echo esc_attr( (string) $i ); ?>][desc]" value="<?php echo esc_attr( $b['desc'] ?? '' ); ?>" /></td>
					<td>
						<select name="cv_tv_buttons[<?php echo esc_attr( (string) $i ); ?>][type]">
							<?php foreach ( $types as $val => $lbl ) : ?>
								<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $b['type'] ?? 'url', $val ); ?>><?php echo esc_html( $lbl ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
					<td>
						<?php if ( 'smoobu_guide' === ( $b['type'] ?? '' ) ) : ?>
							<em style="color:#888;font-size:12px;">URL automatica da Smoobu — nessun target necessario</em>
							<input type="hidden" name="cv_tv_buttons[<?php echo esc_attr( (string) $i ); ?>][target]" value="">
						<?php else : ?>
							<input type="text" class="widefat" name="cv_tv_buttons[<?php echo esc_attr( (string) $i ); ?>][target]" value="<?php echo esc_attr( $b['target'] ?? '' ); ?>" placeholder="https://..." />
						<?php endif; ?>
					</td>
				</tr>
			<?php endfor; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Lascia vuoto il campo "Etichetta" per nascondere il pulsante. Tipo "Home Assistant" usa l\'URL globale nelle Impostazioni.', 'casa-volterra-tv-launcher' ); ?></p>
		<?php
	}

	public static function render_btn_layout( $post ) {
		$size    = get_post_meta( $post->ID, '_cv_tv_btn_size',   true ) ?: 'medium';
		$align_h = get_post_meta( $post->ID, '_cv_tv_btn_align',  true ) ?: 'left';
		$align_v = get_post_meta( $post->ID, '_cv_tv_btn_valign', true ) ?: 'bottom';
		$cols    = get_post_meta( $post->ID, '_cv_tv_btn_cols',   true ) ?: '3';
		$sizes   = array( 'small' => 'Piccoli', 'medium' => 'Medi (default)', 'large' => 'Grandi' );
		$aligns  = array( 'left' => 'Sinistra', 'center' => 'Centro', 'right' => 'Destra' );
		$valigns = array( 'top' => 'In alto', 'center' => 'Centro', 'bottom' => 'In basso (default)' );
		$cols_opts = array( '1' => '1 colonna', '2' => '2 colonne', '3' => '3 colonne (default)', '4' => '4 colonne' );
		?>
		<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:24px;padding:4px 0">
			<div>
				<p style="font-weight:600;margin-bottom:8px"><?php esc_html_e( 'Dimensione pulsanti', 'casa-volterra-tv-launcher' ); ?></p>
				<?php foreach ( $sizes as $v => $l ) : ?>
				<label style="display:block;margin:5px 0">
					<input type="radio" name="cv_tv_btn_size" value="<?php echo esc_attr( $v ); ?>" <?php checked( $size, $v ); ?>> <?php echo esc_html( $l ); ?>
				</label>
				<?php endforeach; ?>
			</div>
			<div>
				<p style="font-weight:600;margin-bottom:8px"><?php esc_html_e( 'Numero colonne', 'casa-volterra-tv-launcher' ); ?></p>
				<?php foreach ( $cols_opts as $v => $l ) : ?>
				<label style="display:block;margin:5px 0">
					<input type="radio" name="cv_tv_btn_cols" value="<?php echo esc_attr( $v ); ?>" <?php checked( $cols, $v ); ?>> <?php echo esc_html( $l ); ?>
				</label>
				<?php endforeach; ?>
			</div>
			<div>
				<p style="font-weight:600;margin-bottom:8px"><?php esc_html_e( 'Allineamento', 'casa-volterra-tv-launcher' ); ?></p>
				<?php foreach ( $aligns as $v => $l ) : ?>
				<label style="display:block;margin:5px 0">
					<input type="radio" name="cv_tv_btn_align" value="<?php echo esc_attr( $v ); ?>" <?php checked( $align_h, $v ); ?>> <?php echo esc_html( $l ); ?>
				</label>
				<?php endforeach; ?>
			</div>
			<div>
				<p style="font-weight:600;margin-bottom:8px"><?php esc_html_e( 'Posizione verticale', 'casa-volterra-tv-launcher' ); ?></p>
				<?php foreach ( $valigns as $v => $l ) : ?>
				<label style="display:block;margin:5px 0">
					<input type="radio" name="cv_tv_btn_valign" value="<?php echo esc_attr( $v ); ?>" <?php checked( $align_v, $v ); ?>> <?php echo esc_html( $l ); ?>
				</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public static function render_qr( $post ) {
		$qr_url   = get_post_meta( $post->ID, '_cv_tv_qr_url', true );
		$qr_label = get_post_meta( $post->ID, '_cv_tv_qr_label', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cv_tv_qr_url"><?php esc_html_e( 'URL del QR code', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_qr_url" type="url" class="widefat" name="cv_tv_qr_url" value="<?php echo esc_attr( $qr_url ); ?>" placeholder="https://casa-volterra.it/guida" />
					<p class="description"><?php esc_html_e( 'Lascia vuoto per non mostrare il QR code.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cv_tv_qr_label"><?php esc_html_e( 'Didascalia QR', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td><input id="cv_tv_qr_label" type="text" class="regular-text" name="cv_tv_qr_label" value="<?php echo esc_attr( $qr_label ); ?>" placeholder="Inquadra per aprire la guida" /></td>
			</tr>
		</table>
		<?php
	}

	public static function render_ticker( $post ) {
		$mode   = get_post_meta( $post->ID, '_cv_tv_ticker_mode', true );
		$rss    = get_post_meta( $post->ID, '_cv_tv_ticker_rss', true );
		$static = get_post_meta( $post->ID, '_cv_tv_ticker_text', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Modalità ticker', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<label style="margin-right:20px;"><input type="radio" name="cv_tv_ticker_mode" value="" <?php checked( '', $mode ); ?> /> <?php esc_html_e( 'Nessuno', 'casa-volterra-tv-launcher' ); ?></label>
					<label style="margin-right:20px;"><input type="radio" name="cv_tv_ticker_mode" value="static" <?php checked( 'static', $mode ); ?> /> <?php esc_html_e( 'Testo statico', 'casa-volterra-tv-launcher' ); ?></label>
					<label><input type="radio" name="cv_tv_ticker_mode" value="rss" <?php checked( 'rss', $mode ); ?> /> <?php esc_html_e( 'Feed RSS', 'casa-volterra-tv-launcher' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cv_tv_ticker_text"><?php esc_html_e( 'Testo statico', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_ticker_text" type="text" class="widefat" name="cv_tv_ticker_text" value="<?php echo esc_attr( $static ); ?>" placeholder="Benvenuti · Wi-Fi: CasaVolterra · Checkout ore 10:00 · Silenzio dopo le 22:00" />
					<p class="description"><?php esc_html_e( 'Usa · (punto centrato) come separatore tra le voci.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cv_tv_ticker_rss"><?php esc_html_e( 'URL feed RSS', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_ticker_rss" type="url" class="widefat" name="cv_tv_ticker_rss" value="<?php echo esc_attr( $rss ); ?>" placeholder="https://www.iltirreno.it/rss" />
				</td>
			</tr>
		</table>
		<?php
	}

	public static function render_webcams( $post ) {
		$webcams = get_post_meta( $post->ID, '_cv_tv_webcams', true );
		if ( ! is_array( $webcams ) ) {
			$webcams = array();
		}
		?>
		<div style="background:#fff3cd;border-left:4px solid #f0a500;padding:10px 14px;margin-bottom:14px;border-radius:0 6px 6px 0;font-size:.9rem;">
			<strong>💡 <?php esc_html_e( 'Tipo consigliato: Windy (ID)', 'casa-volterra-tv-launcher' ); ?></strong><br>
			<?php esc_html_e( 'Per la webcam di Piazza dei Priori usa il tipo "Windy (ID)" e inserisci solo il numero ID: ', 'casa-volterra-tv-launcher' ); ?>
			<code>1292947496</code><br>
			<?php esc_html_e( 'Funziona su qualsiasi browser senza problemi di autoplay — mostra uno snapshot aggiornato ogni 60 secondi.', 'casa-volterra-tv-launcher' ); ?>
		</div>
		<table class="widefat" id="cv-tv-webcam-table">
			<thead>
				<tr>
					<th style="width:20%"><?php esc_html_e( 'Etichetta', 'casa-volterra-tv-launcher' ); ?></th>
					<th style="width:16%"><?php esc_html_e( 'Tipo', 'casa-volterra-tv-launcher' ); ?></th>
					<th><?php esc_html_e( 'URL / ID Windy', 'casa-volterra-tv-launcher' ); ?></th>
					<th style="width:100px"><?php esc_html_e( 'Durata (s)', 'casa-volterra-tv-launcher' ); ?></th>
					<th style="width:40px"></th>
				</tr>
			</thead>
			<tbody id="cv-tv-webcam-rows">
			<?php foreach ( $webcams as $i => $cam ) : ?>
				<tr class="cv-tv-webcam-row">
					<td><input type="text" name="cv_tv_webcams[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $cam['label'] ?? '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'es. Piazza dei Priori', 'casa-volterra-tv-launcher' ); ?>" /></td>
					<td>
						<select name="cv_tv_webcams[<?php echo (int) $i; ?>][type]" class="cv-tv-wc-type-sel">
							<option value="windy_id"  <?php selected( 'windy_id',  $cam['type'] ?? 'windy_id' ); ?>><?php esc_html_e( '⭐ Windy (ID)', 'casa-volterra-tv-launcher' ); ?></option>
							<option value="hls"       <?php selected( 'hls',       $cam['type'] ?? '' ); ?>><?php esc_html_e( '▶ HLS (m3u8)', 'casa-volterra-tv-launcher' ); ?></option>
							<option value="snapshot"  <?php selected( 'snapshot',  $cam['type'] ?? '' ); ?>><?php esc_html_e( 'Snapshot JPEG', 'casa-volterra-tv-launcher' ); ?></option>
							<option value="iframe"    <?php selected( 'iframe',    $cam['type'] ?? '' ); ?>><?php esc_html_e( 'iframe (rtsp.me)', 'casa-volterra-tv-launcher' ); ?></option>
						</select>
					</td>
					<td>
						<input type="text" name="cv_tv_webcams[<?php echo (int) $i; ?>][url]"
							value="<?php echo esc_attr( $cam['url'] ?? '' ); ?>"
							class="widefat cv-tv-wc-url-input"
							placeholder="<?php echo 'windy_id' === ( $cam['type'] ?? 'windy_id' ) ? esc_attr__( 'es. 1292947496', 'casa-volterra-tv-launcher' ) : 'https://...'; ?>" />
					</td>
					<td><input type="number" name="cv_tv_webcams[<?php echo (int) $i; ?>][duration]" value="<?php echo (int) ( $cam['duration'] ?? 30 ); ?>" min="5" max="300" style="width:70px" /></td>
					<td><button type="button" class="button cv-tv-webcam-remove">✕</button></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:10px">
			<button type="button" class="button button-primary" id="cv-tv-webcam-add"><?php esc_html_e( '+ Aggiungi webcam', 'casa-volterra-tv-launcher' ); ?></button>
		</p>

		<!-- Template riga (clonata da JS) -->
		<template id="cv-tv-webcam-row-tpl">
			<tr class="cv-tv-webcam-row">
				<td><input type="text" name="cv_tv_webcams[__IDX__][label]" value="" class="widefat" placeholder="<?php esc_attr_e( 'es. Vista panoramica', 'casa-volterra-tv-launcher' ); ?>" /></td>
				<td>
					<select name="cv_tv_webcams[__IDX__][type]" class="cv-tv-wc-type-sel">
						<option value="windy_id"><?php esc_html_e( '⭐ Windy (ID)', 'casa-volterra-tv-launcher' ); ?></option>
						<option value="hls"><?php esc_html_e( '▶ HLS (m3u8)', 'casa-volterra-tv-launcher' ); ?></option>
						<option value="snapshot"><?php esc_html_e( 'Snapshot JPEG', 'casa-volterra-tv-launcher' ); ?></option>
						<option value="iframe"><?php esc_html_e( 'iframe (rtsp.me)', 'casa-volterra-tv-launcher' ); ?></option>
					</select>
				</td>
				<td><input type="text" name="cv_tv_webcams[__IDX__][url]" value="" class="widefat cv-tv-wc-url-input" placeholder="<?php esc_attr_e( 'es. 1292947496', 'casa-volterra-tv-launcher' ); ?>" /></td>
				<td><input type="number" name="cv_tv_webcams[__IDX__][duration]" value="30" min="5" max="300" style="width:70px" /></td>
				<td><button type="button" class="button cv-tv-webcam-remove">✕</button></td>
			</tr>
		</template>
		<?php
	}

	public static function render_footer( $post ) {
		$note = get_post_meta( $post->ID, '_cv_tv_footer_note', true );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cv_tv_footer_note"><?php esc_html_e( 'Nota footer (override locale)', 'casa-volterra-tv-launcher' ); ?></label></th>
				<td>
					<input id="cv_tv_footer_note" type="text" class="widefat" name="cv_tv_footer_note" value="<?php echo esc_attr( $note ); ?>" placeholder="<?php esc_attr_e( 'Lascia vuoto per usare il testo globale delle impostazioni.', 'casa-volterra-tv-launcher' ); ?>" />
				</td>
			</tr>
		</table>
		<?php
	}

	/* ── Save meta ─────────────────────────────────────────── */

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['cv_tv_screen_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_tv_screen_nonce'] ) ), 'cv_tv_save_screen_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// URL fields
		$url_fields = array( 'cv_tv_bg_image' => '_cv_tv_bg_image', 'cv_tv_bg_video' => '_cv_tv_bg_video', 'cv_tv_logo_url' => '_cv_tv_logo_url', 'cv_tv_qr_url' => '_cv_tv_qr_url', 'cv_tv_ticker_rss' => '_cv_tv_ticker_rss' );
		foreach ( $url_fields as $field => $meta ) {
			$val = isset( $_POST[ $field ] ) ? esc_url_raw( wp_unslash( (string) $_POST[ $field ] ) ) : '';
			update_post_meta( $post_id, $meta, $val );
		}

		// Text fields
		$text_fields = array( 'cv_tv_eyebrow' => '_cv_tv_eyebrow', 'cv_tv_title' => '_cv_tv_title', 'cv_tv_weather_city' => '_cv_tv_weather_city', 'cv_tv_qr_label' => '_cv_tv_qr_label', 'cv_tv_ticker_text' => '_cv_tv_ticker_text', 'cv_tv_ticker_mode' => '_cv_tv_ticker_mode', 'cv_tv_footer_note' => '_cv_tv_footer_note' );
		foreach ( $text_fields as $field => $meta ) {
			$val = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $field ] ) ) : '';
			update_post_meta( $post_id, $meta, $val );
		}

		// Textarea
		$subtitle = isset( $_POST['cv_tv_subtitle'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['cv_tv_subtitle'] ) ) : '';
		update_post_meta( $post_id, '_cv_tv_subtitle', $subtitle );

		// Overlay range
		$overlay = isset( $_POST['cv_tv_overlay'] ) ? min( 90, max( 0, absint( $_POST['cv_tv_overlay'] ) ) ) : 55;
		update_post_meta( $post_id, '_cv_tv_overlay', (string) $overlay );

		// Checkboxes
		update_post_meta( $post_id, '_cv_tv_show_clock',   ! empty( $_POST['cv_tv_show_clock'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_cv_tv_show_weather', ! empty( $_POST['cv_tv_show_weather'] ) ? '1' : '0' );

		// Badges
		$badges_raw = isset( $_POST['cv_tv_badges'] ) && is_array( $_POST['cv_tv_badges'] ) ? $_POST['cv_tv_badges'] : array();
		$badges     = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$badges[] = sanitize_text_field( wp_unslash( (string) ( $badges_raw[ $i ] ?? '' ) ) );
		}
		update_post_meta( $post_id, '_cv_tv_badges', $badges );

		// Button layout
		$btn_size   = sanitize_key( isset( $_POST['cv_tv_btn_size'] )   ? $_POST['cv_tv_btn_size']   : 'medium' );
		$btn_align  = sanitize_key( isset( $_POST['cv_tv_btn_align'] )  ? $_POST['cv_tv_btn_align']  : 'left' );
		$btn_valign = sanitize_key( isset( $_POST['cv_tv_btn_valign'] ) ? $_POST['cv_tv_btn_valign'] : 'bottom' );
		$btn_cols   = sanitize_key( isset( $_POST['cv_tv_btn_cols'] )   ? $_POST['cv_tv_btn_cols']   : '3' );
		update_post_meta( $post_id, '_cv_tv_btn_size',   in_array( $btn_size,   array( 'small','medium','large' ), true )  ? $btn_size   : 'medium' );
		update_post_meta( $post_id, '_cv_tv_btn_align',  in_array( $btn_align,  array( 'left','center','right' ), true )   ? $btn_align  : 'left' );
		update_post_meta( $post_id, '_cv_tv_btn_valign', in_array( $btn_valign, array( 'top','center','bottom' ), true )   ? $btn_valign : 'bottom' );
		update_post_meta( $post_id, '_cv_tv_btn_cols',   in_array( $btn_cols,   array( '1','2','3','4' ), true )           ? $btn_cols   : '3' );

		// Button layout
		$btn_size   = sanitize_key( isset( $_POST['cv_tv_btn_size'] )   ? $_POST['cv_tv_btn_size']   : 'medium' );
		$btn_align  = sanitize_key( isset( $_POST['cv_tv_btn_align'] )  ? $_POST['cv_tv_btn_align']  : 'left' );
		$btn_valign = sanitize_key( isset( $_POST['cv_tv_btn_valign'] ) ? $_POST['cv_tv_btn_valign'] : 'bottom' );
		$btn_cols   = sanitize_key( isset( $_POST['cv_tv_btn_cols'] )   ? $_POST['cv_tv_btn_cols']   : '3' );
		update_post_meta( $post_id, '_cv_tv_btn_size',   in_array( $btn_size,   array('small','medium','large'), true )   ? $btn_size   : 'medium' );
		update_post_meta( $post_id, '_cv_tv_btn_align',  in_array( $btn_align,  array('left','center','right'), true )    ? $btn_align  : 'left' );
		update_post_meta( $post_id, '_cv_tv_btn_valign', in_array( $btn_valign, array('top','center','bottom'), true )    ? $btn_valign : 'bottom' );
		update_post_meta( $post_id, '_cv_tv_btn_cols',   in_array( $btn_cols,   array('1','2','3','4'), true )            ? $btn_cols   : '3' );

		// Buttons
		$buttons_raw = isset( $_POST['cv_tv_buttons'] ) && is_array( $_POST['cv_tv_buttons'] ) ? $_POST['cv_tv_buttons'] : array();
		$buttons     = array();
		$valid_types = array( 'url', 'ha', 'protocol', 'browser', 'guide', 'ai_chat', 'pdf_viewer', 'smoobu_guide' );
		for ( $i = 0; $i < 8; $i++ ) {
			$raw  = isset( $buttons_raw[ $i ] ) && is_array( $buttons_raw[ $i ] ) ? $buttons_raw[ $i ] : array();
			$type = sanitize_key( $raw['type'] ?? 'url' );
			if ( ! in_array( $type, $valid_types, true ) ) {
				$type = 'url';
			}
			$target_raw = trim( wp_unslash( (string) ( $raw['target'] ?? '' ) ) );
			$target     = in_array( $type, array( 'url', 'ha', 'browser' ), true ) ? esc_url_raw( $target_raw ) : sanitize_text_field( $target_raw );
			$buttons[]  = array(
				'icon'   => sanitize_text_field( wp_unslash( (string) ( $raw['icon'] ?? '' ) ) ),
				'label'  => sanitize_text_field( wp_unslash( (string) ( $raw['label'] ?? '' ) ) ),
				'desc'   => sanitize_text_field( wp_unslash( (string) ( $raw['desc'] ?? '' ) ) ),
				'type'   => $type,
				'target' => $target,
			);
		}
		update_post_meta( $post_id, '_cv_tv_buttons', $buttons );

		// Webcams
		$webcams_raw = isset( $_POST['cv_tv_webcams'] ) && is_array( $_POST['cv_tv_webcams'] ) ? $_POST['cv_tv_webcams'] : array();
		$webcams     = array();
		$valid_cam_types = array( 'windy_id', 'hls', 'iframe', 'snapshot' );
		foreach ( $webcams_raw as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$cam_type = sanitize_key( $raw['type'] ?? 'windy_id' );
			$url_raw  = wp_unslash( (string) ( $raw['url'] ?? '' ) );
			$url = 'windy_id' === $cam_type
				? preg_replace( '/[^0-9]/', '', $url_raw ) // solo cifre per l'ID
				: esc_url_raw( $url_raw );
			if ( empty( $url ) ) {
				continue;
			}
			if ( ! in_array( $cam_type, $valid_cam_types, true ) ) {
				$cam_type = 'iframe';
			}
			$webcams[] = array(
				'label'    => sanitize_text_field( wp_unslash( (string) ( $raw['label'] ?? '' ) ) ),
				'type'     => $cam_type,
				'url'      => $url,
				'duration' => min( 300, max( 5, absint( $raw['duration'] ?? 15 ) ) ),
			);
		}
		update_post_meta( $post_id, '_cv_tv_webcams', $webcams );
	}

	/* ── Admin columns ─────────────────────────────────────── */

	public static function columns( $cols ) {
		$cols['cv_tv_zones'] = __( 'Zone attive', 'casa-volterra-tv-launcher' );
		return $cols;
	}

	public static function column_content( $col, $post_id ) {
		if ( 'cv_tv_zones' !== $col ) {
			return;
		}
		$zones = array();
		if ( get_post_meta( $post_id, '_cv_tv_bg_image', true ) || get_post_meta( $post_id, '_cv_tv_bg_video', true ) ) {
			$zones[] = '🖼';
		}
		if ( get_post_meta( $post_id, '_cv_tv_logo_url', true ) ) {
			$zones[] = '🏷';
		}
		if ( '1' === get_post_meta( $post_id, '_cv_tv_show_clock', true ) ) {
			$zones[] = '🕐';
		}
		if ( get_post_meta( $post_id, '_cv_tv_title', true ) ) {
			$zones[] = '✏️';
		}
		$btns = get_post_meta( $post_id, '_cv_tv_buttons', true );
		if ( is_array( $btns ) ) {
			$active = array_filter( $btns, fn( $b ) => ! empty( $b['label'] ) );
			if ( $active ) {
				$zones[] = '🟦×' . count( $active );
			}
		}
		if ( get_post_meta( $post_id, '_cv_tv_qr_url', true ) ) {
			$zones[] = '📱';
		}
		if ( get_post_meta( $post_id, '_cv_tv_ticker_mode', true ) ) {
			$zones[] = '📰';
		}
		$wcams = get_post_meta( $post_id, '_cv_tv_webcams', true );
		if ( is_array( $wcams ) && ! empty( $wcams ) ) {
			$zones[] = '📷×' . count( $wcams );
		}
		echo esc_html( implode( '  ', $zones ) );
	}

	/* ── Data helper ────────────────────────────────────────── */

	/**
	 * Ritorna array strutturato con tutti i dati della screen (usato da Renderer).
	 */
	public static function get_screen_data( $post_id, $duration = 10, $transition = 'fade' ) {
		$post = get_post( $post_id );
		if ( ! $post || self::PT !== $post->post_type ) {
			return null;
		}
		$settings = CV_TV_Launcher::get_settings();

		// ── Slug interpolation ──────────────────────────────────────────────────
		$display_guest_name = CV_TV_Launcher::effective_guest_name( $settings );
		$next_ci_name = $settings['next_guest_name'] ?? '';
		$is_next_active = ( $display_guest_name !== '' && $next_ci_name !== '' && $display_guest_name === $next_ci_name );
		if ( $is_next_active ) {
			$eff_ci_date = $settings['next_checkin']       ?? '';
			$eff_ci_time = $settings['next_checkin_time']  ?? '';
			$eff_co_date = $settings['next_checkout']      ?? '';
			$eff_co_time = $settings['next_checkout_time'] ?? '';
		} else {
			$eff_ci_date = $settings['current_checkin']       ?? '';
			$eff_ci_time = $settings['current_checkin_time']  ?? '';
			$eff_co_date = $settings['current_checkout']      ?? '';
			$eff_co_time = $settings['current_checkout_time'] ?? '';
		}
		$slug_map = array(
			'{nome_ospite}'   => $display_guest_name,
			'{data_checkin}'  => $eff_ci_date ? wp_date( 'd/m/Y', strtotime( $eff_ci_date ) ) : '',
			'{ora_checkin}'   => $eff_ci_time,
			'{data_checkout}' => $eff_co_date ? wp_date( 'd/m/Y', strtotime( $eff_co_date ) ) : '',
			'{ora_checkout}'  => $eff_co_time,
		);
		$interpolate = function( $text ) use ( $slug_map ) {
			if ( ! $text ) return $text;
			return str_replace( array_keys( $slug_map ), array_values( $slug_map ), $text );
		};

		// ── Buttons ───────────────────────────────────────────────────
		$buttons  = get_post_meta( $post_id, '_cv_tv_buttons', true );
		if ( ! is_array( $buttons ) ) {
			$buttons = array();
		}
		$clean_buttons = array();
		foreach ( $buttons as $b ) {
			if ( empty( $b['label'] ) ) {
				continue;
			}
			$type   = $b['type']   ?? 'url';
			$target = $b['target'] ?? '';

			if ( 'ha' === $type && $settings['home_assistant_url'] ) {
				$target = $settings['home_assistant_url'];
			} elseif ( 'smoobu_guide' === $type ) {
				// Guida dell'OSPITE ATTUALE — visibile solo se c'è qualcuno in casa.
				$guest_url = $settings['current_guest_app_url'] ?? '';
				if ( ! $guest_url ) {
					continue; // Nessun ospite attuale: nascondi il pulsante
				}
				$target = $guest_url;
				$type   = 'browser';
			}
			$clean_buttons[] = array(
				'icon'   => $b['icon'] ?? '',
				'label'  => $b['label'],
				'desc'   => $b['desc'] ?? '',
				'type'   => $type,
				'target' => $target,
			);
		}
		$badges = get_post_meta( $post_id, '_cv_tv_badges', true );
		if ( ! is_array( $badges ) ) {
			$badges = array();
		}
		$badges = array_values( array_filter( $badges ) );

		$webcams = get_post_meta( $post_id, '_cv_tv_webcams', true );
		if ( ! is_array( $webcams ) ) {
			$webcams = array();
		}

		return array(
			'id'            => $post_id,
			'title'         => get_the_title( $post_id ),
			'duration'      => (int) $duration,
			'transition'    => $transition,
			'bg_image'      => get_post_meta( $post_id, '_cv_tv_bg_image', true ),
			'bg_video'      => get_post_meta( $post_id, '_cv_tv_bg_video', true ),
			'overlay'       => (int) get_post_meta( $post_id, '_cv_tv_overlay', true ),
			'logo'          => get_post_meta( $post_id, '_cv_tv_logo_url', true ),
			'show_clock'    => '1' === get_post_meta( $post_id, '_cv_tv_show_clock', true ),
			'show_weather'  => '1' === get_post_meta( $post_id, '_cv_tv_show_weather', true ),
			'weather_city'  => get_post_meta( $post_id, '_cv_tv_weather_city', true ) ?: 'Volterra',
			'eyebrow'       => $interpolate( get_post_meta( $post_id, '_cv_tv_eyebrow', true ) ),
			'heading'       => $interpolate( get_post_meta( $post_id, '_cv_tv_title', true ) ),
			'subtitle'      => $interpolate( get_post_meta( $post_id, '_cv_tv_subtitle', true ) ),
			'badges'        => $badges,
			'buttons'       => $clean_buttons,
			'qr_url'        => get_post_meta( $post_id, '_cv_tv_qr_url', true ),
			'qr_label'      => get_post_meta( $post_id, '_cv_tv_qr_label', true ),
			'ticker_mode'   => get_post_meta( $post_id, '_cv_tv_ticker_mode', true ),
			'ticker_text'   => get_post_meta( $post_id, '_cv_tv_ticker_text', true ),
			'ticker_rss'    => get_post_meta( $post_id, '_cv_tv_ticker_rss', true ),
			'footer_note'   => get_post_meta( $post_id, '_cv_tv_footer_note', true ),
			'webcams'       => $webcams,
			'btn_size'      => get_post_meta( $post_id, '_cv_tv_btn_size',   true ) ?: 'medium',
			'btn_align'     => get_post_meta( $post_id, '_cv_tv_btn_align',  true ) ?: 'left',
			'btn_valign'    => get_post_meta( $post_id, '_cv_tv_btn_valign', true ) ?: 'bottom',
			'btn_cols'      => (int) ( get_post_meta( $post_id, '_cv_tv_btn_cols', true ) ?: 3 ),
		);
	}
}
