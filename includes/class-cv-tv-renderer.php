<?php
/**
 * Renderer — genera la pagina HTML completa per il player TV.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Renderer {

	public static function render() {
		// 1. Resolve content via scheduler
		$resolved = CV_TV_Schedule::resolve_now();
		if ( ! $resolved ) {
			$resolved = CV_TV_Schedule::get_fallback();
		}

		// 2. Build screens array
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

		// 3. Final fallback: try first published screen
		if ( empty( $screens ) ) {
			$first = get_posts( array(
				'post_type'      => CV_TV_Screens::PT,
				'post_status'    => 'publish',
				'numberposts'    => 1,
				'suppress_filters' => false,
			) );
			if ( $first ) {
				$screen = CV_TV_Screens::get_screen_data( $first[0]->ID, 30, 'fade' );
				if ( $screen ) {
					$screens = array( $screen );
				}
			}
		}

		$settings   = CV_TV_Launcher::get_settings();
		$refresh_at = CV_TV_Schedule::seconds_to_next_boundary();
		$rest_root  = get_rest_url( null, 'cv-tv/v1' );
		$nonce      = wp_create_nonce( 'wp_rest' );

		// Guest info: usa l'OSPITE ATTUALE (in casa oggi)
		$guest_name    = CV_TV_Launcher::effective_guest_name( $settings );
		$guest_app_url = $settings['current_guest_app_url'] ?? '';

		$tv_data = wp_json_encode(
			array(
				'local_proxy'       => trim( (string) $settings['local_proxy_url'] ),
				'screens'           => $screens,
				'loop'              => $loop,
				'progress'          => $progress,
				'refresh_at'        => $refresh_at,
				'launcher_url'      => home_url( '/' . trim( (string) $settings['launcher_path'], '/' ) . '/' ),
				'rest_root'         => $rest_root,
				'nonce'             => $nonce,
				'footer_text'       => $settings['footer_text'],
				'opening_note'      => $settings['opening_note'],
				// Auth
				'auth_token'        => CV_TV_Auth::get_token(),
				'auth_bypass'       => CV_TV_Auth::bypass_active(),
				// Feature flags
				'kodi_enabled'      => '1' === (string) ( $settings['kodi_enabled']    ?? '1' ),
				'netflix_enabled'   => '1' === (string) ( $settings['netflix_enabled'] ?? '1' ),
				'browser_enabled'   => '1' === (string) ( $settings['browser_enabled'] ?? '1' ),
				// Ospite corrente
				'guest_name'        => $guest_name,
				'guest_app_url'     => $guest_app_url,
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);

		$base_title = empty( $screens ) ? 'Casa Volterra TV' : esc_html( $screens[0]['title'] );
		// Aggiunge il nome dell'ospite al titolo della pagina quando disponibile
		$title = $guest_name
			? $base_title . ' — ' . esc_html( $guest_name )
			: $base_title;
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="cv-tv-launcher" content="cv-tv-launcher">
<title><?php echo esc_html( $title ); ?></title>
<style>
/* ── Reset ───────────────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;background:#0a0f1e;color:#f8fafc;
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
#wpadminbar{display:none!important}
html{margin-top:0!important}

/* ── CSS vars ────────────────────────────────────────────────── */
:root{
  --surface:rgba(15,23,42,.60);
  --surface-strong:rgba(15,23,42,.82);
  --border:rgba(255,255,255,.14);
  --text:#f8fafc;
  --muted:rgba(248,250,252,.72);
  --radius:20px;
  --shadow:0 18px 60px rgba(0,0,0,.32);
  --accent:#60a5fa;
}

/* ── Stage ───────────────────────────────────────────────────── */
#cv-tv-stage{position:fixed;inset:0;width:100vw;height:100vh}

/* ── Slides A/B ──────────────────────────────────────────────── */
.cv-slide{
  position:absolute;inset:0;width:100%;height:100%;
  display:grid;grid-template-rows:auto 1fr auto;
  padding:4vh 4.5vw;gap:2vh;
  opacity:0;transition:opacity .6s ease;pointer-events:none;
}
.cv-slide.cv-active{opacity:1;pointer-events:auto;z-index:2}
.cv-slide.cv-exit{opacity:0;z-index:1}

