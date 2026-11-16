<?php
/**
 * REST API — endpoint /now e proxy RSS.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'cv-tv/v1',
			'/now',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_now' ),
				'permission_callback' => array( 'CV_TV_Auth', 'rest_permission' ),
			)
		);
		register_rest_route(
			'cv-tv/v1',
			'/stream',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_stream' ),
				'permission_callback' => array( 'CV_TV_Auth', 'rest_permission' ),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			'cv-tv/v1',
			'/guide/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_guide' ),
				'permission_callback' => array( 'CV_TV_Auth', 'rest_permission' ),
				'args'                => array(
					'id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				),
			)
		);
		register_rest_route(
			'cv-tv/v1',
			'/rss',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_rss' ),
				'permission_callback' => array( 'CV_TV_Auth', 'rest_permission' ),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	/**
	 * GET /wp-json/cv-tv/v1/now
	 * Ritorna il contenuto attivo + refresh_at per il player JS.
	 */
	public static function get_now( WP_REST_Request $request ) {
		$resolved = CV_TV_Schedule::resolve_now();
		if ( ! $resolved ) {
			$resolved = CV_TV_Schedule::get_fallback();
		}

		$loop     = true;
		$progress = true;
		$screens  = array();

		if ( $resolved ) {
			if ( 'playlist' === $resolved['type'] ) {
				$playlist = CV_TV_Playlists::get_playlist_data( (int) $resolved['id'] );
				if ( $playlist ) {
					$screens  = $playlist['screens'];
					$loop     = $playlist['loop'];
					$progress = $playlist['progress'];
				}
			} else {
				$screen = CV_TV_Screens::get_screen_data( (int) $resolved['id'], 30, 'fade' );
				if ( $screen ) {
					$screens = array( $screen );
				}
			}
		}

		$settings = CV_TV_Launcher::get_settings();
		return rest_ensure_response( array(
			'mode'          => $resolved ? $resolved['type'] : 'none',
			'screens'       => $screens,
			'loop'          => $loop,
			'progress'      => $progress,
			'refresh_at'    => CV_TV_Schedule::seconds_to_next_boundary(),
			'resolved_by'   => $resolved ? $resolved['resolved_by'] : 'none',
			'guest_name'    => CV_TV_Launcher::effective_guest_name( $settings ),
			'guest_app_url' => $settings['current_guest_app_url'] ?? '',
		) );
	}

	/**
	 * GET /wp-json/cv-tv/v1/stream?url=<encoded>
	 * Proxy HLS server-side. Scarica il file M3U8 (o segmento TS) e lo ri-serve
	 * con gli header corretti, bypassando CORS e X-Frame-Options del sorgente.
	 *
	 * Il browser vede tutto come proveniente dallo stesso dominio WordPress —
	 * nessun blocco. Funziona per stream m3u8 pubblici (SkylineWebcams, ecc.)
	 */
	public static function get_stream( WP_REST_Request $request ) {
		$raw_url = $request->get_param( 'url' );
		if ( ! $raw_url ) {
			return new WP_Error( 'missing_url', 'Missing url parameter', array( 'status' => 400 ) );
		}

		// Consenti solo URL https ben formati (sicurezza)
		$url = esc_url_raw( $raw_url );
		if ( ! $url || strpos( $url, 'https://' ) !== 0 ) {
			return new WP_Error( 'invalid_url', 'Only https URLs allowed', array( 'status' => 400 ) );
		}

		// Lista allow-list dei domini ammessi (evita uso come open proxy)
		$allowed_hosts = array(
			'livee.skylinewebcams.com',
			'cdn.skylinewebcams.com',
			'rtsp.me',
			'webcams.windy.com',
			'images-webcams.windy.com',
		);
		$parsed_host = wp_parse_url( $url, PHP_URL_HOST );
		$host_ok = false;
		foreach ( $allowed_hosts as $ah ) {
			if ( $parsed_host === $ah || substr( $parsed_host, -strlen( '.' . $ah ) ) === '.' . $ah ) {
				$host_ok = true;
				break;
			}
		}
		if ( ! $host_ok ) {
			return new WP_Error( 'disallowed_host', 'Host not in allowlist', array( 'status' => 403 ) );
		}

		$response = wp_remote_get( $url, array(
			'timeout'    => 10,
			'user-agent' => 'Mozilla/5.0 (compatible; CasaVolterraTV/1.0)',
			'headers'    => array(
				'Referer' => 'https://www.skylinewebcams.com/',
				'Origin'  => 'https://www.skylinewebcams.com',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fetch_failed', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code         = wp_remote_retrieve_response_code( $response );
		$body         = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		// Se è un file m3u8, riscrive gli URL relativi dei segmenti in URL proxy assoluti
		// in modo che hls.js carichi anche i chunk .ts attraverso questo proxy
		if ( strpos( $content_type, 'mpegurl' ) !== false || substr( $url, -5 ) === '.m3u8' ) {
			$base_url   = trailingslashit( dirname( $url ) );
			$proxy_base = rest_url( 'cv-tv/v1/stream' ) . '?url=';

			$lines = explode( "
", $body );
			$out   = array();
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( $line === '' || $line[0] === '#' ) {
					$out[] = $line;
				} else {
					// URI relativo → assoluto → proxied
					$abs = ( strpos( $line, 'http' ) === 0 ) ? $line : $base_url . $line;
					$out[] = $proxy_base . rawurlencode( $abs );
				}
			}
			$body = implode( "
", $out );
		}

		// Invia direttamente, bypassando il JSON wrapper di WP REST
		if ( ! headers_sent() ) {
			if ( $content_type ) {
				header( 'Content-Type: ' . $content_type );
			} else {
				header( 'Content-Type: application/octet-stream' );
			}
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Cache-Control: no-cache' );
			// Rimuove header che potrebbero interferire
			header_remove( 'X-Frame-Options' );
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $body;
		exit;
	}

	/**
	 * GET /wp-json/cv-tv/v1/guide/{id}
	 * Ritorna i dati di una Guida TV per il player.
	 */
	public static function get_guide( WP_REST_Request $request ) {
		$id    = (int) $request->get_param( 'id' );
		$guide = CV_TV_Guides::get_guide_data( $id );
		if ( ! $guide ) {
			return new WP_Error( 'not_found', 'Guide not found', array( 'status' => 404 ) );
		}
		return rest_ensure_response( $guide );
	}

	/**
	 * GET /wp-json/cv-tv/v1/rss?url=<encoded>
	 * Proxy server-side per feed RSS (evita CORS sul Raspberry Pi).
	 */
	public static function get_rss( WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );
		if ( ! $url ) {
			return new WP_Error( 'missing_url', 'Missing url parameter', array( 'status' => 400 ) );
		}

		$transient_key = 'cv_tv_rss_' . md5( $url );
		$cached        = get_transient( $transient_key );
		if ( false !== $cached ) {
			return rest_ensure_response( array( 'items' => $cached, 'source' => 'cache' ) );
		}

		$response = wp_remote_get( $url, array(
			'timeout' => 8,
			'headers' => array( 'Accept' => 'application/rss+xml, application/xml, text/xml' ),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'fetch_failed', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return new WP_Error( 'empty_response', 'Empty RSS response', array( 'status' => 502 ) );
		}

		// Parse RSS
		$items = array();
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body );
		libxml_clear_errors();

		if ( $xml ) {
			$entries = $xml->channel->item ?? array();
			foreach ( $entries as $item ) {
				$title = trim( (string) $item->title );
				if ( $title ) {
					$items[] = html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				}
				if ( count( $items ) >= 10 ) {
					break;
				}
			}
		}

		set_transient( $transient_key, $items, 15 * MINUTE_IN_SECONDS );

		return rest_ensure_response( array( 'items' => $items, 'source' => 'live' ) );
	}
}
