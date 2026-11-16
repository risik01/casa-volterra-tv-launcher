<?php
/**
 * Scheduler — fasce orarie che determinano quale playlist/screen mostrare, quando.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Schedule {

	const OPTION = 'cv_tv_schedule';

	public static function init() {
		add_action( 'admin_menu',        array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_cv_tv_save_schedule', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'edit.php?post_type=cv_tv_screen',
			__( 'Scheduler TV', 'casa-volterra-tv-launcher' ),
			__( 'Scheduler', 'casa-volterra-tv-launcher' ),
			'manage_options',
			'cv-tv-schedule',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'cv-tv-schedule' ) ) {
			return;
		}
		wp_enqueue_script(
			'cv-tv-admin-schedule',
			CV_TV_URL . 'assets/admin-schedule.js',
			array( 'jquery' ),
			CV_TV_VERSION,
			true
		);
	}

	public static function get_schedule() {
		$data = get_option( self::OPTION, array() );
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		return wp_parse_args( $data, array(
			'slots'           => array(),
			'fallback_type'   => 'screen',
			'fallback_id'     => 0,
		) );
	}

	/* ── Admin page ─────────────────────────────────────────── */

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$schedule = self::get_schedule();
		$slots    = $schedule['slots'];

		// Resolve current slot for info box
		$resolved = self::resolve_now();

		// Load available screens and playlists
		$screens   = get_posts( array( 'post_type' => 'cv_tv_screen',   'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false ) );
		$playlists = get_posts( array( 'post_type' => 'cv_tv_playlist', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false ) );

		$day_types = array(
			'all'      => __( 'Tutti i giorni', 'casa-volterra-tv-launcher' ),
			'normal'   => __( 'Giorni normali', 'casa-volterra-tv-launcher' ),
			'checkin'  => __( 'Giorno check-in', 'casa-volterra-tv-launcher' ),
			'checkout' => __( 'Giorno check-out', 'casa-volterra-tv-launcher' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Scheduler Casa Volterra TV', 'casa-volterra-tv-launcher' ); ?></h1>

			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Scheduler salvato.', 'casa-volterra-tv-launcher' ); ?></p></div>
			<?php endif; ?>

		<?php
		// Warning se scheduler vuoto e nessun fallback → schermo nero
		$_has_fallback = ! empty( $schedule['fallback_id'] );
		$_has_slots    = ! empty( $slots );
		$_first_screen = get_posts( array( 'post_type' => 'cv_tv_screen', 'post_status' => 'publish', 'numberposts' => 1, 'suppress_filters' => false ) );
		if ( ! $_has_slots && ! $_has_fallback ) : ?>
			<div class="notice notice-warning" style="border-left-color:#f97316">
				<p><strong>&#x26A0;&#xFE0F; Attenzione: schermo nero!</strong>
				Lo scheduler è vuoto e nessuna Screen fallback è configurata.
				<?php if ( ! empty( $_first_screen ) ) : ?>
					La TV mostrerà automaticamente <strong><?php echo esc_html( $_first_screen[0]->post_title ); ?></strong> come fallback automatico.
					Per scegliere tu quale Screen mostrare, configurala nel campo <em>Screen fallback</em> qui sotto e salva.
				<?php else : ?>
					<strong>Nessuna Screen pubblicata trovata.</strong> Crea almeno una TV Screen.
				<?php endif; ?></p>
			</div>
		<?php elseif ( ! $_has_slots ) : ?>
			<div class="notice notice-info is-dismissible">
				<p>ℹ️ Nessuna fascia oraria — la TV mostra sempre la Screen fallback selezionata sotto.</p>
			</div>
		<?php endif; ?>


		<!-- ── Orario server ──────────────────────────────────────────── -->
		<?php
		$wp_tz_string = wp_timezone_string();
		$wp_tz_obj    = wp_timezone();
		$dt_now       = new DateTime( 'now', $wp_tz_obj );
		$is_dst       = (bool) $dt_now->format( 'I' );
		$local_time   = $dt_now->format( 'H:i:s' );
		$utc_time     = ( new DateTime( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'H:i:s' );
		$offset_hrs   = $dt_now->format( 'P' );
		$tz_is_named  = (bool) preg_match( '/[A-Za-z]\/[A-Za-z]/', $wp_tz_string );
		?>
		<div style="background:#f0f6fc;border-left:4px solid #2271b1;padding:10px 18px;margin:0 0 12px;border-radius:0 6px 6px 0;font-size:13px;">
			<strong>&#x1F550; Orario server:</strong>
			&nbsp;<strong style="font-size:15px;"><?php echo esc_html( $local_time ); ?></strong>
			<span style="color:#555;"> (timezone WP: <code><?php echo esc_html( $wp_tz_string ); ?></code>, offset <?php echo esc_html( $offset_hrs ); ?><?php if ( $is_dst ) { echo '&nbsp;&#x26A0;&#xFE0F;&nbsp;<strong style="color:#d63638;">ora legale (DST) attiva</strong>'; } ?>)</span>
			&nbsp;&middot;&nbsp; UTC: <strong><?php echo esc_html( $utc_time ); ?></strong>
			<?php if ( ! $tz_is_named ) : ?>
				<br><span style="color:#d63638;">&#x26A0;&#xFE0F; <strong>Attenzione:</strong> il timezone WP &egrave; un offset fisso — l&apos;ora legale <strong>non</strong> viene gestita. Imposta <code>Europe/Rome</code> in <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">Impostazioni &rarr; Generali</a>.</span>
			<?php endif; ?>
		</div>

			<!-- Info box: in onda adesso + ospite -->
			<?php
			$_plugin_settings = CV_TV_Launcher::get_settings();
			// Ospite attuale (in casa oggi)
			$_guest_name    = CV_TV_Launcher::effective_guest_name( $_plugin_settings );
			$_guest_url     = $_plugin_settings['current_guest_app_url'] ?? '';
			$_cur_checkin   = $_plugin_settings['current_checkin']       ?? '';
			$_cur_checkout  = $_plugin_settings['current_checkout']      ?? '';
			// Prossimo ospite (per automation scheduler)
			$_next_checkin  = $_plugin_settings['next_checkin']  ?? '';
			$_next_checkout = $_plugin_settings['next_checkout'] ?? '';
			$_today         = wp_date( 'Y-m-d' );
			?>
			<div style="background:#fff;border-left:4px solid #0073aa;padding:14px 18px;margin:0 0 16px;border-radius:0 6px 6px 0;display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">
				<div style="flex:1;min-width:260px;">
					<strong><?php esc_html_e( '▶ In onda adesso:', 'casa-volterra-tv-launcher' ); ?></strong>
					<?php if ( $resolved ) : ?>
						<?php echo esc_html( 'playlist' === $resolved['type'] ? __( 'Playlist', 'casa-volterra-tv-launcher' ) : __( 'Screen', 'casa-volterra-tv-launcher' ) ); ?>
						— <em><?php echo esc_html( get_the_title( $resolved['id'] ) ); ?></em>
						<span style="color:#888;font-size:12px;">(<?php echo esc_html( $resolved['resolved_by'] ); ?>)</span>
					<?php else : ?>
						<em><?php esc_html_e( 'Nessuno slot attivo — verrà mostrata la schermata fallback.', 'casa-volterra-tv-launcher' ); ?></em>
					<?php endif; ?>
				</div>
				<?php
				// Chip ospite: mostra se c'è un ospite attuale in casa
				$_show_guest = ! empty( $_guest_name );
				?>
				<?php if ( $_show_guest ) : ?>
				<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:8px 14px;font-size:13px;white-space:nowrap;">
					<div style="color:#3b82f6;font-size:11px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;">
						<?php
						if ( $_cur_checkin === $_today )       echo '&#x1F6EC; Arrivo oggi';
						elseif ( $_cur_checkout === $_today )  echo '&#x1F6EB; Partenza oggi';
						else                                   echo '&#x1F464; Ospite in corso';
						?>
					</div>
					<strong style="font-size:14px;"><?php echo esc_html( $_guest_name ); ?></strong>
					<?php if ( $_guest_url ) : ?>
						&nbsp;<a href="<?php echo esc_url( $_guest_url ); ?>" target="_blank" rel="noopener" style="font-size:12px;">&#x1F4D6;</a>
					<?php endif; ?>
				</div>
				<?php else : ?>
				<?php
				$_next_name = $_plugin_settings['next_guest_name'] ?? '';
				if ( $_next_name && $_next_checkin ) : ?>
				<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 14px;font-size:13px;white-space:nowrap;color:#64748b;">
					&#x23F0; Prossimo ospite il <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $_next_checkin ) ) ); ?>:
					<em><?php echo esc_html( $_next_name ); ?></em>
					<small style="color:#94a3b8;">(nome sul TV dal giorno dell&#39;arrivo)</small>
				</div>
				<?php else : ?>
				<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 14px;font-size:13px;color:#94a3b8;">
					&#x1F6CC; Nessun ospite in casa.
				</div>
				<?php endif; endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="cv_tv_save_schedule" />
				<?php wp_nonce_field( 'cv_tv_save_schedule', 'cv_tv_schedule_nonce' ); ?>

				<h2><?php esc_html_e( 'Fasce orarie', 'casa-volterra-tv-launcher' ); ?></h2>
				<p><?php esc_html_e( 'Le fasce sono valutate in ordine. La prima corrispondente vince. Orari in formato HH:MM (24h). Le fasce overnight (es. 22:00 → 06:00) sono supportate.', 'casa-volterra-tv-launcher' ); ?></p>

				<table class="widefat" id="cv-tv-schedule-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Etichetta', 'casa-volterra-tv-launcher' ); ?></th>
							<th style="width:140px"><?php esc_html_e( 'Tipo giorno', 'casa-volterra-tv-launcher' ); ?></th>
							<th style="width:80px"><?php esc_html_e( 'Dalle', 'casa-volterra-tv-launcher' ); ?></th>
							<th style="width:80px"><?php esc_html_e( 'Alle', 'casa-volterra-tv-launcher' ); ?></th>
							<th style="width:110px"><?php esc_html_e( 'Tipo', 'casa-volterra-tv-launcher' ); ?></th>
							<th><?php esc_html_e( 'Playlist / Screen', 'casa-volterra-tv-launcher' ); ?></th>
							<th style="width:50px"></th>
						</tr>
					</thead>
					<tbody id="cv-tv-schedule-slots">
						<?php foreach ( $slots as $i => $slot ) : ?>
							<?php self::render_slot_row( $i, $slot, $screens, $playlists, $day_types ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top:12px;">
					<button type="button" class="button button-primary" id="cv-tv-add-slot"><?php esc_html_e( '+ Aggiungi fascia', 'casa-volterra-tv-launcher' ); ?></button>
				</p>

				<h2 style="margin-top:32px;"><?php esc_html_e( 'Schermata fallback', 'casa-volterra-tv-launcher' ); ?></h2>
				<p><?php esc_html_e( 'Mostrata quando nessuna fascia è attiva o in caso di errore. Questa schermata deve essere sempre configurata.', 'casa-volterra-tv-launcher' ); ?></p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Tipo fallback', 'casa-volterra-tv-launcher' ); ?></th>
						<td>
							<label style="margin-right:16px;"><input type="radio" name="cv_tv_fallback_type" value="screen" <?php checked( 'screen', $schedule['fallback_type'] ); ?> /> <?php esc_html_e( 'Screen', 'casa-volterra-tv-launcher' ); ?></label>
							<label><input type="radio" name="cv_tv_fallback_type" value="playlist" <?php checked( 'playlist', $schedule['fallback_type'] ); ?> /> <?php esc_html_e( 'Playlist', 'casa-volterra-tv-launcher' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cv_tv_fallback_screen"><?php esc_html_e( 'Screen fallback', 'casa-volterra-tv-launcher' ); ?></label></th>
						<td>
							<select id="cv_tv_fallback_screen" name="cv_tv_fallback_screen_id">
								<option value="0"><?php esc_html_e( '— Seleziona —', 'casa-volterra-tv-launcher' ); ?></option>
								<?php foreach ( $screens as $s ) : ?>
									<option value="<?php echo esc_attr( (string) $s->ID ); ?>" <?php selected( 'screen' === $schedule['fallback_type'] ? (int) $schedule['fallback_id'] : 0, $s->ID ); ?>><?php echo esc_html( $s->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cv_tv_fallback_playlist"><?php esc_html_e( 'Playlist fallback', 'casa-volterra-tv-launcher' ); ?></label></th>
						<td>
							<select id="cv_tv_fallback_playlist" name="cv_tv_fallback_playlist_id">
								<option value="0"><?php esc_html_e( '— Seleziona —', 'casa-volterra-tv-launcher' ); ?></option>
								<?php foreach ( $playlists as $p ) : ?>
									<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php selected( 'playlist' === $schedule['fallback_type'] ? (int) $schedule['fallback_id'] : 0, $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Salva scheduler', 'casa-volterra-tv-launcher' ) ); ?>
			</form>
		</div>

		<!-- Template row per JS -->
		<template id="cv-tv-slot-template">
			<?php self::render_slot_row( '__IDX__', array(), $screens, $playlists, $day_types ); ?>
		</template>
		<?php
	}

	private static function render_slot_row( $i, $slot, $screens, $playlists, $day_types ) {
		$label       = $slot['label'] ?? '';
		$day_type    = $slot['day_type'] ?? 'all';
		$time_start  = $slot['time_start'] ?? '08:00';
		$time_end    = $slot['time_end'] ?? '12:00';
		$content_t   = $slot['content_type'] ?? 'playlist';
		$content_id  = (int) ( $slot['content_id'] ?? 0 );
		?>
		<tr class="cv-tv-slot-row">
			<td><input type="text" name="cv_tv_slots[<?php echo esc_attr( (string) $i ); ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'es. Mattina', 'casa-volterra-tv-launcher' ); ?>" /></td>
			<td>
				<select name="cv_tv_slots[<?php echo esc_attr( (string) $i ); ?>][day_type]">
					<?php foreach ( $day_types as $val => $lbl ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $day_type, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="time" name="cv_tv_slots[<?php echo esc_attr( (string) $i ); ?>][time_start]" value="<?php echo esc_attr( $time_start ); ?>" /></td>
			<td><input type="time" name="cv_tv_slots[<?php echo esc_attr( (string) $i ); ?>][time_end]" value="<?php echo esc_attr( $time_end ); ?>" /></td>
			<td>
				<select name="cv_tv_slots[<?php echo esc_attr( (string) $i ); ?>][content_type]" class="cv-tv-content-type-sel">
					<option value="playlist" <?php selected( $content_t, 'playlist' ); ?>><?php esc_html_e( 'Playlist', 'casa-volterra-tv-launcher' ); ?></option>
					<option value="screen" <?php selected( $content_t, 'screen' ); ?>><?php esc_html_e( 'Screen', 'casa-volterra-tv-launcher' ); ?></option>
				</select>
			</td>
			<td>
				<select name="cv_tv_slots[<?php echo esc_attr( (string) $i ); ?>][content_id]" class="widefat cv-tv-content-playlist" <?php echo 'screen' === $content_t ? 'style="display:none"' : ''; ?>>
					<option value="0"><?php esc_html_e( '— Seleziona playlist —', 'casa-volterra-tv-launcher' ); ?></option>
					<?php foreach ( $playlists as $p ) : ?>
						<option value="<?php echo esc_attr( (string) $p->ID ); ?>" <?php echo 'playlist' === $content_t ? selected( $content_id, $p->ID, false ) : ''; ?>><?php echo esc_html( $p->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="cv_tv_slots[<?php echo esc_attr( (string) $i ); ?>][content_id_screen]" class="widefat cv-tv-content-screen" <?php echo 'playlist' === $content_t ? 'style="display:none"' : ''; ?>>
					<option value="0"><?php esc_html_e( '— Seleziona screen —', 'casa-volterra-tv-launcher' ); ?></option>
					<?php foreach ( $screens as $s ) : ?>
						<option value="<?php echo esc_attr( (string) $s->ID ); ?>" <?php echo 'screen' === $content_t ? selected( $content_id, $s->ID, false ) : ''; ?>><?php echo esc_html( $s->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><button type="button" class="button cv-tv-remove-slot">✕</button></td>
		</tr>
		<?php
	}

	/* ── Save handler ───────────────────────────────────────── */

	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non autorizzato.', 'casa-volterra-tv-launcher' ) );
		}
		if ( ! isset( $_POST['cv_tv_schedule_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_tv_schedule_nonce'] ) ), 'cv_tv_save_schedule' ) ) {
			wp_die( esc_html__( 'Nonce non valido.', 'casa-volterra-tv-launcher' ) );
		}

		$raw_slots     = isset( $_POST['cv_tv_slots'] ) && is_array( $_POST['cv_tv_slots'] ) ? $_POST['cv_tv_slots'] : array();
		$valid_days    = array( 'all', 'normal', 'checkin', 'checkout' );
		$valid_content = array( 'playlist', 'screen' );
		$slots         = array();

		foreach ( $raw_slots as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$content_type = sanitize_key( $raw['content_type'] ?? 'playlist' );
			if ( ! in_array( $content_type, $valid_content, true ) ) {
				$content_type = 'playlist';
			}
			$content_id = 'screen' === $content_type
				? absint( $raw['content_id_screen'] ?? 0 )
				: absint( $raw['content_id'] ?? 0 );
			if ( ! $content_id ) {
				continue;
			}
			$day_type = sanitize_key( $raw['day_type'] ?? 'all' );
			if ( ! in_array( $day_type, $valid_days, true ) ) {
				$day_type = 'all';
			}
			$slots[] = array(
				'label'        => sanitize_text_field( wp_unslash( (string) ( $raw['label'] ?? '' ) ) ),
				'day_type'     => $day_type,
				'time_start'   => sanitize_text_field( wp_unslash( (string) ( $raw['time_start'] ?? '08:00' ) ) ),
				'time_end'     => sanitize_text_field( wp_unslash( (string) ( $raw['time_end'] ?? '23:59' ) ) ),
				'content_type' => $content_type,
				'content_id'   => $content_id,
			);
		}

		$fallback_type = sanitize_key( $_POST['cv_tv_fallback_type'] ?? 'screen' );
		if ( ! in_array( $fallback_type, $valid_content, true ) ) {
			$fallback_type = 'screen';
		}
		$fallback_id = 'playlist' === $fallback_type
			? absint( $_POST['cv_tv_fallback_playlist_id'] ?? 0 )
			: absint( $_POST['cv_tv_fallback_screen_id'] ?? 0 );

		update_option( self::OPTION, array(
			'slots'         => $slots,
			'fallback_type' => $fallback_type,
			'fallback_id'   => $fallback_id,
		) );

		wp_safe_redirect( add_query_arg( array( 'page' => 'cv-tv-schedule', 'saved' => '1' ), admin_url( 'edit.php?post_type=cv_tv_screen' ) ) );
		exit;
	}

	/* ── Resolver ───────────────────────────────────────────── */

	/**
	 * Controlla se siamo dentro una finestra di evento arrivo/partenza.
	 * Ritorna array {type, id, resolved_by} o null.
	 * Ha PRIORITÀ MASSIMA: chiamato prima degli slot scheduler normali.
	 *
	 * Logica partenza (checkout):
	 *   finestra = [checkout_time - advance_hours, checkout_time + extra_hours]
	 *
	 * Logica arrivo (checkin):
	 *   finestra = [checkin_time - advance_hours, checkin_time + duration_hours]
	 */
	public static function resolve_event() {
		$s     = CV_TV_Launcher::get_settings();
		$today = wp_date( 'Y-m-d' );
		$now   = (int) wp_date( 'H' ) * 60 + (int) wp_date( 'i' );

		// ── Checkout (di solito mattina — verificato per prima) ──
		if ( ! empty( $s['event_checkout_screen_id'] ) && $s['current_checkout'] === $today ) {
			$t   = ! empty( $s['current_checkout_time'] ) ? $s['current_checkout_time'] : ( $s['event_checkout_default_time'] ?: '11:00' );
			list( $th, $tm ) = array_map( 'intval', explode( ':', $t ) );
			$base  = $th * 60 + $tm;
			$adv   = max( 0, (int) $s['event_checkout_advance_hours'] );
			$ext   = max( 0, (int) $s['event_checkout_extra_hours'] );
			$start = $base - $adv * 60;
			$end   = $base + $ext * 60; // se $ext=0 finisce ESATTAMENTE all'ora checkout
			// end==start solo se advance=0 e extra=0: finestra invalida, skip
			if ( $start < $end && $now >= $start && $now < $end ) {
				return array(
					'type'        => 'screen',
					'id'          => (int) $s['event_checkout_screen_id'],
					'resolved_by' => sprintf( 'partenza (%s)', $t ),
				);
			}
		}

		// ── Checkin (di solito pomeriggio) ──────────────────────────────────
		// Sorgente dell'orario checkin:
		//   A) ospite corrente arriva oggi (caso normale: current_checkin === today)
		//   B) turnover: ospite corrente parte oggi, il PROSSIMO arriva oggi
		//      → in questo caso current_checkin è la data di arrivo del vecchio ospite
		//        (settimane fa) e NON corrisponde a oggi; usiamo next_checkin.
		if ( ! empty( $s['event_checkin_screen_id'] ) ) {
			$ci_src_date = '';
			$ci_src_time = '';
			if ( $s['current_checkin'] === $today ) {
				// Caso A: arrivo normale oggi
				$ci_src_date = $today;
				$ci_src_time = ! empty( $s['current_checkin_time'] ) ? $s['current_checkin_time'] : ( $s['event_checkin_default_time'] ?: '15:00' );
			} elseif ( ( $s['next_checkin'] ?? '' ) === $today ) {
				// Caso B: turnover — il prossimo ospite arriva oggi
				$ci_src_date = $today;
				$ci_src_time = ! empty( $s['next_checkin_time'] ) ? $s['next_checkin_time'] : ( $s['event_checkin_default_time'] ?: '15:00' );
			}
			if ( $ci_src_date ) {
				list( $th, $tm ) = array_map( 'intval', explode( ':', $ci_src_time ) );
				$base  = $th * 60 + $tm;
				$adv   = max( 0, (int) $s['event_checkin_advance_hours'] );
				$dur   = max( 1, (int) $s['event_checkin_duration_hours'] );
				$start = $base - $adv * 60;
				$end   = $base + $dur * 60;
				if ( $now >= $start && $now < $end ) {
					return array(
						'type'        => 'screen',
						'id'          => (int) $s['event_checkin_screen_id'],
						'resolved_by' => sprintf( 'arrivo (%s)', $ci_src_time ),
					);
				}
			}
		}

		return null;
	}

	/**
	 * Ritorna array {type, id, resolved_by} o null se nessuna fascia corrisponde.
	 */
	public static function resolve_now() {
		// Priorità 1: finestre evento arrivo/partenza (ignorano gli slot scheduler)
		$event = self::resolve_event();
		if ( $event ) {
			return $event;
		}

		// Priorità 2: slot scheduler normali
		$schedule = self::get_schedule();
		$slots    = $schedule['slots'];
		if ( empty( $slots ) ) {
			return null;
		}

		$settings = CV_TV_Launcher::get_settings();
		// wp_date() usa il timezone WordPress con supporto DST corretto (Europe/Rome ecc.)
		$now_hm   = (int) wp_date( 'H' ) * 60 + (int) wp_date( 'i' );
		$today    = wp_date( 'Y-m-d' );

		$day_type = 'normal';
		if ( $settings['current_checkin'] && $today === $settings['current_checkin'] ) {
			$day_type = 'checkin';
		} elseif ( $settings['current_checkout'] && $today === $settings['current_checkout'] ) {
			$day_type = 'checkout';
		}

		foreach ( $slots as $slot ) {
			// Day type match
			$slot_day = $slot['day_type'] ?? 'all';
			if ( 'all' !== $slot_day && $slot_day !== $day_type ) {
				continue;
			}

			// Time match (supports overnight)
			list( $sh, $sm ) = array_map( 'intval', explode( ':', $slot['time_start'] ?? '00:00' ) );
			list( $eh, $em ) = array_map( 'intval', explode( ':', $slot['time_end'] ?? '23:59' ) );
			$start = $sh * 60 + $sm;
			$end   = $eh * 60 + $em;
			$match = false;
			if ( $start <= $end ) {
				$match = $now_hm >= $start && $now_hm < $end;
			} else {
				// Overnight: e.g. 22:00 → 06:00
				$match = $now_hm >= $start || $now_hm < $end;
			}
			if ( ! $match ) {
				continue;
			}

			$label = $slot['label'] ?: __( 'Fascia senza nome', 'casa-volterra-tv-launcher' );
			return array(
				'type'        => $slot['content_type'],
				'id'          => (int) $slot['content_id'],
				'resolved_by' => $label,
			);
		}

		return null;
	}

	/**
	 * Ritorna il fallback configurato come {type, id, resolved_by}.
	 * Se nessun fallback è configurato, usa automaticamente la prima
	 * Screen pubblicata disponibile (evita lo schermo nero).
	 */
	public static function get_fallback() {
		$schedule = self::get_schedule();

		if ( $schedule['fallback_id'] ) {
			return array(
				'type'        => $schedule['fallback_type'],
				'id'          => (int) $schedule['fallback_id'],
				'resolved_by' => 'fallback',
			);
		}

		// Nessun fallback configurato → usa la prima Screen pubblicata
		$first = get_posts( array(
			'post_type'        => 'cv_tv_screen',
			'post_status'      => 'publish',
			'numberposts'      => 1,
			'orderby'          => 'menu_order title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		) );

		if ( ! empty( $first ) ) {
			return array(
				'type'        => 'screen',
				'id'          => (int) $first[0]->ID,
				'resolved_by' => 'auto-fallback (' . $first[0]->post_title . ')',
			);
		}

		return null;
	}

	/**
	 * Secondi al prossimo cambio di fascia (per auto-reload).
	 */
	public static function seconds_to_next_boundary() {
		$schedule = self::get_schedule();
		$slots    = $schedule['slots'];
		$now_hm   = (int) wp_date( 'H' ) * 60 + (int) wp_date( 'i' );
		$now_sec  = (int) wp_date( 's' );
		$min_gap  = PHP_INT_MAX;

		// Boundary degli slot scheduler
		foreach ( $slots as $slot ) {
			foreach ( array( $slot['time_start'], $slot['time_end'] ) as $t ) {
				list( $h, $m ) = array_map( 'intval', explode( ':', $t ?? '00:00' ) );
				$boundary = $h * 60 + $m;
				$gap      = $boundary - $now_hm;
				if ( $gap <= 0 ) $gap += 1440;
				if ( $gap < $min_gap ) $min_gap = $gap;
			}
		}

		// Boundary degli eventi arrivo/partenza (solo oggi)
		$s     = CV_TV_Launcher::get_settings();
		$today = wp_date( 'Y-m-d' );
		foreach ( array( 'checkin', 'checkout' ) as $type ) {
			$screen_key = "event_{$type}_screen_id";
			$date_key   = "current_{$type}";
			if ( empty( $s[ $screen_key ] ) || $s[ $date_key ] !== $today ) continue;

			$time_key = "current_{$type}_time";
			$def_key  = "event_{$type}_default_time";
			$t        = ! empty( $s[ $time_key ] ) ? $s[ $time_key ] : ( $s[ $def_key ] ?: ( 'checkin' === $type ? '15:00' : '11:00' ) );
			list( $th, $tm ) = array_map( 'intval', explode( ':', $t ) );
			$base = $th * 60 + $tm;

			if ( 'checkin' === $type ) {
				$adv   = max( 0, (int) $s['event_checkin_advance_hours'] );
				$dur   = max( 1, (int) $s['event_checkin_duration_hours'] );
				$start = $base - $adv * 60;
				$end   = $base + $dur * 60;
			} else {
				$adv   = max( 0, (int) $s['event_checkout_advance_hours'] );
				$ext   = max( 0, (int) $s['event_checkout_extra_hours'] );
				$start = $base - $adv * 60;
				$end   = $base + $ext * 60;
			}
			foreach ( array( $start, $end ) as $boundary ) {
				$gap = $boundary - $now_hm;
				if ( $gap <= 0 ) $gap += 1440;
				if ( $gap < $min_gap ) $min_gap = $gap;
			}
		}

		if ( $min_gap === PHP_INT_MAX ) return 300;
		return max( 60, $min_gap * 60 - $now_sec );
	}
}
