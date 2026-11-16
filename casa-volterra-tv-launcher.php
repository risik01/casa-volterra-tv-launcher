<?php
/**
 * Plugin Name: Casa Volterra TV Launcher
 * Description: Sistema hospitality TV stile Yodeck: Screens, Playlist, Scheduler e Template library per Raspberry Pi in kiosk mode.
 * Version:     4.3.0
 * Author:      Casa Volterra
 * Text Domain: casa-volterra-tv-launcher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CV_TV_VERSION', '4.3.0' );
define( 'CV_TV_PATH', plugin_dir_path( __FILE__ ) );
define( 'CV_TV_URL', plugin_dir_url( __FILE__ ) );
define( 'CV_TV_OPTION', 'cv_tv_settings' );

require_once CV_TV_PATH . 'includes/class-cv-tv-auth.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-screens.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-playlists.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-schedule.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-templates.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-renderer.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-rest.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-guides.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-ai.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-browser.php';
require_once CV_TV_PATH . 'includes/class-cv-tv-smoobu.php';

/* ── Aggiornamenti automatici da GitHub ─────────────────────
 *
 * Libreria: Plugin Update Checker 6.0 (licenza MIT), in /plugin-update-checker.
 * WordPress controlla il repository ogni 12 ore: quando l'header "Version" di
 * questo file sul branch main è più alto di quello installato, l'aggiornamento
 * compare in Plugin/Aggiornamenti come per i plugin ufficiali.
 *
 * Per pubblicare una versione: alza "Version" e CV_TV_VERSION qui sopra, poi
 * fai push su main. (Se nel repo crei release o tag, hanno la precedenza sul
 * branch: in quel caso il numero di versione è quello del tag, es. v4.3.1.)
 *
 * Opzionali in wp-config.php:
 *   define( 'CV_TV_GITHUB_REPO', 'https://github.com/utente/altro-repo/' );
 *   define( 'CV_GITHUB_TOKEN', 'ghp_...' );  // serve solo se il repo è privato
 */
if ( ! defined( 'CV_TV_GITHUB_REPO' ) ) {
	define( 'CV_TV_GITHUB_REPO', 'https://github.com/risik01/casa-volterra-tv-launcher/' );
}

function cv_tv_github_updater() {
	static $checker = null;
	if ( null !== $checker ) {
		return $checker;
	}
	$checker = false;

	$library = CV_TV_PATH . 'plugin-update-checker/plugin-update-checker.php';
	if ( PHP_VERSION_ID < 70400 || ! is_readable( $library ) ) {
		return $checker;
	}

	// Un problema dell'updater non deve mai bloccare il sito.
	try {
		require_once $library;
		$factory = '\YahnisElsts\PluginUpdateChecker\v6\PucFactory';
		if ( class_exists( $factory ) ) {
			$checker = $factory::buildUpdateChecker( CV_TV_GITHUB_REPO, __FILE__, 'casa-volterra-tv-launcher' );
			$checker->setBranch( 'main' );
			if ( defined( 'CV_GITHUB_TOKEN' ) && CV_GITHUB_TOKEN ) {
				$checker->setAuthentication( CV_GITHUB_TOKEN );
			}
		}
	} catch ( \Throwable $e ) {
		$checker = false;
	}
	return $checker;
}
cv_tv_github_updater();

final class CV_TV_Launcher {

	const QUERY_VAR = 'cv_tv_launcher';

	/* ── Bootstrap ──────────────────────────────────────────── */

