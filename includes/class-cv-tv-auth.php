<?php
/**
 * Casa Volterra TV — Autenticazione via Token
 *
 * Token condiviso tra WordPress e il Raspberry Pi.
 * Validato su ogni richiesta frontend, REST e proxy.
 *
 * Flusso:
 *   1. Chromium apre /tv/?cv_token=XXXX → cookie 24h → redirect pulito
 *   2. Ricariche usano il cookie
 *   3. JS invia header X-CV-Token su ogni fetch
 *   4. Proxy Python valida lo stesso header
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Auth {

	const COOKIE_NAME = 'cv_tv_token';
	const HEADER_NAME = 'X-CV-Token';
	const PARAM_NAME  = 'cv_token';

	// 86400 = 24 ore — NON usare DAY_IN_SECONDS come class const (PHP fatal error)
	const COOKIE_TTL = 86400;

	/* ── Token management ───────────────────────────────────── */

	public static function generate_token() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	public static function get_token() {
		$s = CV_TV_Launcher::get_settings();
		$t = trim( (string) ( $s['auth_token'] ?? '' ) );
		if ( $t ) {
			return $t;
		}
		$new_token       = self::generate_token();
		$s['auth_token'] = $new_token;
		update_option( CV_TV_OPTION, $s );
		return $new_token;
	}

	public static function bypass_active() {
		$s = CV_TV_Launcher::get_settings();
		return '1' === (string) ( $s['auth_bypass'] ?? '0' );
	}

	/* ── Validation ─────────────────────────────────────────── */

	public static function token_valid( $incoming ) {
		$stored = self::get_token();
		if ( ! $stored || ! $incoming ) {
			return false;
		}
		return hash_equals( $stored, $incoming );
	}

	public static function is_authorized() {
		if ( self::bypass_active() ) {
			return true;
		}
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}
		// Cookie
		$cookie = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ?? '' ) );
		if ( $cookie && self::token_valid( $cookie ) ) {
			return true;
		}
		// Header HTTP
		$header = '';
		$server_key = 'HTTP_' . strtoupper( str_replace( '-', '_', self::HEADER_NAME ) );
		$header = sanitize_text_field( $_SERVER[ $server_key ] ?? '' );
		if ( ! $header && function_exists( 'getallheaders' ) ) {
			$all    = getallheaders();
			$header = sanitize_text_field( $all[ self::HEADER_NAME ] ?? $all[ strtolower( self::HEADER_NAME ) ] ?? '' );
		}
		if ( $header && self::token_valid( $header ) ) {
			return true;
		}
		// Query param
		$param = sanitize_text_field( $_GET[ self::PARAM_NAME ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( $param && self::token_valid( $param ) ) {
			return true;
		}
		return false;
	}

	public static function maybe_set_cookie_and_redirect() {
		if ( self::bypass_active() ) {
			return;
		}
		$param = sanitize_text_field( $_GET[ self::PARAM_NAME ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $param || ! self::token_valid( $param ) ) {
			return;
		}
		self::set_cookie( $param );
		wp_redirect( remove_query_arg( self::PARAM_NAME ) );
		exit;
	}

	public static function set_cookie( $token ) {
		setcookie(
			self::COOKIE_NAME,
			$token,
			array(
				'expires'  => time() + self::COOKIE_TTL,
				'path'     => '/',
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	public static function clear_cookie() {
		setcookie( self::COOKIE_NAME, '', array(
			'expires'  => time() - 3600,
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'Lax',
		) );
	}

	/* ── Frontend gate ──────────────────────────────────────── */

	public static function render_denied() {
		status_header( 403 );
		nocache_headers();
		?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Accesso TV — Casa Volterra</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;display:flex;align-items:center;justify-content:center;
  background:#0a0e1f;color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}
.box{text-align:center;padding:40px;max-width:480px}
.icon{font-size:4rem;margin-bottom:20px}
h1{font-size:1.6rem;margin-bottom:12px;color:#93c5fd}
p{color:rgba(255,255,255,.55);font-size:1rem;line-height:1.6}
</style>
</head>
<body>
<div class="box">
  <div class="icon">📺🔒</div>
  <h1>Casa Volterra TV</h1>
  <p>Questa pagina è riservata all'interfaccia TV dell'appartamento.<br>
     Accedi tramite il Raspberry Pi in kiosk mode.</p>
</div>
</body>
</html>
		<?php
		exit;
	}

	/* ── REST permission callback ───────────────────────────── */

	public static function rest_permission() {
		if ( self::is_authorized() ) {
			return true;
		}
		return new WP_Error(
			'cv_tv_unauthorized',
			'Token TV non valido o mancante.',
			array( 'status' => 403 )
		);
	}

	/* ── Admin notice bypass ────────────────────────────────── */

	public static function admin_bypass_notice() {
		if ( ! self::bypass_active() ) {
			return;
		}
		?>
		<div class="notice notice-warning" style="border-left-color:#f97316;background:#fff7ed;">
			<p>
				<strong>⚠️ Casa Volterra TV — Modalità bypass attiva</strong><br>
				Il controllo token è <strong>disabilitato</strong>: chiunque può accedere alla pagina TV.
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cv_tv_screen&page=cv-tv-settings#cv-tv-security' ) ); ?>">
					Vai alle impostazioni
				</a> per disabilitarlo prima di andare in produzione.
			</p>
		</div>
		<?php
	}

	/* ── Kiosk URL ──────────────────────────────────────────── */

	public static function get_kiosk_url() {
		$s    = CV_TV_Launcher::get_settings();
		$base = home_url( '/' . trim( (string) $s['launcher_path'], '/' ) . '/' );
		if ( self::bypass_active() ) {
			return $base;
		}
		return add_query_arg( self::PARAM_NAME, self::get_token(), $base );
	}
}
