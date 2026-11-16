<?php
/**
 * Casa Volterra TV — Browser search page con tastiera virtuale
 * Raggiungibile su: /tv-search/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CV_TV_Browser {

	const SLUG = 'tv-search';

	public static function init() {
		add_action( 'init',              array( __CLASS__, 'register_rewrite' ) );
		add_filter( 'query_vars',        array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	public static function register_rewrite() {
		add_rewrite_rule( '^' . self::SLUG . '/?$', 'index.php?cv_tv_browser=1', 'top' );
	}

	public static function query_vars( $vars ) {
		$vars[] = 'cv_tv_browser';
		return $vars;
	}

	public static function maybe_render() {
		if ( '1' !== (string) get_query_var( 'cv_tv_browser', '0' ) ) {
			return;
		}
		// Auth gate
		CV_TV_Auth::maybe_set_cookie_and_redirect();
		if ( ! CV_TV_Auth::is_authorized() ) {
			CV_TV_Auth::render_denied();
		}
		status_header( 200 );
		nocache_headers();
		self::render();
		exit;
	}

	private static function render() {
		$settings = CV_TV_Launcher::get_settings();
		$tv_url   = home_url( '/' . trim( (string) $settings['launcher_path'], '/' ) . '/' );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cerca su Internet — Casa Volterra TV</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{width:100%;height:100%;background:#0a0e1f;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#fff;overflow:hidden}
#cv-search{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding-top:6vh;height:100vh}
#cv-search-logo{font-size:clamp(2rem,5vw,4rem);margin-bottom:3vh;text-align:center}
#cv-search-logo span{font-size:clamp(1.4rem,2.5vw,2rem);display:block;opacity:.6;margin-top:4px}
#cv-search-box{display:flex;gap:12px;width:min(700px,90vw);margin-bottom:3vh;background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.15);border-radius:50px;padding:14px 20px;align-items:center}
#cv-search-input{flex:1;background:transparent;border:none;outline:none;color:#fff;font-size:clamp(1rem,2vw,1.5rem);caret-color:#60a5fa}
#cv-search-input::placeholder{color:rgba(255,255,255,.35)}
#cv-search-btn{background:#1a56a0;border:none;border-radius:50px;color:#fff;padding:10px 24px;font-size:clamp(.9rem,1.5vw,1.2rem);cursor:pointer;font-weight:600;white-space:nowrap;transition:background .15s}
#cv-search-btn:hover{background:#1565c0}
#cv-quick-links{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;width:min(800px,95vw);margin-bottom:2vh}
.cv-qlink{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:20px;padding:8px 18px;font-size:clamp(.8rem,1.2vw,1rem);cursor:pointer;transition:background .15s;text-decoration:none;color:#fff}
.cv-qlink:hover{background:rgba(255,255,255,.14)}
#cv-back-btn{position:fixed;top:20px;left:20px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:50px;padding:10px 20px;color:#fff;cursor:pointer;font-size:clamp(.85rem,1.3vw,1.1rem);transition:background .15s}
#cv-back-btn:hover{background:rgba(255,255,255,.2)}
#cv-vkb{position:fixed;bottom:0;left:0;right:0;background:rgba(12,16,40,0.97);padding:clamp(8px,1.5vh,14px) clamp(6px,1vw,12px);border-top:1px solid rgba(255,255,255,.08);box-shadow:0 -4px 32px rgba(0,0,0,.6);display:none}
#cv-vkb.visible{display:block}
.cv-kb-row{display:flex;gap:clamp(3px,.4vw,6px);margin-bottom:clamp(3px,.4vh,6px);justify-content:center}
.cv-key{flex:1;min-width:clamp(26px,3.5vw,52px);height:clamp(38px,5.5vh,56px);border:none;border-radius:8px;font-size:clamp(12px,1.6vw,19px);cursor:pointer;color:#fff;background:rgba(255,255,255,.08);transition:background .08s,transform .08s}
.cv-key:active{transform:scale(.94)}
.cv-key.special{background:rgba(255,255,255,.14);font-weight:700}
.cv-key.active{background:rgba(26,86,160,.9)}
.cv-key.wide-2{flex:2}.cv-key.wide-3{flex:3}.cv-key.wide-4{flex:4}
</style>
</head>
<body>
<button id="cv-back-btn" onclick="goBack()">&#8592; Torna alla TV</button>
<div id="cv-search">
  <div id="cv-search-logo">&#127760; <span>Cerca su Internet</span></div>
  <div id="cv-search-box" onclick="focusInput()">
    <input type="text" id="cv-search-input" placeholder="Cerca su Google..."
      onkeydown="if(event.key==='Enter')doSearch()" onfocus="showKb()" />
    <button id="cv-search-btn" onclick="doSearch()">Cerca &#8594;</button>
  </div>
  <div id="cv-quick-links">
    <a class="cv-qlink" onclick="openUrl('https://maps.google.com')">&#128506; Google Maps</a>
    <a class="cv-qlink" onclick="openUrl('https://www.tripadvisor.it/Tourism-g194821-Volterra_Province_of_Pisa_Tuscany-Vacations.html')">&#127860; TripAdvisor Volterra</a>
    <a class="cv-qlink" onclick="openUrl('https://www.meteo.it/meteo/toscana/pisa/volterra')">&#127780; Meteo Volterra</a>
    <a class="cv-qlink" onclick="openUrl('https://www.turismo.toscana.it')">&#127963; Turismo Toscana</a>
    <a class="cv-qlink" onclick="openUrl('https://www.trenitalia.com')">&#128642; Trenitalia</a>
    <a class="cv-qlink" onclick="openUrl('https://www.youtube.com')">&#128250; YouTube</a>
  </div>
</div>
<div id="cv-vkb"></div>
<script>
var kbVisible=false,shift=false,caps=false;
var ROWS=[
  ['1','2','3','4','5','6','7','8','9','0','\u232b'],
  ['q','w','e','r','t','y','u','i','o','p'],
  ['a','s','d','f','g','h','j','k','l','\u23ce'],
  ['\u21e7','z','x','c','v','b','n','m',',','.',{k:'?',w:1},'\u21e7'],
  [{k:'SPACE',w:4},'@','_','-','\u2715'],
];
function buildKb(){
  var html='';
  ROWS.forEach(function(row){
    html+='<div class="cv-kb-row">';
    row.forEach(function(k){
      var isObj=typeof k==='object',key=isObj?k.k:k,w=isObj?(k.w||1):1;
      var lbl=key==='SPACE'?' ':((shift||caps)&&key.length===1&&key>='a'&&key<='z')?key.toUpperCase():key;
      var cls='cv-key';
      if(['\u232b','\u23ce','\u21e7','\u2715'].indexOf(key)>=0)cls+=' special';
      if(w>1)cls+=' wide-'+w;
      if(key==='\u21e7'&&(shift||caps))cls+=' active';
      html+='<button class="'+cls+'" data-key="'+key+'" onclick="kbClick(\''+key+'\')">'+lbl+'</button>';
    });
    html+='</div>';
  });
  document.getElementById('cv-vkb').innerHTML=html;
}
function showKb(){document.getElementById('cv-vkb').classList.add('visible');kbVisible=true;buildKb();}
function hideKb(){document.getElementById('cv-vkb').classList.remove('visible');kbVisible=false;}
function focusInput(){document.getElementById('cv-search-input').focus();}
function kbClick(key){
  var inp=document.getElementById('cv-search-input');
  inp.focus();
  if(key==='\u2715'){hideKb();return;}
  if(key==='\u232b'){
    var s=inp.selectionStart,e=inp.selectionEnd,v=inp.value;
    inp.value=s!==e?v.slice(0,s)+v.slice(e):(s>0?v.slice(0,s-1)+v.slice(s):v);
    inp.setSelectionRange(s!==e?s:Math.max(0,s-1),s!==e?s:Math.max(0,s-1));return;
  }
  if(key==='\u23ce'){doSearch();return;}
  if(key==='\u21e7'){
    if(shift&&!caps){caps=true;shift=false;}else if(caps){caps=false;shift=false;}else{shift=true;}
    buildKb();return;
  }
  if(key==='SPACE'){insertAt(inp,' ');return;}
  var ch=(shift||caps)&&key.length===1&&key>='a'&&key<='z'?key.toUpperCase():key;
  insertAt(inp,ch);
  if(shift&&!caps){shift=false;buildKb();}
}
function insertAt(el,ch){
  var s=el.selectionStart,e=el.selectionEnd;
  el.value=el.value.slice(0,s)+ch+el.value.slice(e);
  el.setSelectionRange(s+ch.length,s+ch.length);
}
function doSearch(){var q=document.getElementById('cv-search-input').value.trim();if(q)window.location.href='https://www.google.com/search?q='+encodeURIComponent(q);}
function openUrl(url){window.location.href=url;}
function goBack(){
  fetch('<?php echo esc_js( rtrim( (string) ( CV_TV_Launcher::get_settings()['local_proxy_url'] ?? 'http://localhost:8765' ), '/' ) ); ?>/launch/tv')
    .catch(function(){});
  window.close();
  setTimeout(function(){window.location.href='<?php echo esc_js( $tv_url ); ?>';},500);
}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){hideKb();}});
window.onload=function(){document.getElementById('cv-search-input').focus();showKb();};
</script>
</body>
</html>
		<?php
	}
}