	public static function init() {
		CV_TV_Screens::init();
		CV_TV_Playlists::init();
		CV_TV_Schedule::init();
		CV_TV_Templates::init();
		CV_TV_Rest::init();
		CV_TV_Guides::init();
		CV_TV_AI::init();
		CV_TV_Browser::init();
		CV_TV_Smoobu::init();

		add_action( 'init',              array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'query_vars',        array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_guide_preview' ) );
		add_action( 'admin_init',        array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu',        array( __CLASS__, 'register_settings_menu' ) );
		add_action( 'admin_notices',     array( 'CV_TV_Auth', 'admin_bypass_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'update_option_' . CV_TV_OPTION, array( __CLASS__, 'on_settings_updated' ), 10, 2 );
		add_action( 'admin_post_cv_tv_regenerate_token', array( __CLASS__, 'handle_regenerate_token' ) );
		add_action( 'admin_post_cv_tv_set_token',        array( __CLASS__, 'handle_set_token' ) );
	}

	public static function activate() {
		CV_TV_Screens::register_post_type();
		CV_TV_Playlists::register_post_type();
		CV_TV_AI::register_post_type();
		self::register_rewrite();
		CV_TV_AI::register_rewrite();
		CV_TV_Browser::register_rewrite();
		// Genera token al primo avvio se non esiste
		$s = self::get_settings();
		if ( empty( $s['auth_token'] ) ) {
			$s['auth_token'] = CV_TV_Auth::generate_token();
			update_option( CV_TV_OPTION, $s );
		}
		flush_rewrite_rules();
	}

	public static function deactivate() {
		CV_TV_Smoobu::deactivate();
		flush_rewrite_rules();
	}

	/* ── Settings ───────────────────────────────────────────── */

	public static function default_settings() {
		return array(
			'launcher_path'      => 'tv',
			'footer_text'        => 'Casa Volterra · Volterra, Toscana',
			'opening_note'       => 'Usa le frecce del telecomando e premi OK.',
			'next_checkin'       => '',
			'next_checkout'      => '',
			'home_assistant_url' => '',
			'local_proxy_url'    => 'http://localhost:8765',
			'kodi_enabled'       => '1',
			'netflix_enabled'    => '1',
			'browser_enabled'    => '1',
			'auth_token'         => '',
			'auth_bypass'        => '0',
			'smoobu_api_key'     => '',
			'smoobu_api_secret'  => '',  // Secret per la firma HMAC delle richieste
			'smoobu_apartment_id'   => '',
			'smoobu_apartment_name' => '',
			'smoobu_guest_token'    => '',  // Token 't=' della guest guide URL
			// Dati ospite corrente (da Smoobu)
			// ── Ospite ATTUALE (in casa oggi: arrival <= today <= departure) ──
			'current_guest_name'    => '',
			'current_booking_id'    => 0,
			'current_guest_app_url' => '',
			'current_checkin'       => '',
			'current_checkin_time'  => '',
			'current_checkout'      => '',
			'current_checkout_time' => '',
			// ── Prossimo ospite (arrival > today) — usato dallo scheduler ──
			'next_guest_name'    => '',
			'next_booking_id'    => 0,
			'next_guest_app_url' => '',
			'next_checkin_time'       => '',
			'next_checkout_time'      => '',
			// Automazione Arrivi: screen + finestra temporale
			'event_checkin_screen_id'     => 0,
			'event_checkin_advance_hours' => 0,    // ore PRIMA del checkin per iniziare
			'event_checkin_duration_hours'=> 6,    // durata in ore
			'event_checkin_default_time'  => '15:00', // fallback se Smoobu non ha l\'ora
			// Automazione Partenze: screen + finestra temporale
			'event_checkout_screen_id'     => 0,
			'event_checkout_advance_hours' => 4,   // ore PRIMA del checkout per iniziare
			'event_checkout_extra_hours'   => 0,   // ore DOPO il checkout (0 = finisce all&#39;ora esatta)
			'event_checkout_default_time'  => '11:00', // fallback se Smoobu non ha l\'ora
		);
	}

	public static function get_settings() {
		$saved = get_option( CV_TV_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::default_settings() );
	}

	/**
	 * Restituisce il nome ospite "fisicamente presente" in questo momento.
	 *
	 * Regole:
	 *  1. Checkout oggi e ora >= checkout_time → appartamento vuoto: ''.
	 *  2. Eccezione turnover: vale reg.1 MA prossimo ospite arriva oggi
	 *     e now >= next_checkin_time → nome prossimo ospite.
	 *  3. Altrimenti → current_guest_name come prima.
	 *
	 * Usare questo metodo ovunque al posto di $settings['current_guest_name'].
	 */
	public static function effective_guest_name( $settings = null ) {
		if ( null === $settings ) {
			$settings = self::get_settings();
		}
		$today    = wp_date( 'Y-m-d' );
		$now_hhmm = wp_date( 'H:i' );
		$co_date  = $settings['current_checkout']      ?? '';
		$co_time  = $settings['current_checkout_time'] ?? '';

		$ci_date  = $settings['current_checkin']      ?? '';
		$ci_time  = $settings['current_checkin_time'] ?? '';

		// ── Caso 1: ospite arriva OGGI ma non ancora nella finestra ──────────────
		if ( $ci_date === $today && $ci_time !== '' ) {
			$adv_h        = max( 0, (int) ( $settings['event_checkin_advance_hours'] ?? 0 ) );
			list( $ci_h, $ci_m ) = array_map( 'intval', explode( ':', $ci_time ) );
			$window_start = ( $ci_h * 60 + $ci_m ) - ( $adv_h * 60 );
			$now_min      = (int) wp_date( 'H' ) * 60 + (int) wp_date( 'i' );
			if ( $now_min < $window_start ) {
				return '';
			}
		}

		// ── Caso 2: checkout oggi già avvenuto ────────────────────────────────────
		if ( $co_date === $today && $co_time !== '' && $now_hhmm >= $co_time ) {
			$next_ci_date = $settings['next_checkin']      ?? '';
			$next_ci_time = $settings['next_checkin_time'] ?? '';
			$next_ci_name = $settings['next_guest_name']   ?? '';
			if ( $next_ci_date === $today && $next_ci_time !== '' && $next_ci_name !== '' ) {
				$adv_h2            = max( 0, (int) ( $settings['event_checkin_advance_hours'] ?? 0 ) );
				list( $nci_h, $nci_m ) = array_map( 'intval', explode( ':', $next_ci_time ) );
				$next_win_start    = ( $nci_h * 60 + $nci_m ) - ( $adv_h2 * 60 );
				$now_min2          = (int) wp_date( 'H' ) * 60 + (int) wp_date( 'i' );
				if ( $now_min2 >= $next_win_start ) {
					return $next_ci_name;
				}
			}
			return '';
		}

		// ── Caso 3: ospite in casa ────────────────────────────────────────────────
		return $settings['current_guest_name'] ?? '';
	}


	public static function sanitize_settings( $input ) {
		$defaults = self::default_settings();
		$input    = is_array( $input ) ? $input : array();

		// Recupera il token esistente PRIMA di sovrascrivere
		$existing       = get_option( CV_TV_OPTION, array() );
		$existing       = is_array( $existing ) ? $existing : array();
		$existing_token = sanitize_text_field( $existing['auth_token'] ?? '' );

		// Il token dal form (campo hidden)
		$form_token = sanitize_text_field( $input['auth_token'] ?? '' );

		// Priorità: form_token > existing_token > genera nuovo
		if ( $form_token ) {
			$final_token = $form_token;
		} elseif ( $existing_token ) {
			$final_token = $existing_token;
		} else {
			$final_token = CV_TV_Auth::generate_token();
		}

		// API Secret Smoobu: il campo del form è sempre vuoto (il secret non
		// viene mai rispedito al browser), quindi vuoto = tieni quello salvato.
		$smoobu_secret = CV_TV_Smoobu::sanitize_secret( $input['smoobu_api_secret'] ?? '' );
		if ( '' === $smoobu_secret ) {
			$smoobu_secret = CV_TV_Smoobu::sanitize_secret( $existing['smoobu_api_secret'] ?? '' );
		}

		$sanitized = array(
			'launcher_path'      => sanitize_title( $input['launcher_path'] ?? $defaults['launcher_path'] ),
			'footer_text'        => sanitize_text_field( $input['footer_text'] ?? $defaults['footer_text'] ),
			'opening_note'       => sanitize_text_field( $input['opening_note'] ?? $defaults['opening_note'] ),
			'next_checkin'       => sanitize_text_field( $input['next_checkin'] ?? '' ),
			'next_checkout'      => sanitize_text_field( $input['next_checkout'] ?? '' ),
			'home_assistant_url' => esc_url_raw( $input['home_assistant_url'] ?? '' ),
			'local_proxy_url'    => esc_url_raw( $input['local_proxy_url'] ?? 'http://localhost:8765' ),
			'kodi_enabled'       => isset( $input['kodi_enabled'] )    ? '1' : '0',
			'netflix_enabled'    => isset( $input['netflix_enabled'] )  ? '1' : '0',
			'browser_enabled'    => isset( $input['browser_enabled'] )  ? '1' : '0',
			'auth_token'         => $final_token,
			'auth_bypass'        => isset( $input['auth_bypass'] ) ? '1' : '0',
			'smoobu_api_key'      => sanitize_text_field( $input['smoobu_api_key'] ?? '' ),
			'smoobu_api_secret'   => $smoobu_secret,
			'smoobu_apartment_id'   => sanitize_text_field( $input['smoobu_apartment_id'] ?? '' ),
			'smoobu_apartment_name' => sanitize_text_field( $input['smoobu_apartment_name'] ?? '' ),
			'smoobu_guest_token'    => sanitize_text_field( $input['smoobu_guest_token'] ?? '' ),
			'current_guest_name'      => sanitize_text_field( $input['current_guest_name'] ?? '' ),
			'current_booking_id'      => absint( $input['current_booking_id'] ?? 0 ),
			'current_guest_app_url'   => esc_url_raw( $input['current_guest_app_url'] ?? '' ),
			'current_checkin'         => sanitize_text_field( $input['current_checkin'] ?? '' ),
			'current_checkin_time'    => sanitize_text_field( $input['current_checkin_time'] ?? '' ),
			'current_checkout'        => sanitize_text_field( $input['current_checkout'] ?? '' ),
			'current_checkout_time'   => sanitize_text_field( $input['current_checkout_time'] ?? '' ),
			'next_guest_name'         => sanitize_text_field( $input['next_guest_name'] ?? '' ),
			'next_booking_id'         => absint( $input['next_booking_id'] ?? 0 ),
			'next_guest_app_url'      => esc_url_raw( $input['next_guest_app_url'] ?? '' ),
			'next_checkin_time'       => sanitize_text_field( $input['next_checkin_time'] ?? '' ),
			'next_checkout_time'      => sanitize_text_field( $input['next_checkout_time'] ?? '' ),
			'event_checkin_screen_id'      => absint( $input['event_checkin_screen_id'] ?? 0 ),
			'event_checkin_advance_hours'  => absint( $input['event_checkin_advance_hours'] ?? 0 ),
			'event_checkin_duration_hours' => max( 1, absint( $input['event_checkin_duration_hours'] ?? 6 ) ),
			'event_checkin_default_time'   => sanitize_text_field( $input['event_checkin_default_time'] ?? '15:00' ),
			'event_checkout_screen_id'     => absint( $input['event_checkout_screen_id'] ?? 0 ),
			'event_checkout_advance_hours' => absint( $input['event_checkout_advance_hours'] ?? 4 ),
			'event_checkout_extra_hours'   => absint( $input['event_checkout_extra_hours'] ?? 0 ),
			'event_checkout_default_time'  => sanitize_text_field( $input['event_checkout_default_time'] ?? '11:00' ),
		);

		if ( empty( $sanitized['launcher_path'] ) ) {
			$sanitized['launcher_path'] = $defaults['launcher_path'];
		}

		return $sanitized;
	}

	public static function register_settings() {
		register_setting(
			'cv_tv_settings_group',
			CV_TV_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	/* ── Admin assets ───────────────────────────────────────── */

	public static function enqueue_admin_assets( $hook ) {
		if ( 'cv_tv_screen_page_cv-tv-settings' !== $hook ) {
			return;
		}
		wp_add_inline_style( 'wp-admin', self::admin_css() );
		wp_add_inline_script( 'jquery', self::admin_js() );
	}

	private static function admin_css() {
		return '
.cv-tv-wrap{max-width:900px}
.cv-tv-section{background:#fff;border:1px solid #c3c4c7;border-radius:8px;margin-bottom:24px;overflow:hidden}
.cv-tv-section-head{background:#f6f7f7;border-bottom:1px solid #c3c4c7;padding:14px 20px;display:flex;align-items:center;gap:10px}
.cv-tv-section-head h2{font-size:1rem;margin:0;font-weight:600;color:#1d2327}
.cv-tv-section-body{padding:20px}
.cv-tv-section-body .form-table{margin:0}
.cv-tv-section-body .form-table th{padding-left:0;width:220px}
.cv-tv-toggle-row{display:flex;align-items:center;gap:14px}
.cv-tv-toggle{position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0}
.cv-tv-toggle input{opacity:0;width:0;height:0}
.cv-tv-toggle-slider{position:absolute;inset:0;background:#ccc;border-radius:34px;cursor:pointer;transition:background .2s}
.cv-tv-toggle-slider:before{content:"";position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:transform .2s}
.cv-tv-toggle input:checked + .cv-tv-toggle-slider{background:#2271b1}
.cv-tv-toggle input:checked + .cv-tv-toggle-slider:before{transform:translateX(20px)}
.cv-tv-toggle.danger input:checked + .cv-tv-toggle-slider{background:#f97316}
.cv-tv-token-val{font-family:monospace;font-size:.85rem;background:#f0f0f1;border:1px solid #c3c4c7;border-radius:4px;padding:6px 12px;word-break:break-all;color:#1d2327;display:block;margin-bottom:8px}
.cv-tv-kiosk-url{font-family:monospace;font-size:.8rem;background:#f0f8ff;border:1px solid #b3d4f5;border-radius:4px;padding:8px 12px;word-break:break-all;color:#0a4a8c;display:block;margin-bottom:8px}
.cv-tv-badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.75rem;font-weight:600;text-transform:uppercase}
.cv-tv-badge-ok{background:#dcfce7;color:#166534}
.cv-tv-badge-warn{background:#fef3c7;color:#92400e}
.cv-tv-sync-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px}
.cv-tv-sync-form input{flex:1;min-width:280px;font-family:monospace;font-size:.85rem}
';
	}

	private static function admin_js() {
		return '
jQuery(function($){

/* ── Smoobu: carica appartamenti ── */
$(document).on("click","#cv-smoobu-load-apt",function(){
	var btn=$(this), nonce=btn.data("nonce");
	btn.prop("disabled",true).text("Caricamento...");
	$.post(ajaxurl,{action:"cv_tv_smoobu_apartments",nonce:nonce},function(r){
		btn.prop("disabled",false).text("Carica appartamenti");
		if(!r.success){alert("Errore: "+(r.data&&r.data.message?r.data.message:"risposta non valida"));return;}
		var sel=$("#cv_tv_smoobu_apt"), cur=sel.val();
		sel.empty().append($("<option>").val("").text("— Tutti —"));
		$.each(r.data.apartments,function(i,a){
			sel.append($("<option>").val(a.id).text(a.name+(a.id?" (ID: "+a.id+")":"")));
		});
		if(cur)sel.val(cur);
		if(r.data.apartments.length===1)sel.val(r.data.apartments[0].id);
		// Aggiorna il nome nascosto quando cambia la selezione
		sel.off("change.smoobu").on("change.smoobu",function(){
			var chosen=$.grep(r.data.apartments,function(a){return a.id===$(this).val();}.bind(this));
			$("#cv_tv_smoobu_apt_name").val(chosen.length?chosen[0].name:"");
		});
		sel.trigger("change.smoobu");
	}).fail(function(){btn.prop("disabled",false).text("Carica appartamenti");alert("Richiesta fallita.");});
});

/* ── Smoobu: sync manuale ── */
$(document).on("click","#cv-smoobu-sync-now",function(){
	var btn=$(this), nonce=btn.data("nonce"), st=$("#cv-smoobu-sync-status");
	btn.prop("disabled",true).text("Sincronizzazione...");
	st.css("color","#555").text("In corso...");
	$.post(ajaxurl,{action:"cv_tv_smoobu_sync",nonce:nonce},function(r){
		btn.prop("disabled",false).text("🔄 Sincronizza ora");
		if(!r.success){st.css("color","#d63638").text("⚠️ Errore: "+(r.data&&r.data.message?r.data.message:"sconosciuto"));return;}
		var d=r.data;
		st.css("color","#16a34a").text("✅ OK — check-in: "+(d.checkin||"nessuno")+" | check-out: "+(d.checkout||"nessuno")+" | "+d.count+" prenotazioni");
		// Aggiorna campi ospite attuale
		$(\'[name$="[current_checkin]"]\').val(d.current_checkin||"");
		$(\'[name$="[current_checkin_time]"]\').val(d.current_checkin_time||"");
		$(\'[name$="[current_checkout]"]\').val(d.current_checkout||"");
		$(\'[name$="[current_checkout_time]"]\').val(d.current_checkout_time||"");
		$(\'[name*="current_guest_name"]\').val(d.current_guest_name||"");
		$(\'[name*="current_booking_id"]\').val(d.current_booking_id||"");
		$(\'[name*="current_guest_app_url"]\').val(d.current_guest_app_url||"");
		// Aggiorna campi prossimo ospite
		$(\'[name*="next_guest_name"]\').val(d.guest_name||"");
		$(\'[name*="next_booking_id"]\').val(d.booking_id||"");
		$(\'[name*="next_guest_app_url"]\').val(d.guest_app_url||"");
		$(\'[name$="[next_checkin]"]\').val(d.checkin||"");
		$(\'[name$="[next_checkin_time]"]\').val(d.checkin_time||"");
		$(\'[name$="[next_checkout]"]\').val(d.checkout||"");
		$(\'[name$="[next_checkout_time]"]\').val(d.checkout_time||"");
		if(d.checkin_time){
			$(\'[name*="next_checkin_time"]\').val(d.checkin_time);
			$(".cv-tv-event-preview[data-type=checkin]").data("smoobu-time",d.checkin_time).attr("data-smoobu-time",d.checkin_time);
		}
		if(d.checkout_time){
			$(\'[name*="next_checkout_time"]\').val(d.checkout_time);
			$(".cv-tv-event-preview[data-type=checkout]").data("smoobu-time",d.checkout_time).attr("data-smoobu-time",d.checkout_time);
		}
		$(".cv-tv-event-preview").each(function(){ updateEventPreview($(this)); });
	}).fail(function(){btn.prop("disabled",false).text("🔄 Sincronizza ora");st.css("color","#d63638").text("Richiesta fallita.");});
});

	/* ── Event window preview ── */
	function parseHM(t){
		if(!t)return null;
		var p=t.split(":");return{h:parseInt(p[0],10),m:parseInt(p[1]||0,10)};
	}
	function addMinutes(hm,mins){
		var total=hm.h*60+hm.m+mins;
		if(total<0)total+=1440;
		total=total%1440;
		return{h:Math.floor(total/60),m:total%60};
	}
	function fmt(hm){return("0"+hm.h).slice(-2)+":"+("0"+hm.m).slice(-2);}
	function updateEventPreview($el){
		var type=$el.data("type");
		var smoobuTime=$el.data("smoobu-time")||"";
		var defTime=$el.data("default-time")||"";
		var useTime=smoobuTime||defTime;
		if(!useTime){$el.text("Configura un orario di default.");return;}
		var base=parseHM(useTime);
		if(!base){$el.text("Orario non valido.");return;}
		if(type==="checkin"){
			var adv=parseInt($("[name*=event_checkin_advance_hours]").val(),10)||0;
			var dur=parseInt($("[name*=event_checkin_duration_hours]").val(),10)||6;
			var start=addMinutes(base,-adv*60);
			var end=addMinutes(base,dur*60);
			$el.html("&#x1F4CC; Finestra: <strong>"+fmt(start)+"</strong> &rarr; <strong>"+fmt(end)+"</strong>"+(smoobuTime?" (ora da Smoobu: "+smoobuTime+")":" (ora di default: "+defTime+")"));
		} else {
			var adv2=parseInt($("[name*=event_checkout_advance_hours]").val(),10)||4;
			var ext=parseInt($("[name*=event_checkout_extra_hours]").val(),10)||0;
			var start2=addMinutes(base,-adv2*60);
			var end2=addMinutes(base,ext*60);
			$el.html("&#x1F4CC; Finestra: <strong>"+fmt(start2)+"</strong> &rarr; <strong>"+fmt(end2)+"</strong>"+(smoobuTime?" (ora da Smoobu: "+smoobuTime+")":" (ora di default: "+defTime+")"));
		}
	}
	$(".cv-tv-event-preview").each(function(){ updateEventPreview($(this)); });
	$(document).on("input change","[name*=event_checkin],[name*=event_checkout]",function(){
		var type=$(this).attr("name").indexOf("checkout")>=0?"checkout":"checkin";
		$(".cv-tv-event-preview[data-type="+type+"]").each(function(){ updateEventPreview($(this)); });
	});

	$(document).on("click","#cv-tv-copy-url",function(){
		var txt=$("#cv-tv-kiosk-url-val").text();
		if(navigator.clipboard){navigator.clipboard.writeText(txt);}
		else{var el=document.createElement("textarea");el.value=txt;document.body.appendChild(el);el.select();document.execCommand("copy");document.body.removeChild(el);}
		$(this).text("Copiato!").addClass("button-primary");
		setTimeout(function(){$("#cv-tv-copy-url").text("Copia URL").removeClass("button-primary");},2000);
	});
	$(document).on("click","#cv-tv-regen-btn",function(){
		return confirm("Rigenerare il token?\nIl Raspberry Pi perdera accesso finche non aggiorni cv-tv-config.json con il nuovo token e riavvii il proxy.");
	});
	$("form").on("submit",function(){
		if($("#cv_tv_auth_bypass").is(":checked")){
			return confirm("Stai salvando con il BYPASS attivo: la pagina TV sara accessibile senza token. Sicuro?");
		}
	});
});
';
	}

	/* ── Settings menu ──────────────────────────────────────── */

	public static function register_settings_menu() {
		add_submenu_page(
			'edit.php?post_type=cv_tv_screen',
			__( 'Impostazioni TV', 'casa-volterra-tv-launcher' ),
			__( 'Impostazioni', 'casa-volterra-tv-launcher' ),
			'manage_options',
			'cv-tv-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/* ── Admin actions ──────────────────────────────────────── */

	public static function handle_regenerate_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permesso negato.' );
		}
		check_admin_referer( 'cv_tv_regenerate_token' );
		$s = self::get_settings();
		$s['auth_token'] = CV_TV_Auth::generate_token();
		update_option( CV_TV_OPTION, $s );
		wp_redirect( add_query_arg( array(
			'post_type'   => 'cv_tv_screen',
			'page'        => 'cv-tv-settings',
			'token_regen' => '1',
		), admin_url( 'edit.php' ) ) );
		exit;
	}

	public static function handle_set_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permesso negato.' );
		}
		check_admin_referer( 'cv_tv_set_token' );
		$token = trim( sanitize_text_field( wp_unslash( $_POST['cv_tv_manual_token'] ?? '' ) ) );
		if ( empty( $token ) ) {
			wp_redirect( add_query_arg( array(
				'post_type'  => 'cv_tv_screen',
				'page'       => 'cv-tv-settings',
				'token_err'  => 'empty',
			), admin_url( 'edit.php' ) ) );
			exit;
		}
		$s = self::get_settings();
		$s['auth_token'] = $token;
		update_option( CV_TV_OPTION, $s );
		wp_redirect( add_query_arg( array(
			'post_type' => 'cv_tv_screen',
			'page'      => 'cv-tv-settings',
			'token_set' => '1',
		), admin_url( 'edit.php' ) ) );
		exit;
	}

	/* ── Settings page ──────────────────────────────────────── */

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s            = self::get_settings();
		$bypass       = '1' === (string) $s['auth_bypass'];
		$token        = CV_TV_Auth::get_token();
		$kiosk_url    = CV_TV_Auth::get_kiosk_url();
		$launcher_url = home_url( '/' . trim( (string) $s['launcher_path'], '/' ) . '/' );
		$proxy_url    = trim( (string) ( $s['local_proxy_url'] ?? '' ) );
		// Screens list for event automation selects
		$screens      = get_posts( array( 'post_type' => 'cv_tv_screen', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => false ) );

		// Notices
		if ( isset( $_GET['token_regen'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>&#x2705; Token rigenerato. Aggiorna <code>cv-tv-config.json</code> sul Raspberry Pi e riavvia il proxy.</p></div>';
		}
		if ( isset( $_GET['token_set'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>&#x2705; Token sincronizzato. WordPress e Raspberry Pi ora usano lo stesso token.</p></div>';
		}
		if ( isset( $_GET['token_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-error is-dismissible"><p>&#x274C; Token non valido: il campo era vuoto.</p></div>';
		}
		?>
		<div class="wrap cv-tv-wrap">
		<h1>&#x2699;&#xFE0F; <?php esc_html_e( 'Impostazioni Casa Volterra TV', 'casa-volterra-tv-launcher' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'cv_tv_settings_group' ); ?>
			<input type="hidden"
				name="<?php echo esc_attr( CV_TV_OPTION ); ?>[auth_token]"
				value="<?php echo esc_attr( $token ); ?>">

			<!-- ══ SICUREZZA ═══════════════════════════════════════ -->
			<div class="cv-tv-section" id="cv-tv-security">
				<div class="cv-tv-section-head">
					<span style="font-size:1.3rem">&#x1F510;</span>
					<h2><?php esc_html_e( 'Sicurezza — Token di accesso', 'casa-volterra-tv-launcher' ); ?></h2>
					<?php if ( $bypass ) : ?>
						<span class="cv-tv-badge cv-tv-badge-warn">&#x26A0;&#xFE0F; BYPASS ATTIVO</span>
					<?php else : ?>
						<span class="cv-tv-badge cv-tv-badge-ok">&#x2713; Token abilitato</span>
					<?php endif; ?>
				</div>
				<div class="cv-tv-section-body">
					<table class="form-table">

						<!-- Bypass toggle -->
						<tr>
							<th scope="row">
								<label for="cv_tv_auth_bypass"><?php esc_html_e( 'Modalità bypass (sviluppo)', 'casa-volterra-tv-launcher' ); ?></label>
							</th>
							<td>
								<div class="cv-tv-toggle-row">
									<label class="cv-tv-toggle danger">
										<input type="checkbox" id="cv_tv_auth_bypass"
											name="<?php echo esc_attr( CV_TV_OPTION ); ?>[auth_bypass]"
											value="1" <?php checked( $bypass ); ?>>
										<span class="cv-tv-toggle-slider"></span>
									</label>
									<?php if ( $bypass ) : ?>
										<strong style="color:#c2410c;">DISABILITATO — chiunque accede senza token</strong>
									<?php else : ?>
										<span>Disabilitato — controllo token <strong>attivo</strong></span>
									<?php endif; ?>
								</div>
								<p class="description" style="margin-top:6px">
									<?php esc_html_e( 'Solo per sviluppo/test locale. Tieni sempre OFF in produzione.', 'casa-volterra-tv-launcher' ); ?>
								</p>
							</td>
						</tr>

						<!-- Token corrente -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Token segreto', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<code class="cv-tv-token-val"><?php echo esc_html( $token ); ?></code>
								<a id="cv-tv-regen-btn"
									href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cv_tv_regenerate_token' ), 'cv_tv_regenerate_token' ) ); ?>"
									class="button button-secondary">
									&#x1F504; <?php esc_html_e( 'Rigenera token', 'casa-volterra-tv-launcher' ); ?>
								</a>
								<p class="description" style="margin-top:6px">
									<?php esc_html_e( 'Deve corrispondere al campo "token" in cv-tv-config.json sul Raspberry Pi.', 'casa-volterra-tv-launcher' ); ?>
								</p>
							</td>
						</tr>

						<!-- URL Kiosk -->
						<tr>
							<th scope="row"><?php esc_html_e( 'URL Kiosk Raspberry Pi', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<?php if ( $bypass ) : ?>
									<p style="color:#92400e;background:#fef3c7;padding:8px 12px;border-radius:4px;">
										&#x26A0;&#xFE0F; Bypass attivo — usa: <code><?php echo esc_html( $launcher_url ); ?></code>
									</p>
								<?php else : ?>
									<code class="cv-tv-kiosk-url" id="cv-tv-kiosk-url-val"><?php echo esc_html( $kiosk_url ); ?></code>
									<button type="button" id="cv-tv-copy-url" class="button button-secondary">
										&#x1F4CB; <?php esc_html_e( 'Copia URL', 'casa-volterra-tv-launcher' ); ?>
									</button>
									<p class="description" style="margin-top:6px">
										<?php esc_html_e( 'Incolla questo URL nel campo tv_url di cv-tv-config.json. Il token viene incluso al primo accesso e salvato come cookie per 24h.', 'casa-volterra-tv-launcher' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>

						<!-- Snippet config.json -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Snippet cv-tv-config.json', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<p class="description" style="margin-bottom:6px">
									<?php esc_html_e( 'Aggiungi nella sezione "proxy" di cv-tv-config.json:', 'casa-volterra-tv-launcher' ); ?>
								</p>
								<pre style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:6px;font-size:.8rem;overflow-x:auto;white-space:pre-wrap"><?php
								echo esc_html( wp_json_encode( array(
									'token'        => $token,
									'token_bypass' => false,
									'tv_url'       => $kiosk_url,
								), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
								?></pre>
							</td>
						</tr>

						<!-- Sincronizza token dal Pi -->
						<tr style="border-top:2px solid #e0e0e0">
							<th scope="row"><?php esc_html_e( 'Sincronizza token dal Pi', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<p class="description" style="margin-bottom:8px">
									<?php esc_html_e( 'Se vedi "pagina riservata", incolla qui il token che c\'è in cv-tv-config.json (campo "token") e premi Imposta. Risolve immediatamente il disallineamento senza toccare il Raspberry Pi.', 'casa-volterra-tv-launcher' ); ?>
								</p>
								<?php
								// Form separato FUORI dal form principale (evita annidamento HTML).
								// Viene renderizzato dopo la chiusura del form principale tramite output buffer.
								// Per ora usiamo un link diretto alla pagina di sync con GET param.
								$sync_nonce = wp_create_nonce( 'cv_tv_set_token' );
								?>
								<div class="cv-tv-sync-form" id="cv-tv-sync-wrap">
									<input type="text" id="cv-tv-sync-input"
										placeholder="<?php esc_attr_e( 'Es: c99c3337-265d-41ff-b3de-220dfb352cd8', 'casa-volterra-tv-launcher' ); ?>"
										class="regular-text">
									<button type="button" id="cv-tv-sync-btn" class="button button-primary"
										data-nonce="<?php echo esc_attr( $sync_nonce ); ?>"
										data-url="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										&#x2705; <?php esc_html_e( 'Imposta token', 'casa-volterra-tv-launcher' ); ?>
									</button>
									<span id="cv-tv-sync-result" style="margin-left:8px;font-weight:600"></span>
									<script>
									document.getElementById('cv-tv-sync-btn').addEventListener('click', function() {
										var token = document.getElementById('cv-tv-sync-input').value.trim();
										var res   = document.getElementById('cv-tv-sync-result');
										if (!token) { res.style.color='#c00'; res.textContent='Token vuoto'; return; }
										res.style.color='#666'; res.textContent='Salvataggio...';
										var btn = this;
										btn.disabled = true;
										var fd = new FormData();
										fd.append('action', 'cv_tv_set_token');
										fd.append('cv_tv_manual_token', token);
										fd.append('_wpnonce', this.dataset.nonce);
										fetch(this.dataset.url, { method:'POST', body:fd, redirect:'manual' })
											.then(function(r) {
												res.style.color='#166534';
												res.textContent='✅ Token salvato!';
												document.getElementById('cv-tv-sync-input').value='';
												setTimeout(function(){ res.textContent=''; }, 3000);
											})
											.catch(function(e) {
												res.style.color='#c00'; res.textContent='Errore: '+e;
											})
											.finally(function() { btn.disabled=false; });
									});
									</script>
								</div>
							</td>
						</tr>

						<!-- Test connessione proxy -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Test connessione proxy Pi', 'casa-volterra-tv-launcher' ); ?></th>
							<td>
								<?php if ( $proxy_url ) : ?>
									<button type="button" id="cv-tv-proxy-test" class="button button-secondary">
										&#x1F50D; <?php esc_html_e( 'Verifica connessione', 'casa-volterra-tv-launcher' ); ?>
									</button>
									<span id="cv-tv-proxy-result" style="margin-left:12px;font-weight:600"></span>
									<p class="description" style="margin-top:6px">
										<?php printf(
											esc_html__( 'Testa %s/health.', 'casa-volterra-tv-launcher' ),
											'<code>' . esc_html( $proxy_url ) . '</code>'
										); ?>
									</p>
									<script>
									document.getElementById('cv-tv-proxy-test').addEventListener('click',function(){
										var btn=this,res=document.getElementById('cv-tv-proxy-result');
										btn.disabled=true;res.style.color='#666';res.textContent='Verifico...';
										fetch(<?php echo wp_json_encode( rtrim( $proxy_url, '/' ) . '/health' ); ?>)
											.then(function(r){return r.json();})
											.then(function(d){
												if(d.ok){
													res.style.color='#166534';
													var auth=d.auth||{};
													res.textContent='OK v'+(d.version||'?')+' | app:'+d.app+' | token:'+(auth.token_configured?'OK':'MANCANTE')+' | bypass:'+(auth.bypass?'ON':'off');
												}else{res.style.color='#c2410c';res.textContent='Risposta inattesa';}
											})
											.catch(function(e){res.style.color='#991b1b';res.textContent='Non raggiungibile: '+e.message;})
											.finally(function(){btn.disabled=false;});
									});
									</script>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'Configura prima l\'URL proxy nella sezione Integrazioni.', 'casa-volterra-tv-launcher' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>

					</table>
				</div>
			</div><!-- /sicurezza -->

			<!-- ══ GENERALE ════════════════════════════════════════ -->
			<div class="cv-tv-section">
				<div class="cv-tv-section-head">
					<span style="font-size:1.3rem">&#x1F3E0;</span>
					<h2><?php esc_html_e( 'Generale', 'casa-volterra-tv-launcher' ); ?></h2>
				</div>
				<div class="cv-tv-section-body">
					<table class="form-table">
						<tr>
							<th><label for="cv_tv_launcher_path"><?php esc_html_e( 'Percorso launcher', 'casa-volterra-tv-launcher' ); ?></label></th>
							<td>
								<input id="cv_tv_launcher_path" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[launcher_path]"
									type="text" class="regular-text" value="<?php echo esc_attr( $s['launcher_path'] ); ?>">
								<p class="description"><?php esc_html_e( 'es. "tv" → yoursite.com/tv/', 'casa-volterra-tv-launcher' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="cv_tv_footer_text"><?php esc_html_e( 'Testo footer', 'casa-volterra-tv-launcher' ); ?></label></th>
							<td>
								<input id="cv_tv_footer_text" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[footer_text]"
									type="text" class="regular-text" value="<?php echo esc_attr( $s['footer_text'] ); ?>">
							</td>
						</tr>
						<tr>
							<th><label for="cv_tv_opening_note"><?php esc_html_e( 'Nota telecomando', 'casa-volterra-tv-launcher' ); ?></label></th>
							<td>
								<input id="cv_tv_opening_note" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[opening_note]"
									type="text" class="regular-text" value="<?php echo esc_attr( $s['opening_note'] ); ?>">
							</td>
						</tr>
					</table>
				</div>
			</div><!-- /generale -->

			<!-- ══ DATE SOGGIORNO ══════════════════════════════════ -->
			<div class="cv-tv-section">
				<div class="cv-tv-section-head">
					<span style="font-size:1.3rem">&#x1F4C5;</span>
					<h2>Soggiorno in corso</h2>
				</div>
				<div class="cv-tv-section-body">
					<?php
					$_smoobu_key    = trim( $s['smoobu_api_key'] ?? '' );
					$_sync_log      = CV_TV_Smoobu::get_sync_log();
					$_smoobu_active = ! empty( $_smoobu_key );
					$_eff_name  = CV_TV_Launcher::effective_guest_name( $s );
					$_raw_next  = $s['next_guest_name'] ?? '';
					$_is_next   = ( $_eff_name !== '' && $_raw_next !== '' && $_eff_name === $_raw_next );
					if ( $_is_next ) {
						$_cur_name  = $_eff_name;
						$_cur_bid   = $s['next_booking_id']    ?? 0;
						$_cur_url   = $s['next_guest_app_url'] ?? '';
						$_next_name = ''; $_next_bid = 0; $_next_ci = ''; $_next_url = '';
					} else {
						$_cur_name  = $_eff_name;
						$_cur_bid   = $_eff_name ? ( $s['current_booking_id']    ?? 0 ) : 0;
						$_cur_url   = $_eff_name ? ( $s['current_guest_app_url'] ?? '' ) : '';
						$_next_name = $_raw_next;
						$_next_bid  = $s['next_booking_id']  ?? 0;
						$_next_ci   = $s['next_checkin']     ?? '';
						$_next_url  = $s['next_guest_app_url'] ?? '';
					}
					?>
					<?php if ( $_smoobu_active && $_sync_log ) : ?>
						<div style="background:<?php echo 'ok' === $_sync_log['status'] ? '#f0fdf4' : '#fef9f0'; ?>;border-left:4px solid <?php echo 'ok' === $_sync_log['status'] ? '#16a34a' : '#f97316'; ?>;padding:10px 14px;margin:0 0 12px;border-radius:0 4px 4px 0;font-size:13px;">
							<?php if ( 'ok' === $_sync_log['status'] ) : ?>
								&#x2705; <strong>Smoobu sincronizzato</strong> il <?php echo esc_html( wp_date( 'd/m/Y H:i', $_sync_log['time'] ) ); ?> &mdash; <?php echo (int) $_sync_log['count']; ?> prenotazioni trovate.
							<?php else : ?>
								&#x26A0;&#xFE0F; <strong>Errore ultima sync:</strong> <?php echo esc_html( $_sync_log['message'] ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ( $_cur_name ) : ?>
					<div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:10px 14px;margin:0 0 10px;border-radius:0 4px 4px 0;font-size:13px;">
						&#x1F464; <strong>Ospite in casa:</strong>
						<strong style="font-size:15px;"><?php echo esc_html( $_cur_name ); ?></strong>
						<?php if ( $_cur_bid ) : ?><span style="color:#888;font-size:11px;">(Booking #<?php echo esc_html( $_cur_bid ); ?>)</span><?php endif; ?>
						<?php if ( $_cur_url ) : ?>&nbsp;&middot;&nbsp;<a href="<?php echo esc_url( $_cur_url ); ?>" target="_blank" rel="noopener">&#x1F4D6; Guida ospite</a><?php endif; ?>
					</div>
					<?php elseif ( $_smoobu_active ) : ?>
					<div style="background:#f8fafc;border-left:4px solid #94a3b8;padding:10px 14px;margin:0 0 10px;border-radius:0 4px 4px 0;font-size:13px;color:#64748b;">
						&#x1F6CC; Nessun ospite in casa in questo momento.
					</div>
					<?php endif; ?>
					<?php if ( $_next_name && $_next_ci ) : ?>
					<div style="background:#fefce8;border-left:4px solid #eab308;padding:8px 14px;margin:0 0 12px;border-radius:0 4px 4px 0;font-size:12px;color:#713f12;">
						&#x1F551; <strong>Prossimo ospite</strong> dal <?php echo esc_html( wp_date( 'd/m/Y', strtotime( $_next_ci ) ) ); ?>:
						<em><?php echo esc_html( $_next_name ); ?></em>
						<?php if ( $_next_bid ) : ?><span style="color:#92400e;">(#<?php echo esc_html( $_next_bid ); ?>)</span><?php endif; ?>
						<?php if ( $_next_url ) : ?>&nbsp;<a href="<?php echo esc_url( $_next_url ); ?>" target="_blank" rel="noopener" style="color:#92400e;">&#x1F4D6;</a><?php endif; ?>
					</div>
					<?php endif; ?>
					<!-- Hidden fields — preserve all guest data across saves -->
					<input type="hidden" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[current_guest_name]"    value="<?php echo esc_attr( $s['current_guest_name']    ?? '' ); ?>">
					<input type="hidden" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[current_booking_id]"    value="<?php echo esc_attr( $s['current_booking_id']    ?? 0 ); ?>">
					<input type="hidden" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[current_guest_app_url]" value="<?php echo esc_attr( $s['current_guest_app_url'] ?? '' ); ?>">
					<input type="hidden" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[next_guest_name]"       value="<?php echo esc_attr( $s['next_guest_name']       ?? '' ); ?>">
					<input type="hidden" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[next_booking_id]"       value="<?php echo esc_attr( $s['next_booking_id']       ?? 0 ); ?>">
					<input type="hidden" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[next_guest_app_url]"    value="<?php echo esc_attr( $s['next_guest_app_url']    ?? '' ); ?>">
					<table class="form-table">
						<tr>
							<th>Check-in ospite attuale</th>
							<td>
								<div style="display:flex;gap:8px;align-items:center;">
									<input name="<?php echo esc_attr( CV_TV_OPTION ); ?>[current_checkin]" type="date"
										value="<?php echo esc_attr( $s['current_checkin'] ?? '' ); ?>"
										<?php echo $_smoobu_active ? 'style="background:#f0fdf4;"' : ''; ?>>
									<label style="font-size:13px;color:#555;">alle <input type="time" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[current_checkin_time]"
										value="<?php echo esc_attr( $s['current_checkin_time'] ?? '' ); ?>"
										<?php echo $_smoobu_active ? 'style="background:#f0fdf4;"' : ''; ?>></label>
								</div>
								<p class="description">&#x1F501; Ospite attuale &mdash; usato dagli slug <code>{nome_ospite}</code>, <code>{data_checkin}</code> ecc.</p>
							</td>
						</tr>
						<tr>
							<th>Check-out ospite attuale</th>
							<td>
								<div style="display:flex;gap:8px;align-items:center;">
									<input name="<?php echo esc_attr( CV_TV_OPTION ); ?>[current_checkout]" type="date"
									value="<?php echo esc_attr( $s['current_checkout'] ?? '' ); ?>"
										<?php echo $_smoobu_active ? 'style="background:#f0fdf4;"' : ''; ?>>
									<label style="font-size:13px;color:#555;">alle <input type="time" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[current_checkout_time]"
										value="<?php echo esc_attr( $s['current_checkout_time'] ?? '' ); ?>"
										<?php echo $_smoobu_active ? 'style="background:#f0fdf4;"' : ''; ?>></label>
								</div>
								<p class="description">&#x1F501; Ospite attuale &mdash; usato dallo scheduler per la fascia "giorno check-out".</p>
							</td>
						</tr>
						<tr>
							<th>Prossimo arrivo (scheduler)</th>
							<td>
								<div style="display:flex;gap:8px;align-items:center;">
									<input name="<?php echo esc_attr( CV_TV_OPTION ); ?>[next_checkin]" type="date"
										value="<?php echo esc_attr( $s['next_checkin'] ?? '' ); ?>"
										<?php echo $_smoobu_active ? 'style="background:#fefce8;"' : ''; ?>>
									<label style="font-size:13px;color:#555;">alle <input type="time" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[next_checkin_time]"
										value="<?php echo esc_attr( $s['next_checkin_time'] ?? '' ); ?>"
										<?php echo $_smoobu_active ? 'style="background:#fefce8;"' : ''; ?>></label>
								</div>
								<p class="description">Attiva la schermata di arrivo per il prossimo ospite.</p>
							</td>
						</tr>
						<tr>
							<th>Prossima partenza (scheduler)</th>
							<td>
								<div style="display:flex;gap:8px;align-items:center;">
									<input name="<?php echo esc_attr( CV_TV_OPTION ); ?>[next_checkout]" type="date"
										value="<?php echo esc_attr( $s['next_checkout'] ?? '' ); ?>"
										<?php echo $_smoobu_active ? 'style="background:#fefce8;"' : ''; ?>>
									<label style="font-size:13px;color:#555;">alle <input type="time" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[next_checkout_time]"
										value="<?php echo esc_attr( $s['next_checkout_time'] ?? '' ); ?>"
										<?php echo $_smoobu_active ? 'style="background:#fefce8;"' : ''; ?>></label>
								</div>
								<p class="description">Attiva la schermata di partenza per il prossimo ospite.</p>
							</td>
						</tr>
					</table>
				</div>
			</div><!-- /date -->

						<!-- ══ AUTOMAZIONE ARRIVI/PARTENZE ════════════════════ -->
			<div class="cv-tv-section">
				<div class="cv-tv-section-head">
					<span style="font-size:1.3rem">&#x23F0;</span>
					<h2>Automazione Arrivi &amp; Partenze</h2>
				</div>
				<div class="cv-tv-section-body">
					<p style="color:#555;margin-top:0">
						Quando c&rsquo;&egrave; un arrivo o una partenza, il plugin mostra automaticamente
						la schermata dedicata nella finestra temporale configurata,
						<strong>con priorit&agrave; massima sugli slot dello Scheduler</strong>.
						Fuori dalla finestra, lo scheduler torna alla sua programmazione normale.
					</p>

					<?php
					// Info box stato corrente
					$_ci_date = $s['next_checkin'] ?? '';
					$_ci_time = $s['next_checkin_time'] ?? '';
					$_co_date = $s['next_checkout'] ?? '';
					$_co_time = $s['next_checkout_time'] ?? '';
					if ( $_ci_date || $_co_date ) :
					?>
					<div style="background:#f8fafc;border:1px solid #c3c4c7;border-radius:6px;padding:12px 16px;margin-bottom:20px;font-size:13px;display:flex;gap:32px;flex-wrap:wrap;">
						<?php if ( $_ci_date ) : ?>
						<div>
							&#x1F6EC; <strong>Prossimo arrivo:</strong>
							<?php echo esc_html( wp_date( 'd/m/Y', strtotime( $_ci_date ) ) ); ?>
							<?php echo $_ci_time ? ' alle <strong>' . esc_html( $_ci_time ) . '</strong>' : '<span style="color:#999">(ora non disponibile &mdash; verr&agrave; usato l&#39;orario di default)</span>'; ?>
						</div>
						<?php endif; ?>
						<?php if ( $_co_date ) : ?>
						<div>
							&#x1F6EB; <strong>Prossima partenza:</strong>
							<?php echo esc_html( wp_date( 'd/m/Y', strtotime( $_co_date ) ) ); ?>
							<?php echo $_co_time ? ' alle <strong>' . esc_html( $_co_time ) . '</strong>' : '<span style="color:#999">(ora non disponibile &mdash; verr&agrave; usato l&#39;orario di default)</span>'; ?>
						</div>
						<?php endif; ?>
					</div>
					<?php endif; ?>

					<!-- ── Arrivo ───────────────────────────────────────── -->
					<h3 style="margin:0 0 12px;font-size:.95rem;color:#1d2327;border-bottom:1px solid #eee;padding-bottom:8px;">&#x1F6EC; Schermata di Arrivo</h3>
					<table class="form-table">
						<tr>
							<th>Screen da mostrare</th>
							<td>
								<select name="<?php echo esc_attr( CV_TV_OPTION ); ?>[event_checkin_screen_id]" style="min-width:220px">
									<option value="0"><?php esc_html_e( '— Disabilitato —', 'casa-volterra-tv-launcher' ); ?></option>
									<?php foreach ( $screens as $_sc ) : ?>
										<option value="<?php echo esc_attr( $_sc->ID ); ?>" <?php selected( (int)$s['event_checkin_screen_id'], $_sc->ID ); ?>><?php echo esc_html( $_sc->post_title ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description">Seleziona una Screen. Imposta a <em>Disabilitato</em> per non usare questa funzione.</p>
							</td>
						</tr>
						<tr>
							<th>Finestra temporale</th>
							<td>
								<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
									<label>Inizia
										<input type="number" min="0" max="12"
											name="<?php echo esc_attr( CV_TV_OPTION ); ?>[event_checkin_advance_hours]"
											value="<?php echo esc_attr( $s['event_checkin_advance_hours'] ); ?>"
											style="width:60px"> ore <strong>prima</strong> del check-in
									</label>
									<span style="color:#999">|</span>
									<label>Durata
										<input type="number" min="1" max="24"
											name="<?php echo esc_attr( CV_TV_OPTION ); ?>[event_checkin_duration_hours]"
											value="<?php echo esc_attr( $s['event_checkin_duration_hours'] ); ?>"
											style="width:60px"> ore
									</label>
								</div>
								<p class="description cv-tv-event-preview" data-type="checkin"
									data-smoobu-time="<?php echo esc_attr( $s['next_checkin_time'] ); ?>"
									data-default-time="<?php echo esc_attr( $s['event_checkin_default_time'] ); ?>">
									&hellip;
								</p>
							</td>
						</tr>
						<tr>
							<th>Orario di default</th>
							<td>
								<input type="time"
									name="<?php echo esc_attr( CV_TV_OPTION ); ?>[event_checkin_default_time]"
									value="<?php echo esc_attr( $s['event_checkin_default_time'] ); ?>">
								<p class="description">Usato solo se Smoobu non fornisce l&rsquo;ora esatta del check-in.</p>
							</td>
						</tr>
					</table>

					<!-- ── Partenza ─────────────────────────────────────── -->
					<h3 style="margin:24px 0 12px;font-size:.95rem;color:#1d2327;border-bottom:1px solid #eee;padding-bottom:8px;">&#x1F6EB; Schermata di Partenza</h3>
					<table class="form-table">
						<tr>
							<th>Screen da mostrare</th>
							<td>
								<select name="<?php echo esc_attr( CV_TV_OPTION ); ?>[event_checkout_screen_id]" style="min-width:220px">
									<option value="0"><?php esc_html_e( '— Disabilitato —', 'casa-volterra-tv-launcher' ); ?></option>
									<?php foreach ( $screens as $_sc ) : ?>
										<option value="<?php echo esc_attr( $_sc->ID ); ?>" <?php selected( (int)$s['event_checkout_screen_id'], $_sc->ID ); ?>><?php echo esc_html( $_sc->post_title ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th>Finestra temporale</th>
							<td>
								<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
									<label>Inizia
										<input type="number" min="0" max="12"
											name="<?php echo esc_attr( CV_TV_OPTION ); ?>[event_checkout_advance_hours]"
											value="<?php echo esc_attr( $s['event_checkout_advance_hours'] ); ?>"
											style="width:60px"> ore <strong>prima</strong> del check-out
									</label>
									<span style="color:#999">|</span>
									<label>Estendi
										<input type="number" min="0" max="12"
											name="<?php echo esc_attr( CV_TV_OPTION ); ?>[event_checkout_extra_hours]"
											value="<?php echo esc_attr( $s['event_checkout_extra_hours'] ); ?>"
											style="width:60px"> ore <strong>dopo</strong> il check-out
									</label>
								</div>
								<p class="description cv-tv-event-preview" data-type="checkout"
									data-smoobu-time="<?php echo esc_attr( $s['next_checkout_time'] ); ?>"
									data-default-time="<?php echo esc_attr( $s['event_checkout_default_time'] ); ?>">
									&hellip;
								</p>
							</td>
						</tr>
						<tr>
							<th>Orario di default</th>
							<td>
								<input type="time"
									name="<?php echo esc_attr( CV_TV_OPTION ); ?>[event_checkout_default_time]"
									value="<?php echo esc_attr( $s['event_checkout_default_time'] ); ?>">
								<p class="description">Usato solo se Smoobu non fornisce l&rsquo;ora esatta del check-out.</p>
							</td>
						</tr>
					</table>
				</div>
			</div><!-- /automazione -->

			<!-- ══ INTEGRAZIONI ════════════════════════════════════ -->
			<div class="cv-tv-section">
				<div class="cv-tv-section-head">
					<span style="font-size:1.3rem">&#x1F517;</span>
					<h2><?php esc_html_e( 'Integrazioni', 'casa-volterra-tv-launcher' ); ?></h2>
				</div>
				<div class="cv-tv-section-body">
					<table class="form-table">

						<!-- ── Smoobu ── -->
						<tr>
							<th><label for="cv_tv_smoobu_key">&#x1F3E0; Smoobu — API Key</label></th>
							<td>
								<input id="cv_tv_smoobu_key"
									name="<?php echo esc_attr( CV_TV_OPTION ); ?>[smoobu_api_key]"
									type="password" class="regular-text"
									value="<?php echo esc_attr( $s['smoobu_api_key'] ); ?>"
									autocomplete="new-password"
									placeholder="La tua API key Smoobu">
								<p class="description">Trovi la API key in Smoobu &rarr; Impostazioni &rarr; Avanzate &rarr; Chiavi API.</p>
							</td>
						</tr>
						<tr>
							<th><label for="cv_tv_smoobu_secret">Smoobu — API Secret</label></th>
							<td>
								<?php $_has_secret = '' !== trim( $s['smoobu_api_secret'] ?? '' ); ?>
								<input id="cv_tv_smoobu_secret"
									name="<?php echo esc_attr( CV_TV_OPTION ); ?>[smoobu_api_secret]"
									type="password" class="regular-text"
									value=""
									autocomplete="new-password"
									placeholder="<?php echo $_has_secret ? 'Salvato — lascia vuoto per non cambiarlo' : 'Incolla qui il secret'; ?>">
								<?php if ( $_has_secret ) : ?>
									<span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;margin-left:6px;">&#x2705; Salvato</span>
								<?php else : ?>
									<span style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;margin-left:6px;">&#x26A0;&#xFE0F; Mancante</span>
								<?php endif; ?>
								<p class="description">
									Obbligatorio dal 31/10/2026: Smoobu accetta solo richieste firmate (HMAC).<br>
									In Smoobu &rarr; Impostazioni &rarr; Avanzate &rarr; Chiavi API genera il secret della chiave e incollalo qui: Smoobu lo mostra una volta sola.<br>
									Per sicurezza il secret salvato non viene mai mostrato in questa pagina. Dopo averlo salvato premi &ldquo;Sincronizza ora&rdquo; per verificare.
								</p>
							</td>
						</tr>
						<tr>
							<th><label for="cv_tv_smoobu_apt">Smoobu — Appartamento</label></th>
							<td>
								<div style="display:flex;gap:8px;align-items:center;">
									<select id="cv_tv_smoobu_apt"
										name="<?php echo esc_attr( CV_TV_OPTION ); ?>[smoobu_apartment_id]"
										style="min-width:240px">
										<?php if ( $s['smoobu_apartment_id'] ) : ?>
											<option value="<?php echo esc_attr( $s['smoobu_apartment_id'] ); ?>" selected>
												<?php echo esc_html( $s['smoobu_apartment_name'] ?: $s['smoobu_apartment_id'] ); ?>
											</option>
										<?php else : ?>
											<option value="">— Clicca &ldquo;Carica&rdquo; per popolare —</option>
										<?php endif; ?>
									</select>
									<input type="hidden" id="cv_tv_smoobu_apt_name"
										name="<?php echo esc_attr( CV_TV_OPTION ); ?>[smoobu_apartment_name]"
										value="<?php echo esc_attr( $s['smoobu_apartment_name'] ); ?>">
									<button type="button" id="cv-smoobu-load-apt" class="button"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'cv_tv_smoobu_nonce' ) ); ?>">
										Carica appartamenti
									</button>
								</div>
								<p class="description">Seleziona l'appartamento da monitorare. Lascia vuoto per considerare tutte le prenotazioni dell'account.</p>
							</td>
						</tr>
						<tr>
							<th><label for="cv_tv_smoobu_guest_token">Smoobu — Token Guida Ospite</label></th>
							<td>
								<input id="cv_tv_smoobu_guest_token" type="text" class="regular-text"
									name="<?php echo esc_attr( CV_TV_OPTION ); ?>[smoobu_guest_token]"
									value="<?php echo esc_attr( $s['smoobu_guest_token'] ); ?>"
									placeholder="es. v2846a4269f">
								<p class="description">
									Il parametro <code>t=</code> della URL guida ospite Smoobu (es. <code>https://guest.smoobu.com/?t=<strong>v2846a4269f</strong>&amp;b=123</code>).
									È fisso per la tua proprietà — copialo da una guida esistente.<br>
									Se Smoobu restituisce il link direttamente nell&rsquo;API, questo campo viene ignorato.
								</p>
							</td>
						</tr>
						<tr>
							<th>Smoobu — Sincronizzazione</th>
							<td>
								<button type="button" id="cv-smoobu-sync-now" class="button button-primary"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'cv_tv_smoobu_nonce' ) ); ?>">
									&#x1F504; Sincronizza ora
								</button>
								<span id="cv-smoobu-sync-status" style="margin-left:12px;font-size:13px;"></span>
								<p class="description">La sincronizzazione automatica avviene ogni ora tramite WP-Cron. Prossima esecuzione: <?php echo esc_html( wp_date( 'd/m/Y H:i', wp_next_scheduled( CV_TV_Smoobu::CRON_HOOK ) ?: time() ) ); ?>.</p>
							</td>
						</tr>

						<!-- ── Raspberry Pi & Home Assistant ── -->
						<tr><td colspan="2"><hr style="margin:8px 0"></td></tr>
						<tr>
							<th><label for="cv_tv_local_proxy"><?php esc_html_e( 'Proxy Raspberry Pi (URL)', 'casa-volterra-tv-launcher' ); ?></label></th>
							<td>
								<input id="cv_tv_local_proxy" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[local_proxy_url]"
									type="url" class="regular-text" value="<?php echo esc_attr( $s['local_proxy_url'] ); ?>"
									placeholder="http://localhost:8765">
								<p class="description"><?php esc_html_e( 'URL del proxy HLS locale sul Raspberry Pi.', 'casa-volterra-tv-launcher' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="cv_tv_ha_url"><?php esc_html_e( 'URL Home Assistant', 'casa-volterra-tv-launcher' ); ?></label></th>
							<td>
								<input id="cv_tv_ha_url" name="<?php echo esc_attr( CV_TV_OPTION ); ?>[home_assistant_url]"
									type="url" class="regular-text" value="<?php echo esc_attr( $s['home_assistant_url'] ); ?>"
									placeholder="https://homeassistant.local:8123/dashboard-tv">
							</td>
						</tr>
					</table>
				</div>
			</div><!-- /integrazioni -->

			<!-- ══ APPLICAZIONI TV ═════════════════════════════════ -->
			<div class="cv-tv-section">
				<div class="cv-tv-section-head">
					<span style="font-size:1.3rem">&#x1F4FA;</span>
					<h2><?php esc_html_e( 'Applicazioni TV', 'casa-volterra-tv-launcher' ); ?></h2>
				</div>
				<div class="cv-tv-section-body">
					<table class="form-table">
						<?php
						$apps = array(
							'kodi_enabled'    => array( '&#x1F3AC;', 'Kodi',    'Abilita Kodi come player multimediale sul Raspberry Pi.' ),
							'netflix_enabled' => array( '&#x1F39E;&#xFE0F;', 'Netflix', 'Abilita il pulsante Netflix. Chiude Kodi per evitare audio sovrapposto.' ),
							'browser_enabled' => array( '&#x1F310;', 'Browser', 'Abilita la navigazione internet con tastiera virtuale.' ),
						);
						foreach ( $apps as $key => $info ) :
							$enabled = '1' === (string) ( $s[ $key ] ?? '1' );
							?>
							<tr>
								<th><label><?php echo $info[0] . ' ' . esc_html( $info[1] ); ?></label></th>
								<td>
									<div class="cv-tv-toggle-row">
										<label class="cv-tv-toggle">
											<input type="checkbox"
												name="<?php echo esc_attr( CV_TV_OPTION ); ?>[<?php echo esc_attr( $key ); ?>]"
												value="1" <?php checked( $enabled ); ?>>
											<span class="cv-tv-toggle-slider"></span>
										</label>
										<span><?php echo esc_html( $info[2] ); ?></span>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>
			</div><!-- /app tv -->


			<?php submit_button( __( 'Salva impostazioni', 'casa-volterra-tv-launcher' ) ); ?>
		</form>
		</div>
		<?php
	}

	/* ── Rewrite ────────────────────────────────────────────── */

	public static function register_rewrite() {
		$s    = self::get_settings();
		$path = trim( (string) $s['launcher_path'], '/' );
		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '1' );
		if ( $path ) {
			add_rewrite_rule(
				'^' . preg_quote( $path, '/' ) . '/?$',
				'index.php?' . self::QUERY_VAR . '=1',
				'top'
			);
		}
	}

	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		$vars[] = 'cv_tv_guide_preview';
		return $vars;
	}

	public static function on_settings_updated( $old, $new ) {
		$old = is_array( $old ) ? $old : self::default_settings();
		$new = is_array( $new ) ? $new : self::default_settings();
		if ( ( $old['launcher_path'] ?? '' ) !== ( $new['launcher_path'] ?? '' ) ) {
			self::register_rewrite();
			flush_rewrite_rules();
		}
	}

	/* ── Frontend ───────────────────────────────────────────── */

	public static function maybe_render() {
		if ( '1' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		CV_TV_Auth::maybe_set_cookie_and_redirect();
		if ( ! CV_TV_Auth::is_authorized() ) {
			CV_TV_Auth::render_denied();
		}
		status_header( 200 );
		nocache_headers();
		CV_TV_Renderer::render();
		exit;
	}

	public static function maybe_render_guide_preview() {
		$guide_id = absint( get_query_var( 'cv_tv_guide_preview', 0 ) );
		if ( ! $guide_id ) {
			return;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'Accesso negato.', 'casa-volterra-tv-launcher' ) );
		}
		$guide = CV_TV_Guides::get_guide_data( $guide_id );
		if ( ! $guide ) {
			wp_die( esc_html__( 'Guida non trovata.', 'casa-volterra-tv-launcher' ) );
		}
		status_header( 200 );
		nocache_headers();
		CV_TV_Renderer::render_guide( $guide );
		exit;
	}
}

register_activation_hook( __FILE__, array( 'CV_TV_Launcher', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CV_TV_Launcher', 'deactivate' ) );
CV_TV_Launcher::init();