/* Slide background */
.cv-slide__bg{
  position:absolute;inset:0;background-size:cover;background-position:center;
  background-color:#0a0f1e;
}
.cv-slide__bg video{
  position:absolute;inset:0;width:100%;height:100%;object-fit:cover;
  /* GPU compositing — evita il decoding software su Raspberry Pi */
  will-change:transform;
  transform:translateZ(0);
  backface-visibility:hidden;
}
.cv-slide__overlay{
  position:absolute;inset:0;
  background:linear-gradient(180deg,rgba(0,0,0,var(--ov-top,0)) 0%,rgba(0,0,0,var(--ov-bot,0)) 100%);
  backdrop-filter:blur(1px);
}

/* ── HUD top-right (clock + weather) ────────────────────────── */
#cv-hud{
  position:fixed;top:3vh;right:4vw;z-index:10;
  text-align:right;pointer-events:none;
}
#cv-clock{
  font-size:clamp(2rem,3.5vw,3.8rem);font-weight:200;letter-spacing:-0.02em;
  line-height:1;text-shadow:0 2px 12px rgba(0,0,0,.5);
}
#cv-weather{
  font-size:clamp(.9rem,1.3vw,1.2rem);color:var(--muted);margin-top:4px;
}

/* ── Slide content ───────────────────────────────────────────── */
.cv-header{position:relative;z-index:2;max-width:72vw}

/* ── Guest guide button ──────────────────────────────────────── */
#cv-guide-app-btn:focus-visible{
  outline:2px solid #fff;outline-offset:3px;
}
.cv-logo{max-height:64px;max-width:220px;display:block;margin-bottom:1.4vh;
  filter:drop-shadow(0 4px 16px rgba(0,0,0,.4));object-fit:contain}
.cv-eyebrow{font-size:clamp(.85rem,1vw,1rem);letter-spacing:.1em;text-transform:uppercase;
  color:var(--muted);margin-bottom:.6vh}
.cv-title{font-size:clamp(2rem,4vw,4.4rem);line-height:1.0;font-weight:700;
  text-shadow:0 2px 24px rgba(0,0,0,.4);margin-bottom:.8vh;text-wrap:balance}
.cv-subtitle{font-size:clamp(.95rem,1.5vw,1.4rem);line-height:1.45;color:var(--muted);
  max-width:60vw}
.cv-badges{display:flex;flex-wrap:wrap;gap:10px;margin-top:1.4vh}
.cv-badge{
  display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);
  font-size:.92rem;backdrop-filter:blur(8px)
}

/* ── Buttons grid ────────────────────────────────────────────── */
.cv-main{position:relative;z-index:2;display:flex;align-items:flex-end;min-height:0}
.cv-grid{
  width:min(1200px,100%);
  display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;align-content:end;
}
.cv-grid--small .cv-btn{min-height:100px;padding:14px 16px}
.cv-grid--large .cv-btn{min-height:200px;padding:36px 32px}
.cv-grid--align-center{margin:0 auto}
.cv-grid--align-right{margin-left:auto;margin-right:0}
.cv-btn{
  display:flex;flex-direction:column;align-items:flex-start;justify-content:center;
  padding:28px 28px;min-height:160px;border-radius:var(--radius);
  border:1.5px solid var(--border);
  background:linear-gradient(180deg,rgba(255,255,255,.15) 0%,rgba(255,255,255,.07) 100%);
  backdrop-filter:blur(18px);box-shadow:var(--shadow);
  color:var(--text);cursor:pointer;text-align:left;
  transition:transform .15s ease,border-color .15s ease,background .15s ease;
  outline:none;
}
.cv-btn:hover,.cv-btn.focused{
  transform:translateY(-2px) scale(1.012);
  border-color:rgba(255,255,255,.85);
  background:linear-gradient(180deg,rgba(255,255,255,.25) 0%,rgba(255,255,255,.14) 100%);
  box-shadow:0 0 0 3px rgba(255,255,255,.2),var(--shadow);
}
.cv-btn__icon{font-size:2.2em;margin-bottom:10px;line-height:1}
.cv-btn__label{font-size:clamp(1.2rem,2vw,2rem);font-weight:700;line-height:1.2}
.cv-btn__desc{margin-top:8px;font-size:clamp(.95rem,1.3vw,1.2rem);color:var(--muted);line-height:1.35}

