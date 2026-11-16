<?php
/**
 * Smoobu Integration — sincronizza le date di check-in/check-out
 * dal calendario Smoobu e aggiorna automaticamente le impostazioni del plugin.
 *
 * Funzionalità:
 *  - Cron giornaliero (2x/giorno) che aggiorna next_checkin e next_checkout
 *  - Endpoint AJAX per sync manuale dal pannello impostazioni
 *  - Endpoint AJAX per caricare la lista appartamenti (popola il select)
 *  - Salva log dell'ultima sincronizzazione in cv_tv_smoobu_sync
 *
 * Autenticazione (dalla 4.2.0): ogni richiesta è firmata HMAC-SHA256 come
 * richiesto da Smoobu dal 31/10/2026 (docs.smoobu.com → HMAC Authentication).
 * Servono API Key + API Secret; senza secret il plugin ripiega sul vecchio
 * header Api-Key, che Smoobu non accetta più dopo quella data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Smoobu {

	const OPTION_SYNC  = 'cv_tv_smoobu_sync';
	const CRON_HOOK    = 'cv_tv_smoobu_cron';
	const API_HOST     = 'https://login.smoobu.com';
	const API_PATH     = '/api';
	const API_BASE     = 'https://login.smoobu.com/api';

	/* ── Init ───────────────────────────────────────────────── */

	public static function init() {
		// Cron
		add_action( self::CRON_HOOK, array( __CLASS__, 'do_sync' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}

		// AJAX (solo utenti loggati con manage_options)
		add_action( 'wp_ajax_cv_tv_smoobu_sync',        array( __CLASS__, 'ajax_sync' ) );
		add_action( 'wp_ajax_cv_tv_smoobu_apartments',  array( __CLASS__, 'ajax_apartments' ) );

		// Avviso in bacheca se manca l'API Secret
		add_action( 'admin_notices', array( __CLASS__, 'admin_secret_notice' ) );
	}

	public static function deactivate() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/* ── Firma HMAC ─────────────────────────────────────────── */

	/**
	 * Ripulisce un API Secret incollato: via spazi, a-capo e caratteri di
	 * controllo. Non usa sanitize_text_field perché altererebbe i secret
	 * che contengono sequenze tipo "%2B" o "<".
	 */
	public static function sanitize_secret( $value ) {
		return is_scalar( $value ) ? preg_replace( '/[\x00-\x20\x7F]+/', '', (string) $value ) : '';
	}

	/**
	 * Query string canonica: parametri in ordine alfabetico di chiave, chiavi
	 * e valori codificati RFC 3986 (spazio = %20, mai "+").
	 * La stessa stringa finisce sia nell'URL sia nella firma, così ciò che
	 * firmiamo coincide byte per byte con ciò che Smoobu riceve.
	 */
	public static function canonical_query( $params ) {
		$clean = array();
		foreach ( (array) $params as $k => $v ) {
			if ( null === $v || is_array( $v ) || is_object( $v ) ) {
				continue;
			}
			if ( is_bool( $v ) ) {
				$v = $v ? '1' : '0';
			}
			$clean[ (string) $k ] = (string) $v;
		}
		ksort( $clean, SORT_STRING );

		$pairs = array();
		foreach ( $clean as $k => $v ) {
			$pairs[] = rawurlencode( (string) $k ) . '=' . rawurlencode( $v );
		}
		return implode( '&', $pairs );
	}

	/**
	 * Header di autenticazione per una richiesta firmata.
	 *
	 * Stringa firmata (righe separate da "\n"):
	 *   METODO, path (es. /api/reservations), query canonica (vuota se assente),
	 *   timestamp UTC ISO 8601, nonce, SHA-256 esadecimale del body, API key.
	 * Firma = base64( HMAC-SHA256( stringa, API secret ) ).
	 *
	 * $timestamp e $nonce si passano solo nei test: in produzione vengono
	 * generati qui (Smoobu rifiuta timestamp fuori di 5 minuti e nonce riusati).
	 */
	public static function signed_headers( $method, $path, $query, $body, $api_key, $api_secret, $timestamp = null, $nonce = null ) {
		if ( ! $timestamp ) {
			$timestamp = gmdate( 'Y-m-d\TH:i:s\Z' );
		}
		if ( ! $nonce ) {
			$nonce = wp_generate_uuid4();
		}

		$canonical = implode( "\n", array(
			strtoupper( $method ),
			$path,
			$query,
			$timestamp,
			$nonce,
			hash( 'sha256', (string) $body ),
			$api_key,
		) );

		return array(
			'X-API-Key'   => $api_key,
			'X-Timestamp' => $timestamp,
			'X-Nonce'     => $nonce,
			'X-Signature' => base64_encode( hash_hmac( 'sha256', $canonical, $api_secret, true ) ),
		);
	}

	/** Messaggio d'errore leggibile per un HTTP 401. */
	private static function explain_401( $response, $signed ) {
		if ( ! $signed ) {
			return 'Smoobu ha rifiutato la richiesta (HTTP 401). Dal 31/10/2026 serve la firma HMAC: inserisci l\'API Secret in Impostazioni → Integrazioni.';
		}

		$msg = 'Autenticazione Smoobu fallita (HTTP 401): controlla che API Key e API Secret siano quelli della stessa chiave.';

		// La firma scade se l'orologio del server è sfasato di oltre 5 minuti.
		$server_date = wp_remote_retrieve_header( $response, 'date' );
		$server_time = $server_date ? strtotime( $server_date ) : 0;
		if ( $server_time ) {
			$skew = abs( time() - $server_time );
			if ( $skew > 240 ) {
				$msg .= sprintf( ' Attenzione: l\'orologio di questo server è sfasato di circa %d secondi rispetto a Smoobu (tolleranza 5 minuti).', $skew );
			}
		}
		return $msg;
	}

	/** Avviso in bacheca: API key presente ma secret mancante. */
	public static function admin_secret_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = CV_TV_Launcher::get_settings();
		if ( '' === trim( $settings['smoobu_api_key'] ?? '' ) || '' !== trim( $settings['smoobu_api_secret'] ?? '' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<strong>Casa Volterra TV — manca l'API Secret di Smoobu.</strong><br>
				Dal 31/10/2026 Smoobu accetta solo richieste firmate: senza secret la sincronizzazione delle prenotazioni non funziona.
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cv_tv_screen&page=cv-tv-settings' ) ); ?>">Inseriscilo nelle impostazioni</a>.
			</p>
		</div>
		<?php
	}

	/* ── API helpers ────────────────────────────────────────── */

	private static function api_get( $endpoint, $params = array() ) {
		$settings   = CV_TV_Launcher::get_settings();
		$api_key    = trim( $settings['smoobu_api_key'] ?? '' );
		$api_secret = trim( $settings['smoobu_api_secret'] ?? '' );

		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', 'API key Smoobu non configurata.' );
		}

		$path  = self::API_PATH . $endpoint;
		$query = self::canonical_query( $params );
		$url   = self::API_HOST . $path . ( '' !== $query ? '?' . $query : '' );

		if ( '' !== $api_secret ) {
			$headers = self::signed_headers( 'GET', $path, $query, '', $api_key, $api_secret );
		} else {
			// Nessun secret salvato: vecchio header, rifiutato da Smoobu dopo il 31/10/2026.
			$headers = array( 'Api-Key' => $api_key );
		}
		$headers['Cache-Control'] = 'no-cache';

		$response = wp_remote_get( $url, array(
			'headers' => $headers,
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 401 ) {
			return new WP_Error( 'unauthorized', self::explain_401( $response, '' !== $api_secret ) );
		}
		if ( $code !== 200 ) {
			$msg = $body['error'] ?? $body['message'] ?? "HTTP $code";
			return new WP_Error( 'api_error', $msg );
		}

		return $body;
	}

	/* ── Core sync ──────────────────────────────────────────── */

	/**
	 * Recupera le prenotazioni da Smoobu e aggiorna next_checkin / next_checkout.
	 * Ritorna array { ok, checkin, checkout, count, message } o WP_Error.
	 */
	public static function do_sync() {
		$settings = CV_TV_Launcher::get_settings();
		$apt_id   = trim( $settings['smoobu_apartment_id'] ?? '' );
		$today    = wp_date( 'Y-m-d' );
		$date_to  = wp_date( 'Y-m-d', strtotime( '+90 days' ) );

		// Usa departureFrom=oggi: cattura soggiorni in corso (arrivati giorni fa)
		// e prenotazioni future. arrivalFrom avrebbe perso i checkout di soggiorni
		// già iniziati.
		$params = array(
			'departureFrom'      => $today,
			'departureTo'        => $date_to,
			'pageSize'           => 100,
			'page'               => 1,
			'showCancellation'   => 0,  // Esclude prenotazioni cancellate
		);
		if ( $apt_id ) {
			$params['apartmentId'] = $apt_id;
		}

		$data = self::api_get( '/reservations', $params );

		if ( is_wp_error( $data ) ) {
			$sync_log = array(
				'status'  => 'error',
				'message' => $data->get_error_message(),
				'time'    => time(),
			);
			update_option( self::OPTION_SYNC, $sync_log );
			return $data;
		}

		$bookings = $data['bookings'] ?? array();

		// Filtra prenotazioni reali.
		// Docs Smoobu: il campo 'is-blocked-booking' (con trattino) è true per
		// blocchi manuali. Il campo 'type' è la stringa "reservation" o "blocking".
		$bookings = array_filter( $bookings, function( $b ) {
			if ( ! empty( $b['is-blocked-booking'] ) ) {
				return false;
			}
			// Tipo "blocking" = blocco manuale
			if ( isset( $b['type'] ) && $b['type'] === 'blocking' ) {
				return false;
			}
			return true;
		} );

		// ── Separa ospite ATTUALE (oggi tra arrival e departure)
		//    da PROSSIMO ospite (arrival > oggi) ──────────────────────
		$current_booking = null;
		$next_booking    = null;

		foreach ( $bookings as $b ) {
			$arrival   = $b['arrival']   ?? '';
			$departure = $b['departure'] ?? '';
			if ( ! $arrival || ! $departure ) continue;

			if ( $arrival <= $today && $today <= $departure ) {
				// Ospite attualmente in casa
				if ( ! $current_booking || $departure < ( $current_booking['departure'] ?? '' ) ) {
					$current_booking = $b;
				}
			} elseif ( $arrival > $today ) {
				// Ospite futuro — teniamo il più prossimo
				if ( ! $next_booking || $arrival < ( $next_booking['arrival'] ?? '' ) ) {
					$next_booking = $b;
				}
			}
		}

		// ── Turnover stesso giorno ────────────────────────────────────
		// Se l'ospite attuale ha il checkout OGGI, un ospite in arrivo
		// OGGI ha arrival == today e NON soddisfa "arrival > today",
		// quindi non sarebbe catturato come next_booking. Lo cerchiamo
		// esplicitamente.
		if ( $current_booking && ( $current_booking['departure'] ?? '' ) === $today ) {
			foreach ( $bookings as $b ) {
				$arr_b = $b['arrival'] ?? '';
				// Ospite diverso dall'attuale che arriva oggi
				if ( $arr_b === $today && $b !== $current_booking ) {
					if ( ! $next_booking || $arr_b <= ( $next_booking['arrival'] ?? '' ) ) {
						$next_booking = $b;
					}
				}
			}
		}

		// Helper: estrai dati da un booking
		$extract = function( $b ) use ( $settings ) {
			if ( ! $b ) {
				return array(
					'checkin' => '', 'checkin_time' => '',
					'checkout' => '', 'checkout_time' => '',
					'guest_name' => '', 'booking_id' => 0, 'guest_app_url' => '',
				);
			}
			$arr_time = $b['check-in']  ?? $b['checkIn']  ?? $b['arrivalTime']   ?? '';
			$dep_time = $b['check-out'] ?? $b['checkOut'] ?? $b['departureTime'] ?? '';
			if ( $arr_time ) $arr_time = substr( $arr_time, 0, 5 );
			if ( $dep_time ) $dep_time = substr( $dep_time, 0, 5 );

			$name_raw = $b['guest-name'] ?? '';
			if ( ! $name_raw ) {
				$g     = $b['guest'] ?? array();
				$first = trim( $g['firstName'] ?? $g['firstname'] ?? '' );
				$last  = trim( $g['lastName']  ?? $g['lastname']  ?? '' );
				$name_raw = trim( "$first $last" );
			}
			$booking_id = (int) ( $b['id'] ?? 0 );
			$app_url    = $b['guest-app-url'] ?? $b['guestAppLink'] ?? $b['guest_app_link'] ?? '';
			if ( ! $app_url && $booking_id ) {
				$token = trim( $settings['smoobu_guest_token'] ?? '' );
				if ( $token ) {
					$app_url = 'https://guest.smoobu.com/?t=' . rawurlencode( $token ) . '&b=' . $booking_id;
				}
			}
			return array(
				'checkin'       => $b['arrival']   ?? '',
				'checkin_time'  => $arr_time,
				'checkout'      => $b['departure'] ?? '',
				'checkout_time' => $dep_time,
				'guest_name'    => trim( $name_raw ),
				'booking_id'    => $booking_id,
				'guest_app_url' => $app_url,
			);
		};

		// ── Riclassificazione time-aware ─────────────────────────────────────
		// Se departure==today e ora >= departure_time: azzera current, promuovi next.
		$now_time_chk = wp_date( 'H:i' );
		if ( $current_booking ) {
			$dep_date     = $current_booking['departure'] ?? '';
			$raw_dep_time = $current_booking['check-out'] ?? $current_booking['checkOut'] ?? $current_booking['departureTime'] ?? '';
			if ( $raw_dep_time ) { $raw_dep_time = substr( $raw_dep_time, 0, 5 ); }
			if ( ! $raw_dep_time ) { $raw_dep_time = ! empty( $settings['event_checkout_default_time'] ) ? $settings['event_checkout_default_time'] : '11:00'; }
			if ( $dep_date === $today && $now_time_chk >= $raw_dep_time ) {
				if ( $next_booking && ( $next_booking['arrival'] ?? '' ) === $today ) {
					$current_booking = $next_booking;
					$next_booking    = null;
					foreach ( $bookings as $b ) {
						$arr_b = $b['arrival'] ?? '';
						if ( $arr_b > $today && $b !== $current_booking ) {
							if ( ! $next_booking || $arr_b < ( $next_booking['arrival'] ?? '' ) ) {
								$next_booking = $b;
							}
						}
					}
				} else {
					$current_booking = null;
				}
			}
		}

		$cur  = $extract( $current_booking );
		$next = $extract( $next_booking );

		// Aggiorna l'opzione principale del plugin
		$opts = get_option( CV_TV_OPTION, array() );
		if ( ! is_array( $opts ) ) { $opts = array(); }

		// Ospite attuale
		$opts['current_checkin']        = $cur['checkin'];
		$opts['current_checkin_time']   = $cur['checkin_time'];
		$opts['current_checkout']       = $cur['checkout'];
		$opts['current_checkout_time']  = $cur['checkout_time'];
		$opts['current_guest_name']     = $cur['guest_name'];
		$opts['current_booking_id']     = $cur['booking_id'];
		$opts['current_guest_app_url']  = $cur['guest_app_url'];

		// Prossimo ospite (per automation scheduler)
		$opts['next_checkin']           = $next['checkin'];
		$opts['next_checkin_time']      = $next['checkin_time'];
		$opts['next_checkout']          = $next['checkout'];
		$opts['next_checkout_time']     = $next['checkout_time'];
		$opts['next_guest_name']        = $next['guest_name'];
		$opts['next_booking_id']        = $next['booking_id'];
		$opts['next_guest_app_url']     = $next['guest_app_url'];
		update_option( CV_TV_OPTION, $opts );

		// Salva log sync
		$sync_log = array(
			'status'               => 'ok',
			// Ospite attuale
			'current_checkin'      => $cur['checkin'],
			'current_checkin_time' => $cur['checkin_time'],
			'current_checkout'     => $cur['checkout'],
			'current_checkout_time'=> $cur['checkout_time'],
			'current_guest_name'   => $cur['guest_name'],
			'current_booking_id'   => $cur['booking_id'],
			'current_guest_app_url'=> $cur['guest_app_url'],
			// Prossimo ospite
			'checkin'              => $next['checkin'],
			'checkin_time'         => $next['checkin_time'],
			'checkout'             => $next['checkout'],
			'checkout_time'        => $next['checkout_time'],
			'guest_name'           => $next['guest_name'],
			'booking_id'           => $next['booking_id'],
			'guest_app_url'        => $next['guest_app_url'],
			'count'                => count( $bookings ),
			'time'                 => time(),
		);
		update_option( self::OPTION_SYNC, $sync_log );

		return $sync_log;
	}

	/* ── AJAX: sync manuale ─────────────────────────────────── */

	public static function ajax_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Non autorizzato.' ), 403 );
		}
		check_ajax_referer( 'cv_tv_smoobu_nonce', 'nonce' );

		$result = self::do_sync();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			// Ospite attuale
			'current_checkin'       => $result['current_checkin'],
			'current_checkin_time'  => $result['current_checkin_time'],
			'current_checkout'      => $result['current_checkout'],
			'current_checkout_time' => $result['current_checkout_time'],
			'current_guest_name'    => $result['current_guest_name'],
			'current_booking_id'    => $result['current_booking_id'],
			'current_guest_app_url' => $result['current_guest_app_url'],
			// Prossimo ospite (per scheduler)
			'checkin'               => $result['checkin'],
			'checkin_time'          => $result['checkin_time'],
			'checkout'              => $result['checkout'],
			'checkout_time'         => $result['checkout_time'],
			'guest_name'            => $result['guest_name'],
			'guest_app_url'         => $result['guest_app_url'],
			'count'                 => $result['count'],
			'time'                  => wp_date( 'd/m/Y H:i', $result['time'] ),
		) );
	}

	/* ── AJAX: carica lista appartamenti ────────────────────── */

	public static function ajax_apartments() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Non autorizzato.' ), 403 );
		}
		check_ajax_referer( 'cv_tv_smoobu_nonce', 'nonce' );
		$apartments = array();

		// Tentativo 1: endpoint /apartments
		$data = self::api_get( '/apartments' );
		if ( ! is_wp_error( $data ) && is_array( $data ) ) {
			$raw = null;
			if ( isset( $data['apartments'] ) && is_array( $data['apartments'] ) ) {
				$raw = $data['apartments'];
			} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
				$raw = $data['data'];
			} elseif ( isset( $data[0] ) ) {
				$raw = $data;
			} else {
				$raw = array_values( $data );
			}
			foreach ( (array) $raw as $apt ) {
				if ( ! is_array( $apt ) ) { continue; }
				$id   = $apt['id'] ?? $apt['apartmentId'] ?? null;
				$name = $apt['name'] ?? $apt['title'] ?? $apt['apartmentName'] ?? null;
				if ( $id && ! $name ) { $name = 'Appartamento ' . $id; }
				if ( $id ) { $apartments[] = array( 'id' => (string) $id, 'name' => (string) $name ); }
			}
		}

		// Tentativo 2: estrai da prenotazioni esistenti se /apartments vuoto
		if ( empty( $apartments ) ) {
			$res = self::api_get( '/reservations', array( 'pageSize' => 100, 'from' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ), 'to' => gmdate( 'Y-m-d', strtotime( '+365 days' ) ) ) );
			if ( ! is_wp_error( $res ) && isset( $res['bookings'] ) ) {
				$seen = array();
				foreach ( $res['bookings'] as $b ) {
					$a = $b['apartment'] ?? null;
					if ( ! is_array( $a ) ) { continue; }
					$aid = $a['id'] ?? null;
					if ( ! $aid || isset( $seen[ $aid ] ) ) { continue; }
					$seen[ $aid ] = true;
					$aname = $a['name'] ?? ( 'Appartamento ' . $aid );
					$apartments[] = array( 'id' => (string) $aid, 'name' => (string) $aname );
				}
			}
		}

		wp_send_json_success( array( 'apartments' => $apartments ) );
	}

	/* ── Lettura ultimo log sync ─────────────────────────────── */

	public static function get_sync_log() {
		$log = get_option( self::OPTION_SYNC, null );
		if ( ! is_array( $log ) ) {
			return null;
		}
		return $log;
	}
}
