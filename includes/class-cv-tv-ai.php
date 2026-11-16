<?php
/**
 * Casa Volterra TV — AI Assistant v4.0
 * Knowledge base CPT + REST endpoint + chat page
 * Supporto web search (gpt-4o-mini-search-preview)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CV_TV_AI {

	const PT         = 'cv_tv_kb';
	const OPTION     = 'cv_tv_ai_settings';
	const CHAT_SLUG  = 'chat-tv';

	/* ── Init ──────────────────────────────────────────────── */

	public static function init() {
		add_action( 'init',                               array( __CLASS__, 'register_post_type' ) );
		add_action( 'init',                               array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'use_block_editor_for_post_type',     array( __CLASS__, 'disable_gutenberg' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::PT,         array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::PT,              array( __CLASS__, 'save_meta' ) );
		add_action( 'admin_menu',                         array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init',                         array( __CLASS__, 'register_settings' ) );
		add_action( 'rest_api_init',                      array( __CLASS__, 'register_rest' ) );
		add_action( 'template_redirect',                  array( __CLASS__, 'maybe_render_chat' ) );
		add_filter( 'query_vars',                         array( __CLASS__, 'query_vars' ) );
		add_filter( 'manage_' . self::PT . '_posts_columns',       array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::PT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/* ── CPT ───────────────────────────────────────────────── */

	public static function register_post_type() {
		register_post_type( self::PT, array(
			'labels'        => array(
				'name'          => __( 'Knowledge Base AI', 'casa-volterra-tv-launcher' ),
				'singular_name' => __( 'Articolo KB', 'casa-volterra-tv-launcher' ),
				'add_new'       => __( 'Nuovo articolo', 'casa-volterra-tv-launcher' ),
				'add_new_item'  => __( 'Nuovo articolo KB', 'casa-volterra-tv-launcher' ),
				'edit_item'     => __( 'Modifica articolo KB', 'casa-volterra-tv-launcher' ),
				'not_found'     => __( 'Nessun articolo trovato', 'casa-volterra-tv-launcher' ),
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => 'edit.php?post_type=cv_tv_screen',
			'supports'        => array( 'title' ),
			'show_in_rest'    => false,
			'capability_type' => 'post',
			'map_meta_cap'    => true,
		) );
		register_taxonomy( 'cv_tv_kb_cat', self::PT, array(
			'labels'       => array(
				'name'          => __( 'Categorie KB', 'casa-volterra-tv-launcher' ),
				'singular_name' => __( 'Categoria KB', 'casa-volterra-tv-launcher' ),
				'add_new_item'  => __( 'Nuova categoria', 'casa-volterra-tv-launcher' ),
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'hierarchical' => true,
			'show_in_rest' => false,
		) );
	}

	public static function disable_gutenberg( $use, $pt ) {
		return self::PT === $pt ? false : $use;
	}

	public static function query_vars( $vars ) {
		$vars[] = 'cv_tv_chat';
		return $vars;
	}

	public static function register_rewrite() {
		add_rewrite_rule( '^' . self::CHAT_SLUG . '/?$', 'index.php?cv_tv_chat=1', 'top' );
	}

	/* ── Meta boxes ────────────────────────────────────────── */

	public static function register_meta_boxes() {
		add_meta_box( 'cv_tv_kb_content', __( '📝 Contenuto articolo', 'casa-volterra-tv-launcher' ),
			array( __CLASS__, 'render_content_box' ), self::PT, 'normal', 'high' );
		add_meta_box( 'cv_tv_kb_pdf', __( '📄 PDF allegato (opzionale)', 'casa-volterra-tv-launcher' ),
			array( __CLASS__, 'render_pdf_box' ), self::PT, 'normal', 'default' );
		add_meta_box( 'cv_tv_kb_preview', __( '👁 Anteprima sistema prompt', 'casa-volterra-tv-launcher' ),
			array( __CLASS__, 'render_preview_box' ), self::PT, 'side', 'default' );
	}

	public static function render_content_box( $post ) {
		wp_nonce_field( 'cv_tv_kb_save_' . $post->ID, 'cv_tv_kb_nonce' );
		$content  = get_post_meta( $post->ID, '_cv_kb_content',  true );
		$category = get_post_meta( $post->ID, '_cv_kb_category', true ) ?: 'generale';
		$priority = get_post_meta( $post->ID, '_cv_kb_priority', true ) ?: '5';
		$categories = array(
			'appartamento'     => '🏠 Appartamento',
			'checkin'          => '🔑 Check-in / Check-out',
			'wifi'             => '📶 Wi-Fi & Tech',
			'elettrodomestici' => '🔧 Elettrodomestici',
			'zona'             => '🗺 Zona & Attività',
			'regole'           => '📋 Regole della casa',
			'emergenze'        => '🚨 Emergenze',
			'generale'         => '💬 Generale',
		);
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Categoria', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<select name="cv_kb_category" style="width:300px">
						<?php foreach ( $categories as $v => $l ) : ?>
							<option value="<?php echo esc_attr( $v ); ?>" <?php selected( $category, $v ); ?>>
								<?php echo esc_html( $l ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Priorità (1-10)', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<input type="number" name="cv_kb_priority" min="1" max="10"
						value="<?php echo esc_attr( $priority ); ?>" style="width:60px" />
					<p class="description"><?php esc_html_e( '10 = massima priorità (comparirà prima nel prompt AI)', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Contenuto', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<textarea name="cv_kb_content" rows="12" class="widefat"
						placeholder="<?php esc_attr_e( 'Scrivi qui le informazioni che l\'AI deve conoscere su questo argomento...', 'casa-volterra-tv-launcher' ); ?>"
						style="font-family:monospace;font-size:13px"><?php echo esc_textarea( $content ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Usa linguaggio naturale. Più è specifico e dettagliato, migliori saranno le risposte dell\'AI.', 'casa-volterra-tv-launcher' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function render_pdf_box( $post ) {
		$pdf_url  = get_post_meta( $post->ID, '_cv_kb_pdf_url',  true );
		$pdf_text = get_post_meta( $post->ID, '_cv_kb_pdf_text', true );
		?>
		<div style="margin-bottom:12px">
			<p><?php esc_html_e( 'Carica un PDF (manuale, guida, ecc.) — il testo verrà estratto automaticamente e aggiunto alla knowledge base.', 'casa-volterra-tv-launcher' ); ?></p>
			<input type="url" name="cv_kb_pdf_url" id="cv_kb_pdf_url"
				value="<?php echo esc_attr( $pdf_url ); ?>" class="widefat"
				placeholder="https://..." />
			<button type="button" class="button" id="cv_kb_pdf_pick" style="margin-top:6px">
				📄 <?php esc_html_e( 'Scegli PDF dalla Libreria', 'casa-volterra-tv-launcher' ); ?>
			</button>
		</div>
		<?php if ( $pdf_url ) : ?>
		<div style="background:#f0f8ff;padding:10px;border-radius:6px;margin-bottom:12px">
			<strong><?php esc_html_e( 'PDF caricato:', 'casa-volterra-tv-launcher' ); ?></strong>
			<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank"><?php echo esc_html( basename( $pdf_url ) ); ?></a>
			<?php if ( $pdf_text ) : ?>
				<span style="color:#0a0;margin-left:10px">✅ <?php esc_html_e( 'Testo estratto', 'casa-volterra-tv-launcher' ); ?></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<input type="hidden" name="cv_kb_pdf_text" value="<?php echo esc_attr( $pdf_text ); ?>" />
		<?php if ( $pdf_text ) : ?>
		<p style="color:#0a0;font-size:13px">✅ <?php echo number_format( strlen( $pdf_text ) ); ?> <?php esc_html_e( 'caratteri estratti dal PDF e salvati nella KB.', 'casa-volterra-tv-launcher' ); ?></p>
		<?php endif; ?>
		<div id="cv_kb_extract_status" style="display:none;margin-top:8px;padding:8px 12px;border-radius:6px;font-size:.88rem"></div>
		<script>
		jQuery(function($){
			$('#cv_kb_pdf_pick').on('click', function(){
				var frame = wp.media({ title:'Scegli PDF', button:{text:'Usa questo PDF'}, multiple:false, library:{type:'application/pdf'} });
				frame.on('select', function(){
					var att = frame.state().get('selection').first().toJSON();
					$('#cv_kb_pdf_url').val(att.url);
					extractPdfText(att.url);
				});
				frame.open();
			});
			$('#cv_kb_pdf_url').on('change', function(){
				var url = $(this).val().trim();
				if (url && url.endsWith('.pdf')) extractPdfText(url);
			});
			function extractPdfText(url) {
				var $s = $('#cv_kb_extract_status');
				$s.show().css({background:'#fff3cd',color:'#856404'}).text('⏳ Estrazione testo in corso...');
				$.ajax({
					url: wpApiSettings.root + 'cv-tv/v1/ai/extract-pdf',
					method: 'POST',
					beforeSend: function(xhr){ xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce); },
					contentType: 'application/json',
					data: JSON.stringify({ url: url }),
					success: function(data) {
						if (data.text) {
							$('input[name="cv_kb_pdf_text"]').val(data.text);
							$s.css({background:'#d4edda',color:'#155724'}).text('✅ Testo estratto: ' + data.chars.toLocaleString() + ' caratteri');
						}
					},
					error: function(xhr) {
						var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Estrazione automatica non disponibile.';
						$s.css({background:'#f8d7da',color:'#721c24'}).text('⚠️ ' + msg);
					}
				});
			}
		});
		</script>
		<?php
	}

	public static function render_preview_box( $post ) {
		$content  = get_post_meta( $post->ID, '_cv_kb_content', true );
		$pdf_text = get_post_meta( $post->ID, '_cv_kb_pdf_text', true );
		$total    = strlen( $content ) + strlen( $pdf_text );
		?>
		<p><strong><?php esc_html_e( 'Caratteri totali:', 'casa-volterra-tv-launcher' ); ?></strong>
			<?php echo number_format( $total ); ?> / ~50.000 max</p>
		<div style="background:#f5f5f5;padding:8px;border-radius:4px;font-size:11px;font-family:monospace;max-height:120px;overflow:auto">
			<?php echo esc_html( substr( $content, 0, 200 ) ); ?>
			<?php if ( strlen( $content ) > 200 ) echo '...'; ?>
		</div>
		<p class="description" style="margin-top:8px"><?php esc_html_e( 'Questo contenuto viene aggiunto al "system prompt" dell\'AI come fonte di conoscenza.', 'casa-volterra-tv-launcher' ); ?></p>
		<?php
	}

	/* ── Save meta ─────────────────────────────────────────── */

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['cv_tv_kb_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_tv_kb_nonce'] ) ), 'cv_tv_kb_save_' . $post_id ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		update_post_meta( $post_id, '_cv_kb_content',  sanitize_textarea_field( wp_unslash( (string) ( $_POST['cv_kb_content']  ?? '' ) ) ) );
		update_post_meta( $post_id, '_cv_kb_category', sanitize_key( $_POST['cv_kb_category'] ?? 'generale' ) );
		update_post_meta( $post_id, '_cv_kb_priority', min( 10, max( 1, absint( $_POST['cv_kb_priority'] ?? 5 ) ) ) );
		update_post_meta( $post_id, '_cv_kb_pdf_url',  esc_url_raw( wp_unslash( (string) ( $_POST['cv_kb_pdf_url']  ?? '' ) ) ) );
		update_post_meta( $post_id, '_cv_kb_pdf_text', sanitize_textarea_field( wp_unslash( (string) ( $_POST['cv_kb_pdf_text'] ?? '' ) ) ) );
	}

	/* ── Admin columns ─────────────────────────────────────── */

	public static function columns( $cols ) {
		$cols['cv_kb_cat']   = __( 'Categoria', 'casa-volterra-tv-launcher' );
		$cols['cv_kb_chars'] = __( 'Caratteri', 'casa-volterra-tv-launcher' );
		return $cols;
	}

	public static function column_content( $col, $post_id ) {
		if ( 'cv_kb_cat'   === $col ) echo esc_html( get_post_meta( $post_id, '_cv_kb_category', true ) ?: '—' );
		if ( 'cv_kb_chars' === $col ) echo number_format( strlen( get_post_meta( $post_id, '_cv_kb_content', true ) ) + strlen( get_post_meta( $post_id, '_cv_kb_pdf_text', true ) ) );
	}

	/* ── Admin menu & settings ─────────────────────────────── */

	public static function admin_menu() {
		add_submenu_page( 'edit.php?post_type=cv_tv_screen', __( 'AI Assistant Settings', 'casa-volterra-tv-launcher' ), __( '🤖 AI Assistant', 'casa-volterra-tv-launcher' ), 'manage_options', 'cv-tv-ai-settings', array( __CLASS__, 'render_settings_page' ) );
	}

	public static function register_settings() {
		register_setting( 'cv_tv_ai_group', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ) ) );
	}

	public static function sanitize_settings( $v ) {
		return array(
			'openai_key'            => sanitize_text_field( $v['openai_key']            ?? '' ),
			'ai_model'              => sanitize_text_field( $v['ai_model']               ?? 'gpt-4o-mini-search-preview' ),
			'max_tokens'            => min( 2000, max( 300, absint( $v['max_tokens']     ?? 700 ) ) ),
			'assistant_name'        => sanitize_text_field( $v['assistant_name']         ?? 'Sofia' ),
			'property_name'         => sanitize_text_field( $v['property_name']          ?? 'Casa Volterra' ),
			'language'              => sanitize_text_field( $v['language']               ?? 'multilingual' ),
			'owner_phone'           => sanitize_text_field( $v['owner_phone']            ?? '' ),
			'owner_contact_when'    => sanitize_text_field( $v['owner_contact_when']     ?? 'se_necessario' ),
			'web_search_enabled'    => ! empty( $v['web_search_enabled'] )    ? '1' : '0',
			'never_say_dontknow'    => ! empty( $v['never_say_dontknow'] )    ? '1' : '0',
			'authentic_restaurants' => ! empty( $v['authentic_restaurants'] ) ? '1' : '0',
			'response_style'        => sanitize_text_field( $v['response_style']         ?? 'bilanciato' ),
			'volterra_local'        => ! empty( $v['volterra_local'] )        ? '1' : '0',
			'intro_prompt'          => sanitize_textarea_field( $v['intro_prompt']       ?? '' ),
			'chat_enabled'          => ! empty( $v['chat_enabled'] ) ? '1' : '0',
			'quick_chips'           => sanitize_textarea_field( $v['quick_chips']        ?? "🔑 Check-in\n📶 Password Wi-Fi\n🚗 Parcheggio\n🍽️ Ristoranti vicini\n🕙 Check-out\n♨️ Come funziona la caldaia" ),
		);
	}

	private static function default_settings() {
		return array(
			'openai_key'            => '',
			'ai_model'              => 'gpt-4o-mini-search-preview',
			'max_tokens'            => 700,
			'assistant_name'        => 'Sofia',
			'property_name'         => 'Casa Volterra',
			'language'              => 'multilingual',
			'owner_phone'           => '',
			'owner_contact_when'    => 'se_necessario',
			'web_search_enabled'    => '1',
			'never_say_dontknow'    => '1',
			'authentic_restaurants' => '1',
			'response_style'        => 'bilanciato',
			'volterra_local'        => '1',
			'intro_prompt'          => '',
			'chat_enabled'          => '1',
			'quick_chips'           => "🔑 Check-in\n📶 Password Wi-Fi\n🚗 Parcheggio\n🍽️ Ristoranti vicini\n🕙 Check-out\n♨️ Come funziona la caldaia",
		);
	}

	/* ── Settings page render ──────────────────────────────── */

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$s = wp_parse_args( get_option( self::OPTION, array() ), self::default_settings() );
		$chat_token = self::get_chat_url_token();
		$chat_url   = home_url( '/' . self::CHAT_SLUG . '/?t=' . $chat_token );
		$qr_url     = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode( $chat_url );
		$articles    = get_posts( array( 'post_type' => self::PT, 'post_status' => 'publish', 'posts_per_page' => -1 ) );
		$total_chars = 0;
		foreach ( $articles as $a ) {
			$total_chars += strlen( get_post_meta( $a->ID, '_cv_kb_content', true ) ) + strlen( get_post_meta( $a->ID, '_cv_kb_pdf_text', true ) );
		}
		?>
		<div class="wrap">
		<h1>🤖 <?php esc_html_e( 'AI Assistant — Impostazioni', 'casa-volterra-tv-launcher' ); ?></h1>

		<div style="display:grid;grid-template-columns:1fr auto;gap:24px;align-items:start;margin:16px 0 24px">
			<div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px 20px">
				<strong><?php esc_html_e( 'URL chat ospiti (con token):', 'casa-volterra-tv-launcher' ); ?></strong><br>
				<code style="font-size:12px;word-break:break-all"><?php echo esc_html( $chat_url ); ?></code>
				<a href="<?php echo esc_url( $chat_url ); ?>" target="_blank" style="margin-left:8px">🔗 Apri</a><br>
				<span class="description">
					<?php esc_html_e( 'Questo URL contiene il token di accesso. Solo chi lo possiede (dal QR code) può usare la chat. Token:', 'casa-volterra-tv-launcher' ); ?>
					<code><?php echo esc_html( $chat_token ); ?></code>
				</span>
			</div>
			<div style="text-align:center">
				<img src="<?php echo esc_url( $qr_url ); ?>" width="120" height="120" alt="QR Code" style="border:4px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.15);border-radius:8px">
				<p class="description" style="margin-top:4px">QR anteprima</p>
			</div>
		</div>

		<form method="post" action="options.php">
		<?php settings_fields( 'cv_tv_ai_group' ); ?>

		<?php
		/* ---- helper per intestazioni sezione ---- */
		$section = function( $title ) {
			echo '<h2 style="border-bottom:2px solid #2271b1;padding-bottom:6px;margin-top:32px">' . esc_html( $title ) . '</h2>';
		};
		$opt = self::OPTION;
		?>

		<?php $section( '🔧 Configurazione OpenAI' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( '🔑 API Key OpenAI', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<input type="password" name="<?php echo esc_attr( $opt ); ?>[openai_key]" value="<?php echo esc_attr( $s['openai_key'] ); ?>" class="regular-text" placeholder="sk-..." autocomplete="off" />
					<p class="description"><?php esc_html_e( 'Ottieni la chiave su ', 'casa-volterra-tv-launcher' ); ?><a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a>. <?php esc_html_e( 'Non viene mai esposta al browser.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '🤖 Modello AI', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $opt ); ?>[ai_model]">
						<option value="gpt-4o-mini-search-preview" <?php selected( $s['ai_model'], 'gpt-4o-mini-search-preview' ); ?>>gpt-4o-mini-search-preview ⭐ (consigliato — ricerca web inclusa)</option>
						<option value="gpt-4o-search-preview"      <?php selected( $s['ai_model'], 'gpt-4o-search-preview' ); ?>>gpt-4o-search-preview (più potente, più costoso)</option>
						<option value="gpt-4o-mini"                <?php selected( $s['ai_model'], 'gpt-4o-mini' ); ?>>gpt-4o-mini (solo knowledge base, no web)</option>
						<option value="gpt-4o"                     <?php selected( $s['ai_model'], 'gpt-4o' ); ?>>gpt-4o (solo knowledge base, no web)</option>
					</select>
					<p class="description"><?php esc_html_e( 'I modelli "search-preview" cercano su internet in tempo reale. Richiedono la ricerca web abilitata qui sotto.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '📏 Lunghezza risposta', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $opt ); ?>[max_tokens]">
						<option value="400"  <?php selected( $s['max_tokens'], 400 );  ?>>Breve (~300 parole)</option>
						<option value="700"  <?php selected( $s['max_tokens'], 700 );  ?>>Media (~500 parole) ⭐</option>
						<option value="1000" <?php selected( $s['max_tokens'], 1000 ); ?>>Lunga (~750 parole)</option>
						<option value="1500" <?php selected( $s['max_tokens'], 1500 ); ?>>Molto lunga (~1100 parole)</option>
					</select>
					<p class="description"><?php esc_html_e( 'Risposta più lunga = più token = costo leggermente maggiore.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
		</table>

		<?php $section( '👤 Identità dell\'assistente' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Nome assistente', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( $opt ); ?>[assistant_name]" value="<?php echo esc_attr( $s['assistant_name'] ); ?>" class="regular-text" placeholder="Sofia" />
					<p class="description"><?php esc_html_e( 'Il nome con cui l\'AI si presenta agli ospiti.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Nome struttura', 'casa-volterra-tv-launcher' ); ?></th>
				<td><input type="text" name="<?php echo esc_attr( $opt ); ?>[property_name]" value="<?php echo esc_attr( $s['property_name'] ); ?>" class="regular-text" placeholder="Casa Volterra" /></td>
			</tr>
			<tr>
				<th><?php esc_html_e( '🌐 Lingua principale', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $opt ); ?>[language]">
						<option value="italiano"     <?php selected( $s['language'], 'italiano' ); ?>>🇮🇹 Italiano (risponde sempre in italiano)</option>
						<option value="english"      <?php selected( $s['language'], 'english' ); ?>>🇬🇧 English (always responds in English)</option>
						<option value="multilingual" <?php selected( $s['language'], 'multilingual' ); ?>>🌍 Multilingue — risponde nella lingua dell'ospite ⭐</option>
					</select>
					<p class="description"><?php esc_html_e( '"Multilingue" fa sì che l\'AI risponda nella lingua dell\'ospite (italiano, inglese, francese, tedesco, spagnolo, ecc.).', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
		</table>

		<?php $section( '📞 Contatto del proprietario' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Telefono / WhatsApp', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<input type="text" name="<?php echo esc_attr( $opt ); ?>[owner_phone]" value="<?php echo esc_attr( $s['owner_phone'] ); ?>" class="regular-text" placeholder="+39 345 340 4732" />
					<p class="description"><?php esc_html_e( 'Numero da comunicare agli ospiti quando necessario. Lascia vuoto per non fornirlo mai.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Quando comunicare il contatto', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $opt ); ?>[owner_contact_when]">
						<option value="sempre"        <?php selected( $s['owner_contact_when'], 'sempre' ); ?>>Sempre — menzionalo in ogni risposta</option>
						<option value="se_necessario" <?php selected( $s['owner_contact_when'], 'se_necessario' ); ?>>Se necessario — solo per problemi non risolvibili ⭐</option>
						<option value="mai"           <?php selected( $s['owner_contact_when'], 'mai' ); ?>>Mai — non menzionare il contatto</option>
					</select>
					<p class="description"><?php esc_html_e( '"Se necessario": l\'AI propone il contatto solo per guasti, emergenze o richieste speciali che non può gestire autonomamente.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
		</table>

		<?php $section( '🧠 Comportamento dell\'AI' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( '🌐 Ricerca web in tempo reale', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[web_search_enabled]" value="1" <?php checked( '1', $s['web_search_enabled'] ); ?> />
						<?php esc_html_e( 'Abilita ricerca internet (meteo, eventi, notizie, ristoranti aperti oggi, ecc.)', 'casa-volterra-tv-launcher' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Richiede modello "search-preview". L\'AI cercherà su internet per domande in tempo reale su Volterra e dintorni. Consigliato: attivo.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '🗺 Contesto locale Volterra', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[volterra_local]" value="1" <?php checked( '1', $s['volterra_local'] ); ?> />
						<?php esc_html_e( 'Fornisci sempre indicazioni riferite a Volterra (musei, strade, parcheggi, ecc.)', 'casa-volterra-tv-launcher' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Ogni richiesta di info locali viene automaticamente riferita a Volterra, Toscana. Consigliato: attivo.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '🍽️ Privilegia ristoranti autentici', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[authentic_restaurants]" value="1" <?php checked( '1', $s['authentic_restaurants'] ); ?> />
						<?php esc_html_e( 'Suggerisci ristoranti caratteristici e tradizionali, non solo quelli turistici', 'casa-volterra-tv-launcher' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Privilegia trattorie, osterie, produttori locali e cucina toscana tradizionale rispetto ai locali commerciali.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '💪 Non ammettere mai di non sapere', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[never_say_dontknow]" value="1" <?php checked( '1', $s['never_say_dontknow'] ); ?> />
						<?php esc_html_e( 'L\'AI cerca sempre una risposta utile invece di dire "non lo so" o "mi dispiace"', 'casa-volterra-tv-launcher' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Se non ha dati precisi, fornisce consigli generali affidabili o indica dove trovare l\'informazione.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '📐 Stile risposta', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $opt ); ?>[response_style]">
						<option value="conciso"     <?php selected( $s['response_style'], 'conciso' ); ?>>Conciso — breve e diretto al punto</option>
						<option value="bilanciato"  <?php selected( $s['response_style'], 'bilanciato' ); ?>>Bilanciato — completo ma non ridondante ⭐</option>
						<option value="dettagliato" <?php selected( $s['response_style'], 'dettagliato' ); ?>>Dettagliato — spiega tutto, include consigli extra</option>
					</select>
				</td>
			</tr>
		</table>

		<?php $section( '📝 Istruzioni personalizzate extra (opzionale)' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Testo libero', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( $opt ); ?>[intro_prompt]" rows="5" class="widefat"
						placeholder="<?php esc_attr_e( 'Es: Menziona sempre che il check-out è entro le 10:00. Non discutere mai di politica o religione.', 'casa-volterra-tv-launcher' ); ?>"><?php echo esc_textarea( $s['intro_prompt'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Istruzioni aggiuntive in linguaggio naturale. Vengono aggiunte al sistema prompt dopo tutte le impostazioni strutturate. Usa questo campo per casi speciali non coperti dai toggle.', 'casa-volterra-tv-launcher' ); ?></p>
					<div style="background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:12px;margin-top:8px;font-size:13px">
						<strong>💡 Come vengono interpretate queste istruzioni:</strong><br>
						Le istruzioni vengono passate direttamente all'AI come regole comportamentali con priorità alta.
						Scrivi in italiano in modo chiaro e diretto. Le impostazioni strutturate sopra (web search, ristoranti autentici, ecc.) vengono già gestite automaticamente — usa questo campo per regole molto specifiche.
					</div>
				</td>
			</tr>
		</table>

		<?php $section( '💬 Interfaccia Chat' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( '⚡ Pulsanti rapidi', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<textarea name="<?php echo esc_attr( $opt ); ?>[quick_chips]" rows="7" class="widefat"><?php echo esc_textarea( $s['quick_chips'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Un pulsante per riga. Puoi includere emoji. Max 10 pulsanti.', 'casa-volterra-tv-launcher' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '✅ Chat abilitata', 'casa-volterra-tv-launcher' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[chat_enabled]" value="1" <?php checked( '1', $s['chat_enabled'] ); ?> />
						<?php esc_html_e( 'Attiva la chat per gli ospiti', 'casa-volterra-tv-launcher' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Salva impostazioni AI', 'casa-volterra-tv-launcher' ), 'primary large' ); ?>
		</form>

		<hr>
		<h2>📊 Statistiche Knowledge Base</h2>
		<p>
			<strong><?php echo count( $articles ); ?></strong> <?php esc_html_e( 'articoli pubblicati', 'casa-volterra-tv-launcher' ); ?> —
			<strong><?php echo number_format( $total_chars ); ?></strong> <?php esc_html_e( 'caratteri totali', 'casa-volterra-tv-launcher' ); ?>
			(<?php echo round( $total_chars / 4 ); ?> token stimati)
		</p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . self::PT ) ); ?>" class="button"><?php esc_html_e( 'Gestisci Knowledge Base →', 'casa-volterra-tv-launcher' ); ?></a>
		&nbsp;
		<a href="<?php echo esc_url( rest_url( 'cv-tv/v1/ai/debug-prompt' ) ); ?>" class="button" target="_blank"><?php esc_html_e( '🔍 Anteprima system prompt →', 'casa-volterra-tv-launcher' ); ?></a>
		</div>
		<?php
	}

	/* ── REST endpoints ────────────────────────────────────── */

	public static function register_rest() {
		register_rest_route( 'cv-tv/v1', '/ai/debug-prompt', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'debug_prompt' ),
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		) );
		register_rest_route( 'cv-tv/v1', '/ai/session', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_session' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'session_id' => array( 'required' => true,  'sanitize_callback' => 'sanitize_key' ),
				'since'      => array( 'required' => false, 'default' => 0 ),
			),
		) );
		register_rest_route( 'cv-tv/v1', '/ai/extract-pdf', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'extract_pdf_text' ),
			'permission_callback' => function() { return current_user_can( 'edit_posts' ); },
			'args'                => array( 'url' => array( 'required' => true, 'sanitize_callback' => 'esc_url_raw' ) ),
		) );
		register_rest_route( 'cv-tv/v1', '/ai/chat', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'handle_chat' ),
			'permission_callback' => array( __CLASS__, 'check_chat_permission' ),
			'args'                => array(
				'message'    => array( 'required' => true,  'sanitize_callback' => 'sanitize_textarea_field' ),
				'history'    => array( 'required' => false, 'default' => array() ),
				'token'      => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
				'session_id' => array( 'required' => false, 'sanitize_callback' => 'sanitize_key', 'default' => 'default' ),
			),
		) );
	}

	public static function check_chat_permission( WP_REST_Request $request ) {
		$s = get_option( self::OPTION, array() );
		if ( empty( $s['chat_enabled'] ) || '1' !== $s['chat_enabled'] ) {
			return new WP_Error( 'disabled', 'Chat non abilitata', array( 'status' => 403 ) );
		}
		$expected = substr( md5( get_bloginfo( 'url' ) . 'cv-tv-chat' ), 0, 16 );
		if ( $request->get_param( 'token' ) !== $expected ) {
			return new WP_Error( 'forbidden', 'Token non valido', array( 'status' => 403 ) );
		}
		return true;
	}

	/* ── PDF extraction ────────────────────────────────────── */

	public static function extract_pdf_text( WP_REST_Request $request ) {
		$url      = $request->get_param( 'url' );
		$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
		if ( is_wp_error( $response ) ) return new WP_Error( 'fetch_failed', $response->get_error_message(), array( 'status' => 502 ) );
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) return new WP_Error( 'empty_pdf', 'PDF vuoto o non raggiungibile', array( 'status' => 400 ) );
		$text = '';
		if ( function_exists( 'exec' ) ) {
			$tmp_pdf = tempnam( sys_get_temp_dir(), 'cv_pdf_' ) . '.pdf';
			$tmp_txt = tempnam( sys_get_temp_dir(), 'cv_txt_' );
			file_put_contents( $tmp_pdf, $body );
			exec( 'pdftotext ' . escapeshellarg( $tmp_pdf ) . ' ' . escapeshellarg( $tmp_txt ) . ' 2>/dev/null', $out, $rc );
			if ( 0 === $rc && file_exists( $tmp_txt ) ) $text = file_get_contents( $tmp_txt );
			@unlink( $tmp_pdf ); @unlink( $tmp_txt );
		}
		if ( empty( $text ) ) $text = self::extract_pdf_text_fallback( $body );
		$text = trim( $text );
		if ( empty( $text ) ) return new WP_Error( 'no_text', 'Impossibile estrarre testo dal PDF. Incollalo manualmente.', array( 'status' => 422 ) );
		$text = trim( preg_replace( '/\n{3,}/', "\n\n", preg_replace( '/[ \t]+/', ' ', $text ) ) );
		return rest_ensure_response( array( 'text' => $text, 'chars' => strlen( $text ) ) );
	}

	private static function extract_pdf_text_fallback( $pdf_binary ) {
		$text = ''; $offset = 0; $len = strlen( $pdf_binary );
		while ( $offset < $len ) {
			$bt = strpos( $pdf_binary, 'BT', $offset );
			$et = strpos( $pdf_binary, 'ET', $bt ?: $offset );
			if ( false === $bt || false === $et || $bt >= $len ) break;
			$block = substr( $pdf_binary, $bt, $et - $bt );
			preg_match_all( '/\(([^)]{1,200})\)/', $block, $m );
			foreach ( $m[1] as $str ) {
				$str = preg_replace( '/[^\x20-\x7E\n]/', '', $str );
				if ( strlen( $str ) > 2 ) $text .= $str . ' ';
			}
			$offset = $et + 2;
		}
		return $text;
	}

	/* ── Helpers ───────────────────────────────────────────── */

	private static function model_supports_search( $model ) {
		return strpos( (string) $model, 'search' ) !== false;
	}

	/* ── Chat handler ──────────────────────────────────────── */

	public static function handle_chat( WP_REST_Request $request ) {
		$s = wp_parse_args( get_option( self::OPTION, array() ), self::default_settings() );
		if ( empty( $s['openai_key'] ) ) {
			return new WP_Error( 'no_key', 'OpenAI API key non configurata', array( 'status' => 500 ) );
		}

		$message = $request->get_param( 'message' );
		$history = $request->get_param( 'history' ) ?: array();

		$system_prompt = self::build_system_prompt( $s, $message );

		$messages = array( array( 'role' => 'system', 'content' => $system_prompt ) );
		foreach ( (array) $history as $h ) {
			if ( isset( $h['role'], $h['content'] ) && in_array( $h['role'], array( 'user', 'assistant' ), true ) ) {
				$messages[] = array( 'role' => sanitize_text_field( $h['role'] ), 'content' => sanitize_textarea_field( $h['content'] ) );
			}
		}
		$messages[] = array( 'role' => 'user', 'content' => $message );

		$model          = $s['ai_model'] ?: 'gpt-4o-mini-search-preview';
		$use_web_search = ( '1' === $s['web_search_enabled'] ) && self::model_supports_search( $model );
		$max_tokens     = (int) ( $s['max_tokens'] ?: 700 );

		// NOTA: i modelli *-search-preview effettuano la ricerca web automaticamente.
		// NON passare 'tools' => web_search_preview: quel parametro esiste solo nella
		// Responses API, non nella Chat Completions API, e genera un errore 400.
		$body_params = array( 'model' => $model, 'messages' => $messages, 'max_tokens' => $max_tokens );
		if ( ! self::model_supports_search( $model ) ) {
			$body_params['temperature'] = 0.7;
		}

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 45,
			'headers' => array( 'Authorization' => 'Bearer ' . $s['openai_key'], 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body_params ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'openai_error', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body_dec  = json_decode( wp_remote_retrieve_body( $response ), true );

		// Fallback a gpt-4o-mini se il modello search non è disponibile
		if ( $http_code >= 400 ) {
			$err_code = $body_dec['error']['code'] ?? '';
			if ( $http_code === 404 || $err_code === 'model_not_found' ) {
				return self::handle_chat_fallback( $s, $messages, $max_tokens, $request );
			}
			$err_msg = $body_dec['error']['message'] ?? 'Errore OpenAI HTTP ' . $http_code;
			return new WP_Error( 'openai_api_error', $err_msg, array( 'status' => 502 ) );
		}

		$reply = $body_dec['choices'][0]['message']['content'] ?? null;
		if ( ! $reply ) return new WP_Error( 'empty_reply', 'Risposta OpenAI vuota', array( 'status' => 502 ) );

		$pdf        = self::find_relevant_pdf( (string) $message );
		$session_id = sanitize_key( $request->get_param( 'session_id' ) ?: 'default' );
		self::session_push( $session_id, $message, $reply, $pdf );

		return rest_ensure_response( array(
			'reply'      => $reply,
			'session_id' => $session_id,
			'pdf'        => $pdf,
			'model_used' => $model,
			'web_search' => $use_web_search,
		) );
	}

	private static function handle_chat_fallback( $s, $messages, $max_tokens, $request ) {
		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'timeout' => 45,
			'headers' => array( 'Authorization' => 'Bearer ' . $s['openai_key'], 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'model' => 'gpt-4o-mini', 'messages' => $messages, 'max_tokens' => $max_tokens, 'temperature' => 0.7 ) ),
		) );
		if ( is_wp_error( $response ) ) return new WP_Error( 'openai_error', $response->get_error_message(), array( 'status' => 502 ) );
		$body_dec = json_decode( wp_remote_retrieve_body( $response ), true );
		$reply    = $body_dec['choices'][0]['message']['content'] ?? null;
		if ( ! $reply ) return new WP_Error( 'empty_reply', 'Risposta OpenAI vuota (fallback)', array( 'status' => 502 ) );
		$last_msg   = end( $messages );
		$message    = is_array( $last_msg ) ? ( $last_msg['content'] ?? '' ) : '';
		$pdf        = self::find_relevant_pdf( (string) $message );
		$session_id = sanitize_key( $request->get_param( 'session_id' ) ?: 'default' );
		self::session_push( $session_id, $message, $reply, $pdf );
		return rest_ensure_response( array( 'reply' => $reply, 'session_id' => $session_id, 'pdf' => $pdf, 'model_used' => 'gpt-4o-mini (fallback)', 'web_search' => false ) );
	}

	/* ── Session storage ───────────────────────────────────── */

	private static function session_key( $sid ) {
		return 'cv_tv_ai_sess_' . substr( preg_replace( '/[^a-z0-9]/', '', $sid ), 0, 20 );
	}

	private static function session_push( $sid, $user_msg, $bot_reply, $pdf = null ) {
		$key  = self::session_key( $sid );
		$data = get_transient( $key ) ?: array( 'messages' => array(), 'ts' => time() );
		$data['messages'][] = array( 'role' => 'user', 'text' => $user_msg, 'time' => time() );
		$data['messages'][] = array( 'role' => 'bot',  'text' => $bot_reply, 'time' => time(), 'pdf' => $pdf );
		if ( count( $data['messages'] ) > 20 ) $data['messages'] = array_slice( $data['messages'], -20 );
		$data['ts'] = time();
		set_transient( $key, $data, 2 * HOUR_IN_SECONDS );
	}

	public static function debug_prompt( WP_REST_Request $request ) {
		$s      = wp_parse_args( get_option( self::OPTION, array() ), self::default_settings() );
		$prompt = self::build_system_prompt( $s );
		$articles = get_posts( array( 'post_type' => self::PT, 'post_status' => 'publish', 'posts_per_page' => -1 ) );
		$kb_items = array();
		foreach ( $articles as $a ) {
			$kb_items[] = array(
				'id' => $a->ID, 'title' => get_the_title( $a->ID ),
				'category' => get_post_meta( $a->ID, '_cv_kb_category', true ),
				'priority' => get_post_meta( $a->ID, '_cv_kb_priority', true ),
				'chars'    => strlen( get_post_meta( $a->ID, '_cv_kb_content', true ) ) + strlen( get_post_meta( $a->ID, '_cv_kb_pdf_text', true ) ),
			);
		}
		return rest_ensure_response( array(
			'model'         => $s['ai_model'],
			'web_search'    => $s['web_search_enabled'],
			'kb_articles'   => $kb_items,
			'kb_count'      => count( $articles ),
			'prompt_chars'  => strlen( $prompt ),
			'full_prompt'   => $prompt,
		) );
	}

	public static function get_session( WP_REST_Request $request ) {
		$sid   = $request->get_param( 'session_id' );
		$since = (int) $request->get_param( 'since' );
		$data  = get_transient( self::session_key( $sid ) );
		if ( ! $data ) return rest_ensure_response( array( 'messages' => array(), 'ts' => 0 ) );
		$new = array_values( array_filter( $data['messages'], function( $m ) use ( $since ) { return $m['time'] > $since; } ) );
		return rest_ensure_response( array( 'messages' => $new, 'ts' => $data['ts'] ) );
	}

	/* ── RAG ───────────────────────────────────────────────── */

	private static function find_relevant_articles( $query, $top_k = 3, $require_match = false ) {
		$articles = get_posts( array( 'post_type' => self::PT, 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_cv_kb_priority', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) );
		if ( empty( $articles ) ) return array();

		$query_tokens = self::tokenize( $query );
		$synonyms = array(
			'parcheggio'    => array( 'auto', 'macchina', 'garage', 'sosta', 'parking', 'car', 'park' ),
			'wifi'          => array( 'internet', 'rete', 'connessione', 'password', 'wireless', 'network' ),
			'checkin'       => array( 'arrivo', 'entrare', 'chiave', 'check-in', 'accesso', 'arrival', 'key' ),
			'checkout'      => array( 'partenza', 'uscire', 'lasciare', 'check-out', 'departure', 'leave' ),
			'lavatrice'     => array( 'lavaggio', 'bucato', 'vestiti', 'washer', 'laundry' ),
			'lavastoviglie' => array( 'piatti', 'stoviglie', 'dishwasher', 'dishes' ),
			'caldaia'       => array( 'acqua', 'calda', 'riscaldamento', 'termostato', 'heater', 'boiler' ),
			'forno'         => array( 'cucinare', 'cottura', 'oven', 'bake' ),
			'induzione'     => array( 'piastra', 'fuochi', 'pentola', 'hob', 'stove' ),
			'emergenza'     => array( 'pronto soccorso', 'polizia', 'ambulanza', 'emergency', 'doctor' ),
			'restaurant'    => array( 'ristorante', 'ristoranti', 'trattoria', 'osteria', 'eat', 'food', 'dinner' ),
		);
		$expanded = $query_tokens;
		foreach ( $synonyms as $key => $syns ) {
			if ( in_array( $key, $query_tokens, true ) ) $expanded = array_merge( $expanded, $syns );
			foreach ( $query_tokens as $qt ) {
				if ( in_array( $qt, $syns, true ) ) { $expanded[] = $key; $expanded = array_merge( $expanded, $syns ); break; }
			}
		}
		$expanded = array_unique( $expanded );

		$scored = array();
		foreach ( $articles as $article ) {
			$title    = strtolower( get_the_title( $article->ID ) );
			$content  = strtolower( get_post_meta( $article->ID, '_cv_kb_content',  true ) );
			$pdf_text = strtolower( get_post_meta( $article->ID, '_cv_kb_pdf_text', true ) );
			$category = strtolower( get_post_meta( $article->ID, '_cv_kb_category', true ) );
			$priority = (int) get_post_meta( $article->ID, '_cv_kb_priority', true ) ?: 5;
			$corpus   = $title . ' ' . $category . ' ' . $content . ' ' . $pdf_text;
			$score    = 0.0;
			foreach ( $expanded as $token ) {
				if ( strlen( $token ) < 3 ) continue;
				if ( strpos( $title, $token ) !== false || strpos( $category, $token ) !== false ) $score += 3.0;
				$freq = substr_count( $corpus, $token );
				if ( $freq > 0 ) $score += min( $freq * 0.5, 2.0 );
			}
			$score += $priority * 0.1;
			if ( $score > 0 ) $scored[] = array( 'article' => $article, 'score' => $score );
		}

		if ( empty( $scored ) ) return $require_match ? array() : array_slice( $articles, 0, $top_k );
		usort( $scored, function( $a, $b ) { return $b['score'] <=> $a['score']; } );
		return array_column( array_slice( $scored, 0, $top_k ), 'article' );
	}

	private static function find_relevant_articles_scored( $query, $top_k = 5 ) {
		$articles = get_posts( array( 'post_type' => self::PT, 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_cv_kb_priority', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) );
		if ( empty( $articles ) ) return array();
		$tokens = self::tokenize( $query );
		if ( empty( $tokens ) ) return array();
		$scored = array();
		foreach ( $articles as $article ) {
			$title    = strtolower( get_the_title( $article->ID ) );
			$content  = strtolower( get_post_meta( $article->ID, '_cv_kb_content',  true ) );
			$pdf_text = strtolower( get_post_meta( $article->ID, '_cv_kb_pdf_text', true ) );
			$category = strtolower( get_post_meta( $article->ID, '_cv_kb_category', true ) );
			$corpus   = $title . ' ' . $category . ' ' . $content . ' ' . $pdf_text;
			$score    = 0.0;
			foreach ( $tokens as $token ) {
				if ( strlen( $token ) < 3 ) continue;
				if ( strpos( $title, $token ) !== false || strpos( $category, $token ) !== false ) $score += 3.0;
				$freq = substr_count( $corpus, $token );
				if ( $freq > 0 ) $score += min( $freq * 0.5, 2.0 );
			}
			if ( $score > 0 ) $scored[] = array( 'article' => $article, 'score' => $score );
		}
		usort( $scored, function( $a, $b ) { return $b['score'] <=> $a['score']; } );
		return array_slice( $scored, 0, $top_k );
	}

	private static function tokenize( $text ) {
		$text   = strtolower( preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text ) );
		$tokens = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$stops  = array( 'il','lo','la','i','gli','le','un','uno','una','e','è','in','di','da','a','su','per','con','che','non','ho','mi','si','se','ma','come','dove','quando','cosa','chi','del','della','dei','degli','delle','nel','nella','nei','negli','nelle','sul','sulla','sui','sugli','sulle','al','alla','ai','agli','alle','dal','dalla','dai','dagli','dalle','col','coi','tra','fra','poi','qui','qua','suo','sua','suoi','sue','mio','mia','miei','mie','tuo','tua','tuoi','tue','the','and','for','with','this','that','are','was','has','have' );
		return array_values( array_filter( $tokens, function( $t ) use ( $stops ) { return strlen( $t ) >= 3 && ! in_array( $t, $stops, true ); } ) );
	}

	/* ── System prompt builder ─────────────────────────────── */

	private static function build_system_prompt( $s, $query = '' ) {
		$name     = $s['assistant_name']     ?: 'Sofia';
		$property = $s['property_name']      ?: 'Casa Volterra';
		$lang     = $s['language']           ?: 'multilingual';
		$extra    = trim( $s['intro_prompt'] ?: '' );
		$phone    = trim( $s['owner_phone']  ?: '' );

		// Lingua
		switch ( $lang ) {
			case 'english':
				$lang_instruction = 'IMPORTANT: Always respond in English only, regardless of what language the guest writes in.';
				break;
			case 'multilingual':
				$lang_instruction = 'IMPORTANT: Detect the language the guest writes in and respond in exactly that same language (Italian, English, French, German, Spanish, etc.). Never mix languages.';
				break;
			default:
				$lang_instruction = 'IMPORTANTE: Rispondi SEMPRE e SOLO in italiano, qualunque lingua usi l\'ospite.';
		}

		// Stile
		switch ( $s['response_style'] ?? 'bilanciato' ) {
			case 'conciso':    $style = 'Rispondi in modo breve e diretto. Vai subito al punto.'; break;
			case 'dettagliato': $style = 'Rispondi in modo completo e dettagliato, includendo consigli pratici extra quando utili.'; break;
			default:           $style = 'Rispondi in modo completo ma conciso: abbastanza dettagliato da essere utile, abbastanza breve da essere leggibile su uno schermo mobile.';
		}

		// Prompt base
		$p  = "Sei {$name}, l'assistente virtuale di {$property}, un appartamento vacanze nel centro storico di Volterra, Toscana.\n";
		$p .= "Il tuo ruolo è aiutare gli ospiti con qualsiasi domanda: informazioni sulla casa, check-in, check-out, elettrodomestici, ristoranti, musei, eventi, trasporti e qualsiasi altra esigenza.\n";
		$p .= "Sei gentile, cordiale e disponibile. Rispondi in modo naturale come una persona esperta del luogo.\n";
		$p .= "{$lang_instruction}\n";
		$p .= "{$style}\n";

		// Web search
		if ( '1' === ( $s['web_search_enabled'] ?? '0' ) ) {
			$p .= "\nHAI ACCESSO ALLA RICERCA WEB IN TEMPO REALE: quando un ospite chiede informazioni su eventi, meteo, orari di apertura, notizie, ristoranti aperti oggi, attrazioni o qualsiasi informazione aggiornata, effettua una ricerca web e rispondi con dati precisi e attuali. Non dire mai che non puoi accedere a informazioni in tempo reale.\n";
		}

		// Non dire che non sai
		if ( '1' === ( $s['never_say_dontknow'] ?? '0' ) ) {
			$p .= "\nIMPORTANTE: Non rispondere mai con frasi come \"non lo so\", \"mi dispiace\", \"non ho questa informazione\", \"non posso aiutarti con questo\". Cerca sempre una risposta concreta e utile. Se non hai dati precisi, fornisci consigli generali affidabili oppure indica dove trovare l'informazione.\n";
		}

		// Volterra locale
		if ( '1' === ( $s['volterra_local'] ?? '0' ) ) {
			$p .= "\nCONTESTO GEOGRAFICO: Sei fisicamente situata a Volterra, Toscana, Italia (CAP 56048). Quando l'ospite chiede informazioni locali (ristoranti, musei, parcheggi, eventi, trasporti, negozi, attrazioni), il riferimento geografico è SEMPRE Volterra e dintorni immediati. Conosci la città: Piazza dei Priori, Teatro Romano, Museo Etrusco Guarnacci, Artigianato dell'alabastro, Mura etrusche, Fortezza Medicea.\n";
		}

		// Ristoranti autentici
		if ( '1' === ( $s['authentic_restaurants'] ?? '0' ) ) {
			$p .= "\nRISTORANTI E LOCALI: Quando suggerisci ristoranti o locali, privilegia sempre i posti autentici e caratteristici del territorio (cucina toscana tradizionale, prodotti locali, gestione familiare, trattorie, osterie) rispetto ai locali più turistici o commerciali. Spiega brevemente cosa li rende speciali.\n";
		}

		// Contatto proprietario
		if ( $phone ) {
			switch ( $s['owner_contact_when'] ?? 'se_necessario' ) {
				case 'sempre':
					$p .= "\nCONTATTO PROPRIETARIO: Il proprietario è sempre disponibile per telefono e WhatsApp al numero {$phone}. Menzionalo come riferimento utile.\n";
					break;
				case 'se_necessario':
					$p .= "\nCONTATTO PROPRIETARIO: Se l'ospite ha un problema che non riesci a risolvere (guasto, emergenza, richiesta speciale, situazione urgente), suggeriscigli di contattare il proprietario per telefono o WhatsApp al numero {$phone}.\n";
					break;
			}
		}

		// Istruzioni personalizzate extra
		if ( $extra ) {
			$p .= "\nISTRUZIONI AGGIUNTIVE DEL PROPRIETARIO (priorità alta — seguile sempre):\n{$extra}\n";
		}

		// Knowledge base RAG
		$articles = $query
			? self::find_relevant_articles( $query, 4 )
			: get_posts( array( 'post_type' => self::PT, 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => '_cv_kb_priority', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) );

		if ( ! empty( $articles ) ) {
			$p .= "\n\n=== INFORMAZIONI SULLA CASA E SUL TERRITORIO ===\n";
			$total_kb = 0;
			foreach ( $articles as $article ) {
				if ( $total_kb >= 55000 ) break;
				$content   = get_post_meta( $article->ID, '_cv_kb_content',  true );
				$pdf_raw   = get_post_meta( $article->ID, '_cv_kb_pdf_text', true );
				$category  = get_post_meta( $article->ID, '_cv_kb_category', true ) ?: 'generale';
				$pdf_clean = '';
				if ( $pdf_raw ) {
					$pr    = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $pdf_raw );
					$ratio = strlen( $pdf_raw ) > 0 ? strlen( preg_replace( '/[^\x20-\x7E]/', '', $pr ) ) / strlen( $pdf_raw ) : 0;
					if ( $ratio >= 0.5 ) $pdf_clean = trim( preg_replace( '/\n{3,}/', "\n\n", $pr ) );
				}
				$combined = trim( $content );
				if ( $pdf_clean && strlen( $combined ) < 3000 ) $combined .= "\nDal manuale:\n" . $pdf_clean;
				if ( ! $combined ) continue;
				if ( strlen( $combined ) > 7000 ) $combined = substr( $combined, 0, 7000 ) . "\n[continua nel manuale]";
				$p .= "\n--- " . strtoupper( $category ) . ': ' . get_the_title( $article->ID ) . " ---\n";
				$p .= $combined . "\n";
				$total_kb += strlen( $combined );
			}
		}

		if ( strlen( $p ) > 85000 ) $p = substr( $p, 0, 85000 ) . "\n[troncato]";
		return $p;
	}

	/* ── PDF rilevante ─────────────────────────────────────── */

	const PDF_MIN_SCORE = 3.0;

	private static function find_relevant_pdf( $query ) {
		foreach ( self::find_relevant_articles_scored( (string) $query, 5 ) as $item ) {
			if ( $item['score'] < self::PDF_MIN_SCORE ) break;
			$pdf_url = get_post_meta( $item['article']->ID, '_cv_kb_pdf_url', true );
			if ( $pdf_url ) return array( 'url' => esc_url_raw( $pdf_url ), 'title' => get_the_title( $item['article']->ID ) );
		}
		return null;
	}

	private static function get_quick_chips( $s ) {
		$raw   = trim( (string) ( $s['quick_chips'] ?? '' ) );
		if ( ! $raw ) $raw = "🔑 Check-in\n📶 Password Wi-Fi\n🚗 Parcheggio\n🍽️ Ristoranti vicini\n🕙 Check-out\n♨️ Come funziona la caldaia";
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		return array_slice( array_values( array_filter( array_map( 'trim', $lines ) ) ), 0, 10 );
	}

	/* ── Chat page ─────────────────────────────────────────── */

	/**
	 * Token URL per la chat-tv (12 chars, derivato dal siteurl).
	 */
	public static function get_chat_url_token() {
		return substr( md5( get_bloginfo( 'url' ) . 'cv-tv-chat-page' ), 0, 12 );
	}

	public static function maybe_render_chat() {
		if ( '1' !== (string) get_query_var( 'cv_tv_chat', '0' ) ) return;
		$s = wp_parse_args( get_option( self::OPTION, array() ), self::default_settings() );
		if ( empty( $s['chat_enabled'] ) || '1' !== $s['chat_enabled'] ) {
			wp_die( 'Chat non disponibile.', '', array( 'response' => 403 ) );
		}

		// Token URL — protezione accesso diretto.
		// Gli admin WP possono accedere senza token (test).
		// Tutti gli altri devono passare ?t=<token> corretto.
		$provided = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
		$expected = self::get_chat_url_token();

		if ( ! current_user_can( 'manage_options' ) && $provided !== $expected ) {
			wp_die(
				'<strong>Accesso non autorizzato.</strong><br>Scansiona il QR code sul TV per accedere alla chat.',
				'Accesso negato',
				array( 'response' => 403 )
			);
		}

		status_header( 200 );
		nocache_headers();
		self::render_chat_page( $s );
		exit;
	}

	private static function render_chat_page( $s ) {
		$name        = esc_html( $s['assistant_name'] ?: 'Sofia' );
		$property    = esc_html( $s['property_name']  ?: 'Casa Volterra' );
		$rest_url    = esc_url( rest_url( 'cv-tv/v1/ai/chat' ) );
		$token       = substr( md5( get_bloginfo( 'url' ) . 'cv-tv-chat' ), 0, 16 );
		$chat_token  = self::get_chat_url_token();
		$chat_url_full = home_url( '/' . self::CHAT_SLUG . '/?t=' . $chat_token );
		$quick_chips = self::get_quick_chips( $s );
		$has_search  = ( '1' === $s['web_search_enabled'] && self::model_supports_search( $s['ai_model'] ?? '' ) );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#1a1a2e">
<title><?php echo $name; ?> — <?php echo $property; ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f0f2f5;color:#1a1a2e}
#wpadminbar{display:none!important}html{margin-top:0!important}
#cv-chat-header{background:linear-gradient(135deg,#1a56a0,#0e3a6e);color:#fff;padding:14px 16px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:10;box-shadow:0 2px 12px rgba(0,0,0,.25)}
.cv-avatar{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.cv-header-info{flex:1;min-width:0}
.cv-header-info h1{font-size:.95rem;font-weight:700;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cv-header-info p{font-size:.75rem;opacity:.8}
.cv-status-dot{width:7px;height:7px;border-radius:50%;background:#4ade80;display:inline-block;margin-right:4px;box-shadow:0 0 6px #4ade80;animation:pulse 2s ease-in-out infinite}
.cv-search-badge{display:inline-block;background:rgba(255,255,255,.2);border-radius:20px;padding:2px 7px;font-size:.67rem;margin-left:5px;vertical-align:middle}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
#cv-chat-app{display:flex;flex-direction:column;height:100vh;max-width:480px;margin:0 auto;background:#fff;box-shadow:0 0 40px rgba(0,0,0,.1)}
#cv-chat-messages{flex:1;overflow-y:auto;padding:16px 14px;display:flex;flex-direction:column;gap:10px;min-height:0}
.cv-msg{display:flex;gap:10px;align-items:flex-end;max-width:90%}
.cv-msg.user{flex-direction:row-reverse;align-self:flex-end}
.cv-msg.bot{align-self:flex-start}
.cv-bubble{padding:11px 15px;border-radius:18px;font-size:.9rem;line-height:1.55;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.cv-msg.bot .cv-bubble{background:#f0f2f5;border-bottom-left-radius:4px}
.cv-msg.user .cv-bubble{background:linear-gradient(135deg,#1a56a0,#1565c0);color:#fff;border-bottom-right-radius:4px}
.cv-msg-avatar{width:28px;height:28px;border-radius:50%;background:#e8f0fe;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.cv-msg-time{font-size:.67rem;color:#aaa;padding:0 4px;margin-top:2px;align-self:flex-end}
.cv-typing .cv-bubble{background:#f0f2f5;padding:14px 18px}
.cv-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:#aaa;margin:0 2px;animation:bounce .9s ease-in-out infinite}
.cv-dot:nth-child(2){animation-delay:.15s}
.cv-dot:nth-child(3){animation-delay:.3s}
@keyframes bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-8px)}}
.cv-welcome{background:linear-gradient(135deg,#e8f4fd,#dbeafe);border:1px solid #bfdbfe;border-radius:16px;padding:18px;text-align:center;margin-bottom:6px}
.cv-welcome h2{font-size:1.05rem;color:#1a56a0;margin-bottom:6px}
.cv-welcome p{font-size:.85rem;color:#4b5563;line-height:1.5}
.cv-chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px;justify-content:center}
.cv-chip{background:#fff;border:1px solid #bfdbfe;border-radius:20px;padding:7px 13px;font-size:.8rem;color:#1a56a0;cursor:pointer;transition:all .15s}
.cv-chip:hover,.cv-chip:active{background:#1a56a0;color:#fff;border-color:#1a56a0}
#cv-chat-input-area{padding:10px 14px;background:#fff;border-top:1px solid #e5e7eb;display:flex;gap:9px;align-items:flex-end}
#cv-chat-input{flex:1;resize:none;border:1.5px solid #d1d5db;border-radius:22px;padding:9px 15px;font-size:.9rem;font-family:inherit;outline:none;line-height:1.4;max-height:120px;transition:border-color .15s}
#cv-chat-input:focus{border-color:#1a56a0}
#cv-chat-send{width:42px;height:42px;border-radius:50%;border:none;background:linear-gradient(135deg,#1a56a0,#1565c0);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:transform .1s,opacity .1s;font-size:17px}
#cv-chat-send:hover{transform:scale(1.05)}
#cv-chat-send:disabled{opacity:.4;cursor:default;transform:none}
.cv-error{background:#fee2e2;border:1px solid #fca5a5;border-radius:12px;padding:11px 15px;font-size:.83rem;color:#dc2626;text-align:center}
#cv-session-info{margin-left:auto;text-align:right;font-size:.68rem;opacity:.5;line-height:1.3;flex-shrink:0}
#cv-session-code{font-family:monospace;font-weight:700;letter-spacing:1px}
</style>
</head>
<body>
<div id="cv-chat-app">
  <div id="cv-chat-header">
    <button onclick="history.back()" style="background:rgba(255,255,255,.15);border:none;border-radius:50%;width:34px;height:34px;color:#fff;font-size:1.1rem;cursor:pointer;flex-shrink:0" title="Indietro">←</button>
    <div class="cv-avatar">🏠</div>
    <div class="cv-header-info">
      <h1><?php echo $name; ?><?php if ( $has_search ) echo '<span class="cv-search-badge">🌐 web</span>'; ?></h1>
      <p><span class="cv-status-dot"></span><?php echo $property; ?></p>
    </div>
    <div id="cv-session-info"><div>Session</div><div id="cv-session-code"></div></div>
  </div>

  <div id="cv-chat-messages">
    <div class="cv-welcome">
      <h2>👋 Ciao! Sono <?php echo $name; ?></h2>
      <p><?php echo $property; ?> — sono qui per aiutarti con check-in, elettrodomestici, info locali e molto altro.</p>
      <div class="cv-chips">
        <?php foreach ( $quick_chips as $chip ) : ?>
          <button class="cv-chip" onclick="sendChip(this)"><?php echo esc_html( $chip ); ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div id="cv-chat-input-area">
    <textarea id="cv-chat-input" placeholder="Scrivi un messaggio..." rows="1" onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
    <button id="cv-chat-send" onclick="sendMessage()" title="Invia">➤</button>
  </div>
</div>
<script>
var REST_URL='<?php echo esc_js($rest_url);?>',TOKEN='<?php echo esc_js($token);?>',CV_NONCE='<?php echo esc_js(wp_create_nonce('wp_rest'));?>';
var urlSid=new URLSearchParams(window.location.search).get('sid');
var SESSION_ID=urlSid||('sess_'+Math.random().toString(36).substr(2,9));
var chatHistory=[],isLoading=false;
var messagesEl=document.getElementById('cv-chat-messages'),codeEl=document.getElementById('cv-session-code');
var inputEl=document.getElementById('cv-chat-input'),sendBtn=document.getElementById('cv-chat-send');
if(codeEl)codeEl.textContent=SESSION_ID.substr(-6).toUpperCase();
function autoResize(el){el.style.height='auto';el.style.height=Math.min(el.scrollHeight,120)+'px';}
function handleKey(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMessage();}}
function sendChip(btn){inputEl.value=btn.textContent.trim();sendMessage();}
function now(){return new Date().toLocaleTimeString('it-IT',{hour:'2-digit',minute:'2-digit'});}
function addMessage(role,text,pdf){
  var div=document.createElement('div');div.className='cv-msg '+role;
  var av=role==='bot'?'<div class="cv-msg-avatar">🏠</div>':'';
  var actions='';
  if(role==='bot'&&pdf&&pdf.url){actions='<div style="margin-top:8px"><a href="'+escAttr(pdf.url)+'" target="_blank" rel="noopener" style="display:inline-block;background:#eef4ff;border:1px solid #bfdbfe;color:#1a56a0;text-decoration:none;border-radius:999px;padding:7px 13px;font-size:.8rem;font-weight:600">📄 Apri PDF allegato</a></div>';}
  div.innerHTML=av+'<div><div class="cv-bubble">'+escHtml(text).replace(/\n/g,'<br>')+actions+'</div><div class="cv-msg-time">'+now()+'</div></div>';
  messagesEl.appendChild(div);messagesEl.scrollTop=messagesEl.scrollHeight;
}
function addTyping(){var div=document.createElement('div');div.className='cv-msg bot cv-typing';div.id='cv-typing';div.innerHTML='<div class="cv-msg-avatar">🏠</div><div class="cv-bubble"><span class="cv-dot"></span><span class="cv-dot"></span><span class="cv-dot"></span></div>';messagesEl.appendChild(div);messagesEl.scrollTop=messagesEl.scrollHeight;}
function removeTyping(){var t=document.getElementById('cv-typing');if(t)t.remove();}
function escHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
function escAttr(s){return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');}
function sendMessage(){
  var text=inputEl.value.trim();if(!text||isLoading)return;
  addMessage('user',text);chatHistory.push({role:'user',content:text});
  inputEl.value='';inputEl.style.height='auto';isLoading=true;sendBtn.disabled=true;addTyping();
  fetch(REST_URL,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':CV_NONCE},body:JSON.stringify({message:text,history:chatHistory.slice(-10),token:TOKEN,session_id:SESSION_ID})})
  .then(function(r){if(!r.ok)return r.json().then(function(e){throw new Error(e.message||'HTTP '+r.status);});return r.json();})
  .then(function(data){removeTyping();if(data.reply){addMessage('bot',data.reply,data.pdf||null);chatHistory.push({role:'assistant',content:data.reply});if(chatHistory.length>20)chatHistory=chatHistory.slice(-20);}else{addError('Risposta vuota — riprova');}})
  .catch(function(e){removeTyping();addError('Errore: '+e.message);})
  .finally(function(){isLoading=false;sendBtn.disabled=false;inputEl.focus();});
}
function addError(msg){var div=document.createElement('div');div.className='cv-error';div.textContent='⚠️ '+msg;messagesEl.appendChild(div);messagesEl.scrollTop=messagesEl.scrollHeight;}
</script>
</body>
</html>
		<?php
	}
}