/* ── QR code ─────────────────────────────────────────────────── */
.cv-qr{
  position:absolute;bottom:10vh;left:4.5vw;z-index:2; /* a sinistra: webcam PiP occupa il lato destro */
  text-align:center;
}
.cv-qr img{width:110px;height:110px;border-radius:10px;display:block}
.cv-qr__label{font-size:.78rem;color:var(--muted);margin-top:6px;max-width:120px;line-height:1.3}

/* ── Ticker ──────────────────────────────────────────────────── */
#cv-ticker-wrap{
  position:fixed;bottom:0;left:0;right:0;z-index:10;
  background:rgba(0,0,0,.55);backdrop-filter:blur(8px);
  border-top:1px solid rgba(255,255,255,.08);
  height:44px;overflow:hidden;display:none;
}
#cv-ticker-wrap.active{display:block}
#cv-ticker{
  display:inline-block;white-space:nowrap;padding:0 100vw;
  font-size:.95rem;color:var(--muted);line-height:44px;
  animation:cv-ticker-scroll 40s linear infinite;
}
@keyframes cv-ticker-scroll{
  from{transform:translateX(0)}
  to{transform:translateX(-50%)}
}

/* ── Footer ──────────────────────────────────────────────────── */
.cv-footer{
  position:relative;z-index:2;
  display:flex;justify-content:space-between;align-items:center;
  font-size:.9rem;color:rgba(248,250,252,.65);
  padding-bottom:calc(44px + 1.5vh);/* space for ticker */
}

/* ── Progress bar ────────────────────────────────────────────── */
#cv-progress{
  position:fixed;bottom:44px;left:0;right:0;height:3px;z-index:9;
  background:rgba(255,255,255,.1);display:none;
}
#cv-progress.active{display:block}
#cv-progress-bar{height:100%;background:var(--accent);width:0%;transition:width .5s linear}

/* ── Slide dots ──────────────────────────────────────────────── */
#cv-dots{
  position:fixed;bottom:52px;left:50%;transform:translateX(-50%);
  z-index:9;display:flex;gap:8px;
}
#cv-dots .dot{
  width:6px;height:6px;border-radius:50%;
  background:rgba(255,255,255,.3);transition:background .3s ease;
}
#cv-dots .dot.active{background:#fff}

/* ── Slide transitions ───────────────────────────────────────── */
.cv-slide.slide-enter{animation:cv-slide-in .5s ease forwards}
@keyframes cv-slide-in{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}

/* ── Responsive ──────────────────────────────────────────────── */
@media(max-width:1100px){.cv-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){
  .cv-grid{grid-template-columns:1fr}
  .cv-header,.cv-subtitle{max-width:100%}
  .cv-footer{flex-direction:column;align-items:flex-start;gap:4px}
}

