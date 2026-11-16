<?php
/**
 * Template library — pattern predefiniti per creare schermate TV in un click.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Templates {

	public static function init() {
		add_action( 'admin_menu',                          array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_cv_tv_use_template',       array( __CLASS__, 'handle_use_template' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=cv_tv_screen',
			__( 'Template schermate', 'casa-volterra-tv-launcher' ),
			__( 'Template', 'casa-volterra-tv-launcher' ),
			'manage_options',
			'cv-tv-templates',
			array( __CLASS__, 'render_page' )
		);
	}

	/* ── Template definitions ───────────────────────────────── */

	public static function get_templates() {
		return array(
			array(
				'slug'        => 'welcome_minimal',
				'name'        => __( 'Welcome minimal', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Titolo grande, sottotitolo, orologio. Sfondo scuro. Ideale come schermata standby sempre attiva.', 'casa-volterra-tv-launcher' ),
				'icon'        => '🌙',
				'meta'        => array(
					'_cv_tv_eyebrow'       => 'Benvenuti a Volterra',
					'_cv_tv_title'         => 'Casa Volterra',
					'_cv_tv_subtitle'      => 'Il tuo appartamento nel cuore della città etrusca.',
					'_cv_tv_show_clock'    => '1',
					'_cv_tv_show_weather'  => '1',
					'_cv_tv_weather_city'  => 'Volterra',
					'_cv_tv_overlay'       => '65',
				),
			),
			array(
				'slug'        => 'welcome_video',
				'name'        => __( 'Welcome con video di sfondo', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Come Welcome minimal ma con zona video hero predisposta. Aggiungi un MP4 di Volterra per massimo impatto.', 'casa-volterra-tv-launcher' ),
				'icon'        => '🎬',
				'meta'        => array(
					'_cv_tv_eyebrow'       => 'Benvenuti',
					'_cv_tv_title'         => 'Casa Volterra',
					'_cv_tv_subtitle'      => 'Volterra, Toscana — dal 9° secolo.',
					'_cv_tv_show_clock'    => '1',
					'_cv_tv_show_weather'  => '1',
					'_cv_tv_weather_city'  => 'Volterra',
					'_cv_tv_overlay'       => '50',
				),
			),
			array(
				'slug'        => 'home_4btn',
				'name'        => __( 'Home — 4 pulsanti', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Quattro tile grandi per le funzioni principali: Netflix, Wi-Fi, Guida, Checkout.', 'casa-volterra-tv-launcher' ),
				'icon'        => '📺',
				'meta'        => array(
					'_cv_tv_eyebrow'  => 'Cosa vuoi fare?',
					'_cv_tv_title'    => 'Casa Volterra',
					'_cv_tv_overlay'  => '60',
					'_cv_tv_buttons'  => array(
						array( 'icon' => '🎬', 'label' => 'Netflix & Streaming', 'desc' => 'Film, serie e sport', 'type' => 'url', 'target' => '' ),
						array( 'icon' => '📶', 'label' => 'Wi-Fi',               'desc' => 'Password e rete',     'type' => 'url', 'target' => '' ),
						array( 'icon' => '🗺',  'label' => 'Guida Volterra',      'desc' => 'Cosa vedere e fare',  'type' => 'url', 'target' => '' ),
						array( 'icon' => '🔑', 'label' => 'Check-out info',       'desc' => 'Come lasciare casa',  'type' => 'url', 'target' => '' ),
						array( 'icon' => '', 'label' => '', 'desc' => '', 'type' => 'url', 'target' => '' ),
						array( 'icon' => '', 'label' => '', 'desc' => '', 'type' => 'url', 'target' => '' ),
					),
				),
			),
			array(
				'slug'        => 'home_6btn',
				'name'        => __( 'Home — 6 pulsanti', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Sei tile per navigazione completa: intrattenimento, casa, guide, ristoranti, meteo, emergenze.', 'casa-volterra-tv-launcher' ),
				'icon'        => '🏠',
				'meta'        => array(
					'_cv_tv_eyebrow'  => 'Come possiamo aiutarti?',
					'_cv_tv_title'    => 'Casa Volterra',
					'_cv_tv_overlay'  => '60',
					'_cv_tv_buttons'  => array(
						array( 'icon' => '🎬', 'label' => 'Streaming TV',      'desc' => 'Netflix · Amazon · Disney+', 'type' => 'url', 'target' => '' ),
						array( 'icon' => '🏡', 'label' => 'Controlli casa',    'desc' => 'Luci, temperatura, tende',   'type' => 'ha', 'target' => '' ),
						array( 'icon' => '🗺',  'label' => 'Guida Volterra',    'desc' => 'Tour, musei, monumenti',     'type' => 'url', 'target' => '' ),
						array( 'icon' => '🍽',  'label' => 'Ristoranti',        'desc' => 'I migliori in città',        'type' => 'url', 'target' => '' ),
						array( 'icon' => '🌤',  'label' => 'Meteo Volterra',   'desc' => 'Previsioni 7 giorni',        'type' => 'url', 'target' => '' ),
						array( 'icon' => '📞', 'label' => 'Contatti urgenti',  'desc' => 'Host, emergenze, taxi',      'type' => 'url', 'target' => '' ),
					),
				),
			),
			array(
				'slug'        => 'weather_events',
				'name'        => __( 'Meteo + eventi', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Schermata informativa: orologio prominente, meteo, ticker eventi. Nessun pulsante.', 'casa-volterra-tv-launcher' ),
				'icon'        => '⛅',
				'meta'        => array(
					'_cv_tv_eyebrow'       => current_time( 'l j F Y' ),
					'_cv_tv_title'         => 'Volterra oggi',
					'_cv_tv_show_clock'    => '1',
					'_cv_tv_show_weather'  => '1',
					'_cv_tv_weather_city'  => 'Volterra',
					'_cv_tv_overlay'       => '55',
					'_cv_tv_ticker_mode'   => 'static',
					'_cv_tv_ticker_text'   => 'Museo Etrusco · Piazza dei Priori · Parco Archeologico · Balze di Volterra',
				),
			),
			array(
				'slug'        => 'checkin_welcome',
				'name'        => __( 'Check-in welcome', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Schermata calda di benvenuto per il giorno di arrivo. Include badge, istruzioni e QR guida.', 'casa-volterra-tv-launcher' ),
				'icon'        => '👋',
				'meta'        => array(
					'_cv_tv_eyebrow'       => 'Benvenuti!',
					'_cv_tv_title'         => 'Siamo felici che siate qui',
					'_cv_tv_subtitle'      => 'Casa Volterra è vostra. Trovate tutto quello che vi serve in questa guida.',
					'_cv_tv_show_clock'    => '1',
					'_cv_tv_show_weather'  => '1',
					'_cv_tv_weather_city'  => 'Volterra',
					'_cv_tv_overlay'       => '55',
					'_cv_tv_badges'        => array( 'Wi-Fi incluso', 'Self check-in', 'Check-out ore 10:00' ),
					'_cv_tv_qr_label'      => 'Scansiona per aprire la guida digitale',
					'_cv_tv_ticker_mode'   => 'static',
					'_cv_tv_ticker_text'   => 'Password Wi-Fi: CasaVolterra · Riscaldamento: termostato in salotto · Silenzio dopo le 22:00',
				),
			),
			array(
				'slug'        => 'checkout_info',
				'name'        => __( 'Check-out info', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Istruzioni di uscita: riconsegna chiavi, orario, cose da fare prima di partire.', 'casa-volterra-tv-launcher' ),
				'icon'        => '🔑',
				'meta'        => array(
					'_cv_tv_eyebrow'       => 'È arrivato il momento di salutarci',
					'_cv_tv_title'         => 'Check-out — ore 10:00',
					'_cv_tv_subtitle'      => 'Lasciate le chiavi sulla scrivania. Grazie per aver scelto Casa Volterra!',
					'_cv_tv_overlay'       => '60',
					'_cv_tv_badges'        => array( 'Entro le 10:00', 'Chiavi in salotto', 'Finestre chiuse' ),
					'_cv_tv_buttons'       => array(
						array( 'icon' => '📋', 'label' => 'Lista check-out', 'desc' => 'Tutto quello da fare', 'type' => 'url', 'target' => '' ),
						array( 'icon' => '🗺',  'label' => 'Come arrivare',   'desc' => 'Stazione · parcheggi', 'type' => 'url', 'target' => '' ),
						array( 'icon' => '', 'label' => '', 'desc' => '', 'type' => 'url', 'target' => '' ),
						array( 'icon' => '', 'label' => '', 'desc' => '', 'type' => 'url', 'target' => '' ),
						array( 'icon' => '', 'label' => '', 'desc' => '', 'type' => 'url', 'target' => '' ),
						array( 'icon' => '', 'label' => '', 'desc' => '', 'type' => 'url', 'target' => '' ),
					),
				),
			),
			array(
				'slug'        => 'promo_esperienze',
				'name'        => __( 'Promo esperienze', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Schermata promozionale per esperienze locali: degustazioni, tour, ristoranti partner. Include QR.', 'casa-volterra-tv-launcher' ),
				'icon'        => '🍷',
				'meta'        => array(
					'_cv_tv_eyebrow'       => 'Da non perdere',
					'_cv_tv_title'         => 'Esperienze a Volterra',
					'_cv_tv_subtitle'      => 'Degustazioni di vini, tour dell\'alabastro, escursioni alle Balze.',
					'_cv_tv_overlay'       => '55',
					'_cv_tv_qr_label'      => 'Prenota ora — inquadra il QR',
					'_cv_tv_ticker_mode'   => 'static',
					'_cv_tv_ticker_text'   => 'Teatro Romano · Museo dell\'Alabastro · Enoteca Dè Priori · Agriturismo Le Balze',
				),
			),
			array(
				'slug'        => 'standby',
				'name'        => __( 'Standby / notte', 'casa-volterra-tv-launcher' ),
				'description' => __( 'Schermata minimalista per le ore notturne. Solo orologio grande e branding. Overlay forte.', 'casa-volterra-tv-launcher' ),
				'icon'        => '🌑',
				'meta'        => array(
					'_cv_tv_title'         => 'Casa Volterra',
					'_cv_tv_subtitle'      => 'Buona notte',
					'_cv_tv_show_clock'    => '1',
					'_cv_tv_show_weather'  => '0',
					'_cv_tv_overlay'       => '80',
					'_cv_tv_ticker_mode'   => 'static',
					'_cv_tv_ticker_text'   => 'Silenzio richiesto dopo le 22:00 · Grazie per la collaborazione',
				),
			),
		);
	}

	/* ── Admin page ─────────────────────────────────────────── */

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$templates = self::get_templates();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Template schermate TV', 'casa-volterra-tv-launcher' ); ?></h1>
			<p><?php esc_html_e( 'Clicca "Usa questo template" per creare una nuova schermata TV preconfigurata. Potrai modificarla subito dopo.', 'casa-volterra-tv-launcher' ); ?></p>

			<?php if ( isset( $_GET['created'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Schermata creata! Modifica i dettagli qui sotto.', 'casa-volterra-tv-launcher' ); ?></p>
				</div>
			<?php endif; ?>

			<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-top:24px;">
				<?php foreach ( $templates as $tpl ) : ?>
					<div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:20px;">
						<div style="font-size:2.4em;margin-bottom:8px;"><?php echo esc_html( $tpl['icon'] ); ?></div>
						<h3 style="margin:0 0 8px;"><?php echo esc_html( $tpl['name'] ); ?></h3>
						<p style="color:#666;font-size:0.9em;margin:0 0 16px;"><?php echo esc_html( $tpl['description'] ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="cv_tv_use_template" />
							<input type="hidden" name="template_slug" value="<?php echo esc_attr( $tpl['slug'] ); ?>" />
							<?php wp_nonce_field( 'cv_tv_use_template_' . $tpl['slug'], 'cv_tv_template_nonce' ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Usa questo template', 'casa-volterra-tv-launcher' ); ?></button>
						</form>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/* ── Handler ────────────────────────────────────────────── */

	public static function handle_use_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non autorizzato.', 'casa-volterra-tv-launcher' ) );
		}
		$slug = sanitize_key( $_POST['template_slug'] ?? '' );
		if ( ! isset( $_POST['cv_tv_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_tv_template_nonce'] ) ), 'cv_tv_use_template_' . $slug ) ) {
			wp_die( esc_html__( 'Nonce non valido.', 'casa-volterra-tv-launcher' ) );
		}

		$templates = self::get_templates();
		$found     = null;
		foreach ( $templates as $tpl ) {
			if ( $tpl['slug'] === $slug ) {
				$found = $tpl;
				break;
			}
		}
		if ( ! $found ) {
			wp_die( esc_html__( 'Template non trovato.', 'casa-volterra-tv-launcher' ) );
		}

		// Create the post
		$post_id = wp_insert_post( array(
			'post_type'   => CV_TV_Screens::PT,
			'post_title'  => $found['name'],
			'post_status' => 'publish',
		), true );

		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html( $post_id->get_error_message() ) );
		}

		// Apply meta
		foreach ( $found['meta'] as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		wp_safe_redirect( add_query_arg( array( 'post' => $post_id, 'action' => 'edit', 'cv_tv_created' => '1' ), admin_url( 'post.php' ) ) );
		exit;
	}
}