/* ── Webcam PiP — riquadro bottom-right ──────────────────────── */
.cv-webcam-zone{
  /* Riquadro fisso in basso a destra, sopra tutto il contenuto */
  position:absolute;
  right:4vw;
  bottom:calc(50px + 3vh); /* spazio sopra ticker/progress */
  width:clamp(280px, 28vw, 480px);
  aspect-ratio:16/9;
  z-index:6;
  border-radius:16px;
  overflow:hidden;
  box-shadow:0 8px 40px rgba(0,0,0,.55), 0 0 0 2px rgba(255,255,255,.18);
  background:#000;
}
.cv-webcam-item{
  position:absolute;inset:0;
  opacity:0;transition:opacity .6s ease;pointer-events:none;
  overflow:hidden; /* taglia qualsiasi scrollbar dell'iframe */
}
.cv-webcam-item.wc-active{opacity:1;pointer-events:auto}
.cv-webcam-item iframe{
  /* Leggermente più grande del contenitore: taglia la scrollbar
     nativa dell'iframe senza bisogno di JS cross-origin.
     L'overflow:hidden sul parent fa da clip. */
  position:absolute;
  top:-2px; left:-2px;
  width:calc(100% + 4px);
  height:calc(100% + 4px);
  border:none;display:block;
  scrolling:no; /* attributo legacy, rinforza il messaggio */
}
.cv-webcam-item img{
  width:100%;height:100%;object-fit:cover;display:block;
}
/* Label con sfondo semitrasparente in basso nel riquadro */
.cv-webcam-label{
  position:absolute;bottom:0;left:0;right:0;
  padding:5px 10px;
  background:rgba(0,0,0,.50);backdrop-filter:blur(4px);
  font-size:.78rem;color:rgba(255,255,255,.90);
  letter-spacing:.04em;pointer-events:none;z-index:3;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
/* Puntini nell'angolo in alto a destra del riquadro */
.cv-webcam-dots{
  position:absolute;top:8px;right:10px;
  z-index:4;display:flex;gap:5px;
}
.cv-webcam-dots .wdot{
  width:5px;height:5px;border-radius:50%;
  background:rgba(255,255,255,.4);transition:background .3s;
}
.cv-webcam-dots .wdot.active{background:#fff;}
</style>
</head>
<body>
<div id="cv-tv-launcher" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden">cv-tv-launcher</div>

<div id="cv-tv-stage">
  <div id="cv-slide-a" class="cv-slide cv-active"></div>
  <div id="cv-slide-b" class="cv-slide"></div>
</div>

<!-- HUD always visible -->
<div id="cv-hud">
  <div id="cv-clock">--:--</div>
  <div id="cv-weather"></div>
</div>

<!-- Ticker strip -->
<div id="cv-ticker-wrap"><span id="cv-ticker"></span></div>

<!-- Progress bar -->
<div id="cv-progress"><div id="cv-progress-bar"></div></div>

<!-- Slide dots -->
<div id="cv-dots"></div>

<script>
window.cvTvData = <?php echo $tv_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
</script>
<script src="<?php echo esc_url( CV_TV_URL . 'assets/tv-player.js' ); ?>?v=<?php echo esc_attr( CV_TV_VERSION ); ?>"></script>
</body>
</html>
		<?php
	}

	/* ── Guide page renderer ────────────────────────────────── */

	public static function render_guide( array $guide ) {
		$settings   = CV_TV_Launcher::get_settings();
		$launcher   = home_url( '/' . trim( (string) $settings['launcher_path'], '/' ) . '/' );
		$bg         = esc_attr( $guide['bg_color']   ?: '#ffffff' );
		$fg         = esc_attr( $guide['text_color'] ?: '#1a1a2e' );
		$acc        = esc_attr( $guide['accent']     ?: '#1a56a0' );
		$bg_image   = esc_url(  $guide['bg_image'] ?? '' );
		$layout     = esc_attr( $guide['layout']     ?: 'centered' );
		$show_back  = ! empty( $guide['show_back'] );
		$back_label = esc_html( $guide['back_label'] ?: '← Torna alla home' );
		$title      = esc_html( $guide['title'] );
		$blocks     = $guide['blocks'] ?? array();

		// Build CSS as PHP string to avoid inline PHP in CSS confusing parsers
		$overlay_bg = $bg_image ? 'rgba(255,255,255,0.88)' : 'transparent';
		$bg_img_css = $bg_image ? "background-image:url({$bg_image});background-size:cover;background-position:center;" : '';

		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="cv-tv-launcher" content="cv-tv-launcher">
<title><?php echo $title; ?> — Casa Volterra TV</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;overflow:hidden;font-family:Inter,ui-sans-serif,system-ui,sans-serif}
#wpadminbar{display:none!important}html{margin-top:0!important}
#cv-guide{
  position:fixed;inset:0;
  background-color:<?php echo $bg; ?>;
  color:<?php echo $fg; ?>;
  display:flex;flex-direction:column;
  overflow:hidden;
  <?php echo $bg_img_css; ?>
}
#cv-guide::before{content:'';position:absolute;inset:0;background:<?php echo $overlay_bg; ?>;pointer-events:none;z-index:0;}
#cv-guide-inner{position:relative;z-index:1;flex:1;overflow-y:auto;overflow-x:hidden;padding:4vh 5vw 4vh;}
#cv-guide-inner::-webkit-scrollbar{width:8px}
#cv-guide-inner::-webkit-scrollbar-thumb{background:<?php echo $acc; ?>;border-radius:4px}
#cv-guide-header{
  display:flex;align-items:center;gap:16px;padding:2.5vh 5vw 2vh;
  position:relative;z-index:2;border-bottom:2px solid <?php echo $acc; ?>33;
  background:<?php echo $bg; ?>ee;backdrop-filter:blur(8px);flex-shrink:0;
}
#cv-guide-header h1{font-size:clamp(1.6rem,3vw,3rem);font-weight:700;color:<?php echo $acc; ?>;flex:1;line-height:1.1;font-family:inherit}
.cv-guide-back{
  display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:50px;
  border:none;cursor:pointer;background:<?php echo $acc; ?>;color:#fff;
  font-size:clamp(.95rem,1.3vw,1.2rem);font-weight:600;white-space:nowrap;font-family:inherit;
}
.cv-guide-back:hover{opacity:.85}
/* Layouts */
.cv-guide-layout-centered .cv-guide-blocks{max-width:900px;margin:0 auto}
.cv-guide-layout-two-column .cv-guide-blocks{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.cv-guide-layout-magazine .cv-guide-blocks{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
/* Blocks */
.cv-guide-block{margin-bottom:clamp(14px,2.5vh,32px)}
.cv-guide-layout-two-column .cv-guide-block,
.cv-guide-layout-magazine .cv-guide-block{margin-bottom:0}
.cv-gb-heading{padding-bottom:12px;border-bottom:3px solid <?php echo $acc; ?>33}
.cv-gb-heading h2{font-size:clamp(1.3rem,2.2vw,2rem);font-weight:700;color:<?php echo $acc; ?>;font-family:inherit}
.cv-gb-heading p{font-size:clamp(.9rem,1.2vw,1rem);color:<?php echo $fg; ?>99;margin-top:4px;font-family:inherit}
.cv-gb-text{font-size:clamp(1rem,1.4vw,1.25rem);line-height:1.65;font-family:inherit}
.cv-gb-text strong{font-weight:700;color:<?php echo $acc; ?>}
.cv-gb-icon-text{display:flex;gap:20px;align-items:flex-start;padding:20px;border-radius:14px;background:<?php echo $acc; ?>0d}
.cv-gb-icon-text .cv-gi-icon{font-size:clamp(2rem,3vw,3rem);flex-shrink:0;line-height:1}
.cv-gb-icon-text h3{font-size:clamp(1.1rem,1.6vw,1.5rem);font-weight:700;color:<?php echo $acc; ?>;margin-bottom:5px;font-family:inherit}
.cv-gb-icon-text p{font-size:clamp(.9rem,1.2vw,1.1rem);line-height:1.55;font-family:inherit}
.cv-gb-two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.cv-gb-two-col .cv-gc-col{padding:18px;border-radius:12px;background:<?php echo $acc; ?>08}
.cv-gb-two-col h3{font-size:clamp(1rem,1.5vw,1.3rem);font-weight:700;color:<?php echo $acc; ?>;margin-bottom:6px;font-family:inherit}
.cv-gb-two-col p{font-size:clamp(.88rem,1.1vw,1rem);line-height:1.6;font-family:inherit}
.cv-gb-image-text{display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:center}
.cv-gb-image-text.img-right{direction:rtl}.cv-gb-image-text.img-right > *{direction:ltr}
.cv-gb-image-text img{width:100%;border-radius:14px;box-shadow:0 8px 28px rgba(0,0,0,.12);display:block}
.cv-gb-image-text h3{font-size:clamp(1.1rem,1.7vw,1.6rem);font-weight:700;color:<?php echo $acc; ?>;margin-bottom:8px;font-family:inherit}
.cv-gb-image-text p{font-size:clamp(.9rem,1.2vw,1.1rem);line-height:1.6;font-family:inherit}
.cv-gb-info-box{display:flex;gap:14px;padding:18px 22px;border-radius:12px;align-items:flex-start}
.cv-gb-info-box.style-info{background:#e8f4fd;border-left:5px solid #2196F3}
.cv-gb-info-box.style-success{background:#e8f5e9;border-left:5px solid #4CAF50}
.cv-gb-info-box.style-warning{background:#fff3e0;border-left:5px solid #FF9800}
.cv-gb-info-box.style-tip{background:#fffde7;border-left:5px solid #FFC107}
.cv-gb-info-box .cv-gib-icon{font-size:clamp(1.5rem,2.2vw,2.2rem);flex-shrink:0;line-height:1}
.cv-gb-info-box .cv-gib-text{font-size:clamp(.9rem,1.2vw,1.1rem);line-height:1.6;font-family:inherit}
.cv-gb-list{background:<?php echo $acc; ?>08;border-radius:12px;padding:18px 22px}
.cv-gb-list h3{font-size:clamp(1rem,1.5vw,1.3rem);font-weight:700;color:<?php echo $acc; ?>;margin-bottom:10px;font-family:inherit}
.cv-gb-list ul{list-style:none;padding:0}
.cv-gb-list li{font-size:clamp(.9rem,1.2vw,1.1rem);padding:8px 0;border-bottom:1px solid <?php echo $acc; ?>18;line-height:1.4;font-family:inherit}
.cv-gb-list li:last-child{border-bottom:none}
.cv-gb-divider{height:2px;background:linear-gradient(90deg,<?php echo $acc; ?>44,transparent);margin:6px 0;border-radius:1px}
.cv-gb-qr{text-align:center;padding:20px}
.cv-gb-qr h3{font-size:clamp(1rem,1.4vw,1.3rem);font-weight:700;color:<?php echo $acc; ?>;margin-bottom:14px;font-family:inherit}
.cv-gb-qr img{width:clamp(120px,15vw,200px);border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.1)}
.cv-gb-qr p{font-size:clamp(.8rem,1vw,.95rem);opacity:.7;margin-top:6px;font-family:inherit}
.cv-gb-cta{text-align:center;padding:6px 0}
.cv-gb-cta a{display:inline-block;padding:clamp(12px,1.8vw,18px) clamp(24px,3vw,40px);border-radius:50px;font-size:clamp(1rem,1.5vw,1.3rem);font-weight:700;text-decoration:none;transition:transform .15s;font-family:inherit}
.cv-gb-cta a.primary{background:<?php echo $acc; ?>;color:#fff;box-shadow:0 4px 18px <?php echo $acc; ?>44}
.cv-gb-cta a.secondary{background:transparent;color:<?php echo $acc; ?>;border:2.5px solid <?php echo $acc; ?>}
.cv-gb-cta a.large{display:block;border-radius:14px;padding:16px;text-align:center}
.cv-gb-cta a:hover{transform:translateY(-2px)}
#cv-guide-scrollbar{position:fixed;bottom:0;left:0;right:0;height:4px;background:<?php echo $acc; ?>22;z-index:10}
#cv-guide-scrollbar-fill{height:100%;background:<?php echo $acc; ?>;width:0;transition:width .1s}
</style>
</head>
<body>
<div id="cv-tv-launcher" style="position:absolute;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden">cv-tv-launcher</div>
<div id="cv-guide">
  <div id="cv-guide-header">
    <?php if ( $show_back ) : ?>
    <button class="cv-guide-back" onclick="window.location.href='<?php echo esc_url( $launcher ); ?>'">
      <?php echo $back_label; ?>
    </button>
    <?php endif; ?>
    <h1><?php echo $title; ?></h1>
  </div>
  <div id="cv-guide-inner" class="cv-guide-layout-<?php echo $layout; ?>">
    <div class="cv-guide-blocks">
    <?php foreach ( $blocks as $block ) : ?>
      <?php self::render_guide_block( $block ); ?>
    <?php endforeach; ?>
    </div>
  </div>
</div>
<div id="cv-guide-scrollbar"><div id="cv-guide-scrollbar-fill"></div></div>
<script>
(function(){
  var inner=document.getElementById('cv-guide-inner');
  var bar=document.getElementById('cv-guide-scrollbar-fill');
  if(inner&&bar){
    inner.addEventListener('scroll',function(){
      var m=inner.scrollHeight-inner.clientHeight;
      bar.style.width=(m>0?inner.scrollTop/m*100:0)+'%';
    });
  }
  document.addEventListener('keydown',function(e){
    var s=inner?inner.clientHeight*0.8:400;
    if(e.key==='ArrowDown'||e.key==='PageDown'){if(inner)inner.scrollTop+=s;e.preventDefault();}
    if(e.key==='ArrowUp'||e.key==='PageUp'){if(inner)inner.scrollTop-=s;e.preventDefault();}
    if(e.key==='Home'&&inner){inner.scrollTop=0;}
    if(e.key==='End'&&inner){inner.scrollTop=inner.scrollHeight;}
    if(e.key==='Escape'){window.location.href='<?php echo esc_url( $launcher ); ?>';}
  });
})();
</script>
</body>
</html>
	<?php
	}

	private static function render_guide_block( array $block ) {
		$type = $block['type'] ?? 'text';
		echo '<div class="cv-guide-block cv-guide-block--' . esc_attr( $type ) . '">';
		switch ( $type ) {
			case 'heading':
				echo '<div class="cv-gb-heading">';
				if ( ! empty( $block['heading'] ) ) { echo '<h2>' . esc_html( $block['heading'] ) . '</h2>'; }
				if ( ! empty( $block['subtitle'] ) ) { echo '<p>' . esc_html( $block['subtitle'] ) . '</p>'; }
				echo '</div>';
				break;
			case 'text':
				$parsed = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', esc_html( $block['text'] ?? '' ) );
				$parsed = preg_replace( '/_(.+?)_/s', '<em>$1</em>', $parsed );
				echo '<div class="cv-gb-text">' . nl2br( $parsed ) . '</div>';
				break;
			case 'icon_text':
				echo '<div class="cv-gb-icon-text">';
				if ( ! empty( $block['icon'] ) ) { echo '<div class="cv-gi-icon">' . esc_html( $block['icon'] ) . '</div>'; }
				echo '<div>';
				if ( ! empty( $block['heading'] ) ) { echo '<h3>' . esc_html( $block['heading'] ) . '</h3>'; }
				if ( ! empty( $block['text'] ) ) { echo '<p>' . esc_html( $block['text'] ) . '</p>'; }
				echo '</div></div>';
				break;
			case 'two_col':
				echo '<div class="cv-gb-two-col">';
				foreach ( array( 'col1', 'col2' ) as $col ) {
					echo '<div class="cv-gc-col">';
					if ( ! empty( $block[ $col . '_heading' ] ) ) { echo '<h3>' . esc_html( $block[ $col . '_heading' ] ) . '</h3>'; }
					if ( ! empty( $block[ $col . '_text' ] ) ) { echo '<p>' . esc_html( $block[ $col . '_text' ] ) . '</p>'; }
					echo '</div>';
				}
				echo '</div>';
				break;
			case 'image_text':
				$cls = ( ( $block['img_side'] ?? 'left' ) === 'right' ) ? 'img-right' : '';
				echo '<div class="cv-gb-image-text ' . esc_attr( $cls ) . '">';
				if ( ! empty( $block['image'] ) ) { echo '<img src="' . esc_url( $block['image'] ) . '" alt="" loading="lazy">'; }
				echo '<div>';
				if ( ! empty( $block['heading'] ) ) { echo '<h3>' . esc_html( $block['heading'] ) . '</h3>'; }
				if ( ! empty( $block['text'] ) ) { echo '<p>' . esc_html( $block['text'] ) . '</p>'; }
				echo '</div></div>';
				break;
			case 'info_box':
				$style = esc_attr( $block['style'] ?? 'info' );
				echo '<div class="cv-gb-info-box style-' . $style . '">';
				if ( ! empty( $block['icon'] ) ) { echo '<div class="cv-gib-icon">' . esc_html( $block['icon'] ) . '</div>'; }
				$parsed = preg_replace( '/\*\*(.+?)\*\*/s', '<strong>$1</strong>', esc_html( $block['text'] ?? '' ) );
				echo '<div class="cv-gib-text">' . nl2br( $parsed ) . '</div>';
				echo '</div>';
				break;
			case 'list':
				echo '<div class="cv-gb-list">';
				if ( ! empty( $block['heading'] ) ) { echo '<h3>' . esc_html( $block['heading'] ) . '</h3>'; }
				$items = array_filter( array_map( 'trim', explode( "\n", $block['items'] ?? '' ) ) );
				if ( $items ) {
					echo '<ul>';
					foreach ( $items as $item ) { echo '<li>' . esc_html( $item ) . '</li>'; }
					echo '</ul>';
				}
				echo '</div>';
				break;
			case 'divider':
				echo '<div class="cv-gb-divider"></div>';
				break;
			case 'qr':
				if ( ! empty( $block['url'] ) ) {
					echo '<div class="cv-gb-qr">';
					if ( ! empty( $block['heading'] ) ) { echo '<h3>' . esc_html( $block['heading'] ) . '</h3>'; }
					echo '<img src="' . esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . rawurlencode( $block['url'] ) ) . '" alt="QR Code">';
					if ( ! empty( $block['label'] ) ) { echo '<p>' . esc_html( $block['label'] ) . '</p>'; }
					echo '</div>';
				}
				break;
			case 'cta_button':
				if ( ! empty( $block['label'] ) ) {
					$style  = esc_attr( $block['style'] ?? 'primary' );
					$url    = esc_url( $block['url'] ?? '#' );
					$label  = esc_html( $block['label'] );
					echo '<div class="cv-gb-cta"><a href="' . $url . '" class="' . $style . '">' . $label . '</a></div>';
				}
				break;
		}
		echo '</div>';
	}
}
