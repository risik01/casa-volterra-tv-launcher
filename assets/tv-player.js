/**
 * Casa Volterra TV Player — v2.3.0
 * Legge window.cvTvData e gestisce: slide A/B, clock, meteo, ticker, QR, tastiera.
 */
(function () {
  'use strict';

  const data     = window.cvTvData || {};
  const screens  = data.screens || [];
  const loop     = data.loop !== false;
  const progress = data.progress !== false;
  const REST     = data.rest_root || '';
  const NONCE    = data.nonce || '';

  // ── Auth token ────────────────────────────────────────────
  const AUTH_TOKEN   = data.auth_token  || '';
  const AUTH_BYPASS  = data.auth_bypass || false;

  // ── Feature flags ─────────────────────────────────────────
  const KODI_ENABLED    = data.kodi_enabled    !== false;
  const NETFLIX_ENABLED = data.netflix_enabled !== false;
  const BROWSER_ENABLED = data.browser_enabled !== false;




  /**
   * Aggiunge il token Auth a un oggetto Headers per le fetch verso il proxy locale.
   */
  function authHeaders() {
    if (AUTH_BYPASS || !AUTH_TOKEN) return {};
    return { 'X-CV-Token': AUTH_TOKEN };
  }

  /**
   * fetch() con header X-CV-Token aggiunto automaticamente.
   */
  function authFetch(url, opts) {
    opts = opts || {};
    opts.headers = Object.assign({}, opts.headers || {}, authHeaders());
    return fetch(url, opts);
  }


  let activeSlot    = 'a'; // 'a' or 'b'
  let currentIdx    = 0;
  let slideTimer    = null;
  let progressTimer = null;
  let progressStart = null;
  let progressDur   = 0;
  let clockTimer    = null;

  const stageA  = document.getElementById('cv-slide-a');
  const stageB  = document.getElementById('cv-slide-b');
  const dotsEl  = document.getElementById('cv-dots');
  const progBar = document.getElementById('cv-progress-bar');
  const progWrap= document.getElementById('cv-progress');
  const tickWrap= document.getElementById('cv-ticker-wrap');
  const tickEl  = document.getElementById('cv-ticker');
  const clockEl = document.getElementById('cv-clock');
  const weatherEl= document.getElementById('cv-weather');

  /* ── Webcam carousel ───────────────────────────────────────── */

  /**
   * Monta il carosello webcam dentro slideEl.
   * Le cam appaiono sopra lo sfondo statico (z-index 1) ma sotto i contenuti (z-index 2).
   * Per ogni cam: tipo "iframe" → <iframe>; tipo "snapshot" → <img> che si auto-aggiorna.
   * Il timer del carosello usa la durata per-cam configurata in admin.
   */
  function buildWebcamCarousel(screen, slideEl) {
    const cams = screen.webcams;
    if (!cams || !cams.length) return;

    const zone = document.createElement('div');
    zone.className = 'cv-webcam-zone';

    // Costruisci gli item
    const items = cams.map(function(cam, i) {
      const item = document.createElement('div');
      item.className = 'cv-webcam-item' + (i === 0 ? ' wc-active' : '');

      if (cam.type === 'windy_id') {
        // ── Windy snapshot con autorefresh ────────────────────
        // Usa l'immagine statica di Windy aggiornata ogni ~2 min.
        // Zero problemi di autoplay, funziona su qualsiasi browser.
        // Il suffisso numerico dell'URL è le ultime 2 cifre dell'ID.
        var winId = cam.url.replace(/\D/g, '');
        var winSuffix = winId.slice(-2);
        var winSnapUrl = 'https://images-webcams.windy.com/' + winSuffix +
          '/' + winId + '/current/preview/' + winId + '.jpg';

        var img = document.createElement('img');
        img.alt = cam.label || 'Webcam';
        img.src = _snapUrl(winSnapUrl);
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block';

        // Attributo per riconoscerla al refresh periodico
        img.dataset.windyId = winId;
        img.dataset.windyBase = winSnapUrl;

        // Aggiorna l'immagine ogni 60 secondi (Windy la pubblica ogni ~2 min)
        setInterval(function() {
          img.src = _snapUrl(winSnapUrl);
        }, 60000);

        item.appendChild(img);

      } else if (cam.type === 'snapshot') {
        const img = document.createElement('img');
        img.alt = cam.label || 'Webcam';
        img.src = _snapUrl(cam.url);
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block';
        item.appendChild(img);

      } else if (cam.type === 'hls') {
        // ── HLS stream via hls.js ─────────────────────────────
        // Per stream m3u8 diretti (es. SkylineWebcams).
        // hls.js viene caricato una sola volta e riutilizzato.
        var vid = document.createElement('video');
        vid.autoplay  = true;
        vid.muted     = true;
        vid.loop      = false;
        vid.playsInline = true;
        vid.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;background:#000';

        _loadHls(vid, cam.url);
        item.appendChild(vid);

      } else {
        // iframe — stream live con player proprio (es. rtsp.me)
        const iframe = document.createElement('iframe');
        iframe.src = _streamUrl(cam.url);
        iframe.allow = 'autoplay; fullscreen; picture-in-picture';
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('scrolling', 'no');

        item.style.position = 'relative';
        item.appendChild(iframe);
      }
      zone.appendChild(item);
      return item;
    });

    // Label (nome della cam attiva)
    const labelEl = document.createElement('div');
    labelEl.className = 'cv-webcam-label';
    labelEl.textContent = cams[0].label || '';
    zone.appendChild(labelEl);

    // Puntini (solo se > 1 cam)
    const dotEls = [];
    if (cams.length > 1) {
      const dotsDiv = document.createElement('div');
      dotsDiv.className = 'cv-webcam-dots';
      cams.forEach(function(_, i) {
        const d = document.createElement('div');
        d.className = 'wdot' + (i === 0 ? ' active' : '');
        dotsDiv.appendChild(d);
        dotEls.push(d);
      });
      zone.appendChild(dotsDiv);
    }

    slideEl.appendChild(zone);

    if (cams.length < 2) return; // una sola cam, nessun carosello da avviare

    let wcIdx = 0;
    let wcTimer = null;

    function showWcItem(idx) {
      items.forEach(function(el, i) { el.classList.toggle('wc-active', i === idx); });
      dotEls.forEach(function(d, i)  { d.classList.toggle('active',    i === idx); });
      labelEl.textContent = cams[idx].label || '';

      // Per gli snapshot: ricarica l'immagine al momento di mostrarla
      if (cams[idx].type === 'snapshot') {
        const img = items[idx].querySelector('img');
        if (img) img.src = _snapUrl(cams[idx].url);
      }
    }

    function advanceWc() {
      wcIdx = (wcIdx + 1) % cams.length;
      showWcItem(wcIdx);
      scheduleWc(wcIdx);
    }

    function scheduleWc(idx) {
      clearTimeout(wcTimer);
      wcTimer = setTimeout(advanceWc, (cams[idx].duration || 15) * 1000);
    }

    scheduleWc(0);
  }

  /* ── HLS loader ───────────────────────────────────────────── */

  var _hlsReady  = false;
  var _hlsQueue  = [];

  function _ensureHlsJs(cb) {
    if (_hlsReady) { cb(); return; }
    _hlsQueue.push(cb);
    if (_hlsQueue.length > 1) return;
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/hls.js@latest/dist/hls.min.js';
    s.onload = function() {
      _hlsReady = true;
      _hlsQueue.forEach(function(f) { f(); });
      _hlsQueue = [];
    };
    document.head.appendChild(s);
  }

  function _attachHls(videoEl, url) {
    if (window.Hls && window.Hls.isSupported()) {
      var hls = new window.Hls({ lowLatencyMode: true, maxBufferLength: 10 });
      hls.loadSource(url);
      hls.attachMedia(videoEl);
      hls.on(window.Hls.Events.MANIFEST_PARSED, function() {
        videoEl.play().catch(function() {});
      });
      hls.on(window.Hls.Events.ERROR, function(e, d) {
        if (d.fatal) { setTimeout(function() { hls.loadSource(url); }, 5000); }
      });
    } else if (videoEl.canPlayType('application/vnd.apple.mpegurl')) {
      videoEl.src = url;
      videoEl.play().catch(function() {});
    }
  }

  /**
   * Carica uno stream HLS attraverso il proxy locale o WordPress.
   * Se il proxy locale espone /resolve?id=CAMID (SkylineWebcams),
   * ottiene automaticamente il token fresco.
   */
  function _loadHls(videoEl, streamUrl) {
    var localProxy = (data.local_proxy || '').replace(/\/+$/, '');
    var proxyBase  = localProxy ? localProxy + '/stream?url=' : REST + '/stream?url=';

    // Prova /resolve per SkylineWebcams (URL contiene /live/CAMID/)
    var camMatch = streamUrl.match(/\/live\/(\d+)\//);
    if (localProxy && camMatch) {
      var camId = camMatch[1];
      authFetch(localProxy + '/resolve?id=' + camId)
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d.url) {
            _ensureHlsJs(function() { _attachHls(videoEl, d.url); });
          } else {
            _ensureHlsJs(function() { _attachHls(videoEl, proxyBase + encodeURIComponent(streamUrl)); });
          }
        })
        .catch(function() {
          _ensureHlsJs(function() { _attachHls(videoEl, proxyBase + encodeURIComponent(streamUrl)); });
        });
      return;
    }

    // Fallback diretto con proxy
    var proxied = proxyBase + encodeURIComponent(streamUrl);
    _ensureHlsJs(function() { _attachHls(videoEl, proxied); });
  }

  /**
   * Normalizza l'URL di uno stream live aggiungendo ?autoplay=1
   * per i player che lo supportano (Windy, rtsp.me…).
   * Non tocca URL che già contengono il parametro.
   */
  function _streamUrl(url) {
    if (!url) return '';
    if (url.includes('autoplay')) return url; // già presente
    const sep = url.includes('?') ? '&' : '?';
    return url + sep + 'autoplay=1';
  }

  /* ── Slide builder ─────────────────────────────────────────── */

  function buildSlide(screen) {
    const el = document.createElement('div');
    el.className = 'cv-slide';

    const ovAlpha = Math.min(0.9, Math.max(0, (screen.overlay || 55) / 100));
    const ovTop   = (ovAlpha + 0.05).toFixed(2);
    const ovBot   = (ovAlpha + 0.18).toFixed(2);

    // Background
    const bg = document.createElement('div');
    bg.className = 'cv-slide__bg';
    if (screen.bg_image) bg.style.backgroundImage = `url(${screen.bg_image})`;

    if (screen.bg_video) {
      const vid = document.createElement('video');
      vid.autoplay    = true;
      vid.muted       = true;
      vid.loop        = true;
      vid.playsInline = true;
      vid.preload     = 'auto';
      // GPU compositing layer — critico su Raspberry Pi per evitare decoding software
      vid.style.cssText = 'will-change:transform;transform:translateZ(0);backface-visibility:hidden';
      const src = document.createElement('source');
      src.src  = screen.bg_video;
      src.type = 'video/mp4';
      vid.appendChild(src);
      bg.appendChild(vid);
    }

    const ov = document.createElement('div');
    ov.className = 'cv-slide__overlay';
    ov.style.setProperty('--ov-top', ovTop);
    ov.style.setProperty('--ov-bot', ovBot);
    bg.appendChild(ov);
    el.appendChild(bg);

    // Webcam carousel (si inserisce sopra lo sfondo, sotto i contenuti)
    buildWebcamCarousel(screen, el);

    // Header
    const header = document.createElement('header');
    header.className = 'cv-header';

    if (screen.logo) {
      const img = document.createElement('img');
      img.src = screen.logo;
      img.className = 'cv-logo';
      img.alt = 'Logo';
      header.appendChild(img);
    }
    if (screen.eyebrow) {
      const ey = document.createElement('div');
      ey.className = 'cv-eyebrow';
      ey.textContent = screen.eyebrow;
      header.appendChild(ey);
    }
    if (screen.heading || screen.title) {
      const h = document.createElement('h1');
      h.className = 'cv-title';
      h.textContent = screen.heading || screen.title;
      header.appendChild(h);
    }
    if (screen.subtitle) {
      const s = document.createElement('p');
      s.className = 'cv-subtitle';
      s.textContent = screen.subtitle;
      header.appendChild(s);
    }
    if (screen.badges && screen.badges.length) {
      const bw = document.createElement('div');
      bw.className = 'cv-badges';
      screen.badges.forEach(b => {
        const sp = document.createElement('span');
        sp.className = 'cv-badge';
        sp.textContent = b;
        bw.appendChild(sp);
      });
      header.appendChild(bw);
    }
    el.appendChild(header);

    // Main — buttons
    const main = document.createElement('main');
    var bValignMain = screen.btn_valign || 'bottom';
    var valignMap = { 'top': 'flex-start', 'center': 'center', 'bottom': 'flex-end' };
    main.className = 'cv-main';
    main.style.alignItems = valignMap[bValignMain] || 'flex-end';
    if (screen.buttons && screen.buttons.length) {
      const grid = document.createElement('div');
      var bSize   = screen.btn_size   || 'medium';
      var bAlign  = screen.btn_align  || 'left';
      var bValign = screen.btn_valign || 'bottom';
      var bCols   = screen.btn_cols   || 3;
      grid.className = 'cv-grid cv-grid--' + bSize + ' cv-grid--align-' + bAlign;
      grid.style.gridTemplateColumns = 'repeat(' + bCols + ',minmax(0,1fr))';
      grid.dataset.slideId = screen.id;

      screen.buttons.forEach((btn, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'cv-btn';
        b.tabIndex = 0;
        b.dataset.index = i;
        b.dataset.target = btn.target || '';
        b.dataset.type   = btn.type   || 'url';

        if (btn.icon) {
          const ico = document.createElement('div');
          ico.className = 'cv-btn__icon';
          ico.textContent = btn.icon;
          b.appendChild(ico);
        }
        const lbl = document.createElement('div');
        lbl.className = 'cv-btn__label';
        lbl.textContent = btn.label;
        b.appendChild(lbl);

        if (btn.desc) {
          const d = document.createElement('div');
          d.className = 'cv-btn__desc';
          d.textContent = btn.desc;
          b.appendChild(d);
        }

        b.addEventListener('click', () => activateBtn(b));
        grid.appendChild(b);
      });
      main.appendChild(grid);
    }
    el.appendChild(main);

    // QR
    if (screen.qr_url) {
      const qrDiv = document.createElement('div');
      qrDiv.className = 'cv-qr';
      const qrImg = document.createElement('img');
      qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(screen.qr_url)}`;
      qrImg.alt = 'QR Code';
      qrDiv.appendChild(qrImg);
      if (screen.qr_label) {
        const ql = document.createElement('div');
        ql.className = 'cv-qr__label';
        ql.textContent = screen.qr_label;
        qrDiv.appendChild(ql);
      }
      el.appendChild(qrDiv);
    }

    // Footer
    const footer = document.createElement('footer');
    footer.className = 'cv-footer';
    const fl = document.createElement('div');
    fl.textContent = screen.footer_note || data.footer_text || '';
    const fr = document.createElement('div');
    fr.textContent = data.opening_note || '';
    footer.appendChild(fl);
    footer.appendChild(fr);
    el.appendChild(footer);

    return el;
  }

  /* ── Transition ────────────────────────────────────────────── */

  function showSlide(idx, transition) {
    if (!screens.length) return;
    idx = ((idx % screens.length) + screens.length) % screens.length;
    currentIdx = idx;
    const screen  = screens[idx];
    const newEl   = buildSlide(screen);
    const inSlot  = activeSlot === 'a' ? stageB : stageA;
    const outSlot = activeSlot === 'a' ? stageA : stageB;

    // Place new slide in inactive slot
    inSlot.innerHTML = '';
    inSlot.appendChild(newEl);
    inSlot.classList.remove('cv-active', 'cv-exit');

    const trans = transition || screen.transition || 'fade';

    if (trans === 'cut') {
      outSlot.classList.remove('cv-active');
      outSlot.classList.add('cv-exit');
      newEl.classList.add('cv-active');
      inSlot.classList.add('cv-active');
    } else if (trans === 'slide') {
      newEl.classList.add('slide-enter', 'cv-active');
      inSlot.classList.add('cv-active');
      outSlot.classList.remove('cv-active');
      outSlot.classList.add('cv-exit');
      setTimeout(() => { newEl.classList.remove('slide-enter'); }, 550);
    } else {
      // fade (default)
      newEl.style.opacity = '0';
      newEl.classList.add('cv-active');
      inSlot.classList.add('cv-active');
      outSlot.classList.remove('cv-active');
      outSlot.classList.add('cv-exit');
      requestAnimationFrame(() => {
        requestAnimationFrame(() => { newEl.style.opacity = ''; });
      });
    }

    activeSlot = activeSlot === 'a' ? 'b' : 'a';
    updateDots(idx);
    setupTicker(screen);
    setupHUD(screen);
    focusFirstBtn();
    scheduleNext(screen.duration || 10);
  }

  /* ── Auto-advance ──────────────────────────────────────────── */

  function scheduleNext(duration) {
    clearTimeout(slideTimer);
    clearInterval(progressTimer);
    progressDur = duration * 1000;
    progressStart = Date.now();

    if (progress && screens.length > 1) {
      progWrap.classList.add('active');
      progBar.style.width = '0%';
      progressTimer = setInterval(() => {
        const elapsed = Date.now() - progressStart;
        const pct = Math.min(100, (elapsed / progressDur) * 100);
        progBar.style.width = pct + '%';
      }, 300);
    }

    if (screens.length > 1) {
      slideTimer = setTimeout(() => {
        const nextIdx = (currentIdx + 1) % screens.length;
        if (nextIdx === 0 && !loop) return;
        showSlide(nextIdx);
      }, duration * 1000);
    }
  }

  /* ── Dots ───────────────────────────────────────────────────── */

  function buildDots() {
    dotsEl.innerHTML = '';
    if (screens.length <= 1) return;
    screens.forEach((_, i) => {
      const d = document.createElement('div');
      d.className = 'dot';
      dotsEl.appendChild(d);
    });
  }
  function updateDots(idx) {
    const dots = dotsEl.querySelectorAll('.dot');
    dots.forEach((d, i) => d.classList.toggle('active', i === idx));
  }

  /* ── Ticker ─────────────────────────────────────────────────── */

  const weatherCache = {};

  function setupTicker(screen) {
    if (!screen.ticker_mode) {
      tickWrap.classList.remove('active');
      return;
    }
    tickWrap.classList.add('active');

    if (screen.ticker_mode === 'static' && screen.ticker_text) {
      renderTicker(screen.ticker_text.split('·').map(s => s.trim()).filter(Boolean));
    } else if (screen.ticker_mode === 'rss' && screen.ticker_rss) {
      fetchRSS(screen.ticker_rss);
    }
  }

  function renderTicker(items) {
    if (!items.length) return;
    const text = items.join('  ·  ');
    tickEl.textContent = text + '  ·  ' + text; // duplicate for seamless loop
    // Reset animation
    tickEl.style.animation = 'none';
    requestAnimationFrame(() => {
      tickEl.style.animation = '';
    });
  }

  function fetchRSS(url) {
    if (!REST) return;
    fetch(`${REST}/rss?url=${encodeURIComponent(url)}`, {
      headers: { 'X-WP-Nonce': NONCE },
    })
      .then(r => r.json())
      .then(d => {
        if (d.items && d.items.length) renderTicker(d.items);
      })
      .catch(() => {});
  }

  /* ── HUD: clock + weather ───────────────────────────────────── */

  function setupHUD(screen) {
    const showClock = !!(screen && screen.show_clock);
    const showWeather = !!(screen && screen.show_weather && screen.weather_city);

    clockEl.style.display = showClock ? '' : 'none';
    if (showClock) {
      startClock();
    } else if (clockTimer) {
      clearInterval(clockTimer);
      clockTimer = null;
      clockEl.textContent = '';
    }

    if (showWeather) {
      loadWeather(screen.weather_city);
      weatherEl.style.display = '';
    } else {
      weatherEl.style.display = 'none';
      weatherEl.textContent = '';
    }

    const hud = document.getElementById('cv-hud');
    if (hud) {
      hud.style.display = (showClock || showWeather) ? '' : 'none';
    }
  }

  function startClock() {
    function tick() {
      const now = new Date();
      clockEl.textContent = now.toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' });
    }
    tick();
    if (clockTimer) clearInterval(clockTimer);
    clockTimer = setInterval(tick, 1000);
  }

  const WMO_EMOJI = {
    0:'☀️',1:'🌤',2:'⛅',3:'☁️',
    45:'🌫',48:'🌫',
    51:'🌦',53:'🌦',55:'🌧',
    61:'🌧',63:'🌧',65:'🌧',
    71:'🌨',73:'🌨',75:'❄️',
    80:'🌦',81:'🌧',82:'⛈',
    95:'⛈',96:'⛈',99:'⛈',
  };

  async function loadWeather(city) {
    if (weatherCache[city]) {
      weatherEl.textContent = weatherCache[city];
      return;
    }
    try {
      const geo = await fetch(
        `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(city)}&count=1&language=it&format=json`
      ).then(r => r.json());
      const loc = geo.results?.[0];
      if (!loc) return;
      const wx = await fetch(
        `https://api.open-meteo.com/v1/forecast?latitude=${loc.latitude}&longitude=${loc.longitude}&current_weather=true`
      ).then(r => r.json());
      const cw   = wx.current_weather;
      const emoji = WMO_EMOJI[cw.weathercode] || '🌡';
      const text  = `${emoji} ${Math.round(cw.temperature)}°C`;
      weatherCache[city] = text;
      weatherEl.textContent = text;
    } catch (_) {}
  }

  /* ── Keyboard navigation ────────────────────────────────────── */

  function getFocusedBtn() {
    const active = activeSlot === 'a' ? stageB : stageA;
    return active.querySelector('.cv-btn.focused') || active.querySelector('.cv-btn');
  }

  function getAllBtns() {
    const active = activeSlot === 'a' ? stageB : stageA;
    return Array.from(active.querySelectorAll('.cv-btn'));
  }

  function focusFirstBtn() {
    const btns = getAllBtns();
    if (!btns.length) return;
    setFocus(btns[0]);
  }

  function setFocus(btn) {
    getAllBtns().forEach(b => b.classList.remove('focused'));
    if (btn) {
      btn.classList.add('focused');
      btn.focus({ preventScroll: true });
    }
  }

  function getColumns() {
    const btns = getAllBtns();
    if (btns.length <= 1) return 1;
    const firstTop = Math.round(btns[0].getBoundingClientRect().top);
    let cols = 0;
    btns.forEach(b => {
      if (Math.abs(Math.round(b.getBoundingClientRect().top) - firstTop) < 8) cols++;
    });
    return Math.max(1, cols);
  }

  function activateBtn(btn) {
    const target = btn.dataset.target;
    const type   = btn.dataset.type || 'url';
    if (!target) return;

    if (type === 'guide') {
      // Apre la guida in overlay senza navigare
      openGuide(target);

    } else if (type === 'ai_chat') {
      // Mostra QR code overlay per la chat AI sul telefono
      openAiQR(target);

    } else if (type === 'pdf_viewer') {
      // Apre un PDF in overlay full-screen scorrevole con airmouse
      openPdfViewer(target);

    } else if (type === 'browser') {
      // Lancia Chromium kiosk sul sito esterno tramite il proxy locale.
      // Il target può essere:
      //   a) URL diretta del sito  → es. https://www.google.com
      //   b) URL proxy già formata → http://127.0.0.1:8765/launch/browser?url=...
      // In entrambi i casi costruiamo sempre la chiamata proxy corretta.
      var browserUrl = target;
      if (browserUrl.indexOf('127.0.0.1:8765/launch/browser') === -1) {
        // target è l'URL del sito: costruisci la chiamata proxy
        browserUrl = 'http://127.0.0.1:8765/launch/browser?url=' + encodeURIComponent(target);
      }
      console.log('[cv-tv] launching browser:', browserUrl);
      var xhrB = new XMLHttpRequest();
      xhrB.open('GET', browserUrl, true);
      xhrB.timeout = 4000;
      xhrB.send();

    } else if (type === 'protocol') {
      // Chiamata in background al servizio locale (es. launcher Kodi).
      // Usa fetch() standard — il proxy ha CORS headers.
      // NON usa no-cors perché HTTPS→HTTP localhost viene bloccato con no-cors.
      authFetch(target)
        .then(function(r) {
          console.log('[cv-tv] protocol response:', r.status, target);
        })
        .catch(function(err) {
          // Fallback: XMLHttpRequest (compatibilità)
          console.warn('[cv-tv] fetch fallito, provo XHR:', err);
          var xhr = new XMLHttpRequest();
          xhr.open('GET', target, true);
          xhr.timeout = 3000;
          xhr.send();
        });

    } else {
      // url, ha, ecc. → navigazione normale
      window.location.href = target;
    }
  }

  /* ── Guide overlay ──────────────────────────────────────── */
  var _guideOpen = false;

  function openGuide(guideId) {
    if (_guideOpen) return;
    _guideOpen = true;
    // Suspend schedule watchdog — prevents reload while guide overlay is open
    if (typeof _schedWatchdog !== 'undefined' && _schedWatchdog) {
      clearInterval(_schedWatchdog); _schedWatchdog = null;
    }
    if (typeof _schedTimeout !== 'undefined' && _schedTimeout) {
      clearTimeout(_schedTimeout); _schedTimeout = null;
    }
    var overlay = document.getElementById('cv-guide-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'cv-guide-overlay';
      overlay.style.cssText = [
        'position:fixed','inset:0','z-index:100',
        'background:#fff','opacity:0',
        'transition:opacity .35s ease',
        'overflow:hidden','display:flex','flex-direction:column',
      ].join(';');
      document.body.appendChild(overlay);
    }
    overlay.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:2rem;color:#999">Caricamento...</div>';
    overlay.style.display = 'flex';
    requestAnimationFrame(function() { overlay.style.opacity = '1'; });

    fetch(REST + '/guide/' + guideId, { headers: { 'X-WP-Nonce': NONCE } })
      .then(function(r) { return r.json(); })
      .then(function(guide) { renderGuideOverlay(overlay, guide); })
      .catch(function() { closeGuide(overlay); });
  }

  function renderGuideOverlay(overlay, guide) {
    var bg  = guide.bg_color   || '#ffffff';
    var fg  = guide.text_color || '#1a1a2e';
    var acc = guide.accent     || '#1a56a0';

    var html = '<style>' + buildGuideCSS(bg, fg, acc, guide.bg_image) + '</style>';
    html += '<div id="cvg-header" style="display:flex;align-items:center;gap:14px;padding:2vh 4vw;border-bottom:2px solid ' + acc + '22;background:' + bg + 'ee;backdrop-filter:blur(8px);flex-shrink:0">';
    if (guide.show_back) {
      html += '<button id="cvg-back" style="display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border-radius:50px;border:none;cursor:pointer;background:' + acc + ';color:#fff;font-size:clamp(.9rem,1.2vw,1.1rem);font-weight:700">' + (guide.back_label || 'BACK') + '</button>';
    }
    html += '<h1 style="font-size:clamp(1.4rem,2.5vw,2.8rem);font-weight:700;color:' + acc + ';flex:1;font-family:inherit">' + (guide.title || '') + '</h1>';
    html += '</div>';
    html += '<div id="cvg-inner" style="flex:1;overflow-y:auto;padding:3vh 5vw 4vh;overflow-x:hidden">';
    html += '<div class="cvg-blocks cvg-layout-' + (guide.layout || 'centered') + '">';

    (guide.blocks || []).forEach(function(block) {
      html += buildGuideBlock(block, acc, fg);
    });

    html += '</div></div>';
    html += '<div id="cvg-scrollbar" style="position:absolute;bottom:0;left:0;right:0;height:4px;background:' + acc + '22;z-index:2"><div id="cvg-scrollbar-fill" style="height:100%;background:' + acc + ';width:0;transition:width .1s"></div></div>';

    overlay.style.backgroundColor = bg;
    overlay.style.color = fg;
    overlay.innerHTML = html;

    // Back button
    var backBtn = overlay.querySelector('#cvg-back');
    if (backBtn) backBtn.addEventListener('click', function() { closeGuide(overlay); });

    // Scroll progress
    var inner = overlay.querySelector('#cvg-inner');
    var fill  = overlay.querySelector('#cvg-scrollbar-fill');
    if (inner && fill) {
      inner.addEventListener('scroll', function() {
        var max = inner.scrollHeight - inner.clientHeight;
        fill.style.width = (max > 0 ? inner.scrollTop / max * 100 : 0) + '%';
      });
    }
  }

  function closeGuide(overlay) {
    if (!overlay) overlay = document.getElementById('cv-guide-overlay');
    if (!overlay) { _guideOpen = false; return; }
    overlay.style.opacity = '0';
    setTimeout(function() {
      overlay.style.display = 'none';
      overlay.innerHTML = '';
      _guideOpen = false;
      // Resume schedule watchdog after guide is closed
      var refreshAt  = (typeof _schedRefreshAt  !== 'undefined') ? _schedRefreshAt  : 300;
      var resolvedBy = (typeof _schedResolvedBy !== 'undefined') ? _schedResolvedBy : '';
      _schedTimeout  = setTimeout(function() { window.location.reload(); }, refreshAt * 1000);
      _schedWatchdog = setInterval(function() {
        authFetch(REST + '/now')
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if ((d.resolved_by || '') !== resolvedBy) {
              clearInterval(_schedWatchdog); clearTimeout(_schedTimeout);
              window.location.reload();
            }
          }).catch(function() {});
      }, 60 * 1000);
    }, 350);
  }

  /* ── AI Chat overlay (QR + live chat display) ──────────── */

  var _aiPollTimer  = null;
  var _aiLastTs     = 0;
  var _aiSessionId  = null;
  var _aiChatEl     = null;

  function openAiQR(chatUrl) {
    var existing = document.getElementById('cv-ai-qr-overlay');
    if (existing) { closeAiOverlay(); return; }

    var baseUrl    = chatUrl || (window.location.origin + '/chat-tv/');
    var sessionId  = 'sess_' + Math.random().toString(36).substr(2,9);
    _aiSessionId   = sessionId;
    _aiLastTs      = 0;

    var sep        = (baseUrl.indexOf('?') !== -1) ? '&' : '?';
    var sessionUrl = baseUrl + sep + 'sid=' + sessionId;
    var qrSrc      = 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' + encodeURIComponent(sessionUrl);

    var overlay = document.createElement('div');
    overlay.id  = 'cv-ai-qr-overlay';
    overlay.style.cssText = [
      'position:fixed','inset:0','z-index:200',
      'background:rgba(8,12,35,0.95)',
      'display:grid',
      'grid-template-columns:1fr 1fr',
      'align-items:center',
      'padding:4vw',
      'gap:4vw',
    ].join(';');

    overlay.innerHTML =
      // Left: QR
      '<div style="text-align:center;color:#fff">' +
        '<div style="font-size:clamp(2rem,4vw,3.5rem);margin-bottom:12px">🤖</div>' +
        '<h2 style="font-size:clamp(1.4rem,3vw,2.8rem);font-weight:700;margin-bottom:8px">AI Assistant</h2>' +
        '<p style="font-size:clamp(.9rem,1.5vw,1.4rem);opacity:.65;margin-bottom:28px">Scansiona per chattare dal telefono</p>' +
        '<div style="background:#fff;border-radius:24px;padding:18px;display:inline-block;box-shadow:0 12px 48px rgba(0,0,0,.5)">' +
          '<img src="' + qrSrc + '" width="200" height="200" alt="QR" style="display:block;border-radius:8px">' +
        '</div>' +
        '<div style="margin-top:20px">' +
          '<button id="cv-ai-back-btn" onclick="window.cvTvCloseAi()" style="' +
            'display:inline-flex;align-items:center;gap:10px;' +
            'padding:16px 40px;border-radius:50px;border:3px solid rgba(255,255,255,.4);cursor:pointer;' +
            'background:rgba(255,255,255,.12);color:#fff;font-size:clamp(1.1rem,1.8vw,1.6rem);' +
            'font-weight:700;letter-spacing:.05em;transition:all .15s' +
          '">BACK</button>' +
        '</div>' +
      '</div>' +
      // Right: chat messages
      '<div style="height:70vh;display:flex;flex-direction:column;gap:0">' +
        '<div style="color:#fff;opacity:.5;font-size:clamp(.8rem,1.2vw,1rem);margin-bottom:12px;text-align:center">💬 Conversazione in diretta</div>' +
        '<div id="cv-ai-chat-display" style="' +
          'flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:12px;' +
          'padding:16px;background:rgba(255,255,255,.04);border-radius:16px;' +
          'border:1px solid rgba(255,255,255,.08);' +
        '">' +
          '<p style="color:rgba(255,255,255,.3);text-align:center;font-size:clamp(.8rem,1.1vw,.95rem);margin:auto">In attesa della prima domanda...</p>' +
        '</div>' +
      '</div>';

    _aiChatEl = null; // reset, will be found after insert

    document.body.appendChild(overlay);
    _aiChatEl = document.getElementById('cv-ai-chat-display');

    // Start polling
    _aiPollTimer = setInterval(function() { _aiPoll(); }, 2000);

    // Suspend schedule watchdog while AI overlay is open — prevents
    // window.location.reload() from destroying the overlay mid-chat.
    if (typeof _schedWatchdog !== 'undefined' && _schedWatchdog) {
      clearInterval(_schedWatchdog);
      _schedWatchdog = null;
    }
    if (typeof _schedTimeout !== 'undefined' && _schedTimeout) {
      clearTimeout(_schedTimeout);
      _schedTimeout = null;
    }

    // Close on Escape
    document.addEventListener('keydown', _aiEscListener);
  }

  function _aiEscListener(e) {
    if (e.key === 'Escape') closeAiOverlay();
  }

  function closeAiOverlay() {
    clearInterval(_aiPollTimer);
    _aiPollTimer = null;
    document.removeEventListener('keydown', _aiEscListener);
    var o = document.getElementById('cv-ai-qr-overlay');
    if (o) {
      o.style.opacity = '0';
      o.style.transition = 'opacity .25s';
      setTimeout(function() { o.remove(); }, 250);
    }
    // Assicura che Chromium sia in foreground
    var lp = (data && data.local_proxy) ? data.local_proxy : null;
    if (lp) {
      authFetch(lp + '/launch/tv').catch(function(){});
    }
    // Resume schedule watchdog now that overlay is closed
    var refreshAt = (typeof _schedRefreshAt !== 'undefined') ? _schedRefreshAt : 300;
    var resolvedBy = (typeof _schedResolvedBy !== 'undefined') ? _schedResolvedBy : '';
    _schedTimeout  = setTimeout(function() { window.location.reload(); }, refreshAt * 1000);
    _schedWatchdog = setInterval(function() {
      authFetch(REST + '/now')
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if ((d.resolved_by || '') !== resolvedBy) {
            clearInterval(_schedWatchdog);
            clearTimeout(_schedTimeout);
            window.location.reload();
          }
        })
        .catch(function() {});
    }, 60 * 1000);
  }

  function _aiPoll() {
    if (!_aiSessionId) return;
    var url = REST + '/ai/session?session_id=' + encodeURIComponent(_aiSessionId) + '&since=' + _aiLastTs;
    fetch(url)
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (!d.messages || !d.messages.length) return;
        d.messages.forEach(function(m) {
          _aiAppendMessage(m.role, m.text, m.pdf || null);
          if (m.time > _aiLastTs) _aiLastTs = m.time;
        });
      })
      .catch(function() {});
  }

  function _aiAppendMessage(role, text, pdf) {
    if (!_aiChatEl) return;

    // Remove placeholder
    var ph = _aiChatEl.querySelector('p');
    if (ph) ph.remove();

    var div   = document.createElement('div');
    var isBot = role === 'bot';
    div.style.cssText = [
      'display:flex',
      'gap:10px',
      isBot ? 'align-self:flex-start' : 'align-self:flex-end;flex-direction:row-reverse',
    ].join(';');

    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-direction:column;gap:8px;max-width:85%';

    var bubble = document.createElement('div');
    bubble.style.cssText = [
      'padding:10px 14px',
      'border-radius:14px',
      'font-size:clamp(.8rem,1.2vw,1rem)',
      'line-height:1.45',
      'color:#fff',
      isBot
        ? 'background:rgba(255,255,255,.1);border-bottom-left-radius:4px'
        : 'background:rgba(26,86,160,.8);border-bottom-right-radius:4px',
    ].join(';');
    bubble.textContent = text;
    wrap.appendChild(bubble);

    if (isBot && pdf && pdf.url) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.style.cssText = [
        'align-self:flex-start',
        'padding:10px 16px',
        'border-radius:999px',
        'border:1px solid rgba(255,255,255,.18)',
        'background:rgba(255,255,255,.12)',
        'color:#fff',
        'font-size:clamp(.78rem,1vw,.95rem)',
        'font-weight:600',
        'cursor:pointer'
      ].join(';');
      btn.textContent = '📄 Apri PDF';
      btn.addEventListener('click', function(){ openPdfViewer(pdf.url); });
      wrap.appendChild(btn);
    }

    var avatar = document.createElement('div');
    avatar.style.cssText = 'width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0';
    avatar.textContent = isBot ? '🏠' : '👤';

    div.appendChild(avatar);
    div.appendChild(wrap);
    _aiChatEl.appendChild(div);
    _aiChatEl.scrollTop = _aiChatEl.scrollHeight;
  }

  // Handle Escape and BrowserBack key for guide and AI overlay
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.key === 'BrowserBack' || e.key === 'GoBack') {
      window.cvTvCloseAll();
    }
  }, true);

  function buildGuideCSS(bg, fg, acc, bgImg) {
    return [
      '.cvg-blocks{max-width:960px;margin:0 auto}',
      '.cvg-layout-two-column .cvg-blocks{display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:100%}',
      '.cvg-layout-magazine .cvg-blocks{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;max-width:100%}',
      '.cvg-block{margin-bottom:clamp(14px,2.5vh,32px)}',
      '.cvg-heading h2{font-size:clamp(1.2rem,2vw,2rem);font-weight:700;color:' + acc + ';padding-bottom:10px;border-bottom:3px solid ' + acc + '33;font-family:inherit}',
      '.cvg-heading p{font-size:clamp(.85rem,1.1vw,1rem);color:' + fg + '99;margin-top:4px;font-family:inherit}',
      '.cvg-text{font-size:clamp(.95rem,1.3vw,1.2rem);line-height:1.65;font-family:inherit}',
      '.cvg-text strong{font-weight:700;color:' + acc + '}',
      '.cvg-icon-text{display:flex;gap:18px;align-items:flex-start;padding:18px;border-radius:14px;background:' + acc + '0d}',
      '.cvg-icon-text .ci-icon{font-size:clamp(1.8rem,2.8vw,2.8rem);flex-shrink:0;line-height:1}',
      '.cvg-icon-text h3{font-size:clamp(1rem,1.5vw,1.4rem);font-weight:700;color:' + acc + ';margin-bottom:5px;font-family:inherit}',
      '.cvg-icon-text p{font-size:clamp(.85rem,1.1vw,1.05rem);line-height:1.55;font-family:inherit}',
      '.cvg-two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px}',
      '.cvg-two-col .cc{padding:18px;border-radius:12px;background:' + acc + '08}',
      '.cvg-two-col .cc h3{font-size:clamp(.95rem,1.4vw,1.3rem);font-weight:700;color:' + acc + ';margin-bottom:6px;font-family:inherit}',
      '.cvg-two-col .cc p{font-size:clamp(.85rem,1.1vw,1rem);line-height:1.6;font-family:inherit}',
      '.cvg-img-text{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:center}',
      '.cvg-img-text img{width:100%;border-radius:14px;box-shadow:0 6px 24px rgba(0,0,0,.12);display:block}',
      '.cvg-img-text h3{font-size:clamp(1rem,1.5vw,1.4rem);font-weight:700;color:' + acc + ';margin-bottom:8px;font-family:inherit}',
      '.cvg-img-text p{font-size:clamp(.85rem,1.1vw,1rem);line-height:1.6;font-family:inherit}',
      '.cvg-info-box{display:flex;gap:14px;padding:18px 22px;border-radius:12px;align-items:flex-start}',
      '.cvg-info-box.si{background:#e8f4fd;border-left:5px solid #2196F3}',
      '.cvg-info-box.ss{background:#e8f5e9;border-left:5px solid #4CAF50}',
      '.cvg-info-box.sw{background:#fff3e0;border-left:5px solid #FF9800}',
      '.cvg-info-box.st{background:#fffde7;border-left:5px solid #FFC107}',
      '.cvg-info-box .bi{font-size:clamp(1.4rem,2.2vw,2.2rem);flex-shrink:0;line-height:1}',
      '.cvg-info-box .bt{font-size:clamp(.88rem,1.2vw,1.1rem);line-height:1.6;font-family:inherit}',
      '.cvg-list{background:' + acc + '08;border-radius:12px;padding:18px 22px}',
      '.cvg-list h3{font-size:clamp(.95rem,1.4vw,1.3rem);font-weight:700;color:' + acc + ';margin-bottom:10px;font-family:inherit}',
      '.cvg-list ul{list-style:none;padding:0}',
      '.cvg-list li{font-size:clamp(.88rem,1.2vw,1.1rem);padding:7px 0;border-bottom:1px solid ' + acc + '18;line-height:1.4;font-family:inherit}',
      '.cvg-list li:last-child{border-bottom:none}',
      '.cvg-divider{height:2px;background:linear-gradient(90deg,' + acc + '44,transparent);margin:6px 0;border-radius:1px}',
      '.cvg-qr{text-align:center;padding:20px}',
      '.cvg-qr h3{font-size:clamp(.95rem,1.4vw,1.3rem);font-weight:700;color:' + acc + ';margin-bottom:14px;font-family:inherit}',
      '.cvg-qr img{width:clamp(100px,14vw,180px);border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.1)}',
      '.cvg-qr p{font-size:clamp(.78rem,.95vw,.9rem);opacity:.7;margin-top:6px;font-family:inherit}',
      '.cvg-cta{text-align:center;padding:6px 0}',
      '.cvg-cta a{display:inline-block;padding:12px 32px;border-radius:50px;font-size:clamp(.95rem,1.4vw,1.3rem);font-weight:700;text-decoration:none;transition:transform .15s;font-family:inherit}',
      '.cvg-cta a.p{background:' + acc + ';color:#fff;box-shadow:0 4px 18px ' + acc + '44}',
      '.cvg-cta a.s{background:transparent;color:' + acc + ';border:2.5px solid ' + acc + '}',
      '.cvg-cta a.l{display:block;border-radius:14px;padding:16px}',
      '.cvg-cta a:hover{transform:translateY(-2px)}',
    ].join('\n');
  }

  function buildGuideBlock(block, acc, fg) {
    var t = block.type || 'text';
    var h = '<div class="cvg-block cvg-block--' + t + '">';

    function esc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function inl(s) {
      return esc(s)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/_(.+?)_/g, '<em>$1</em>');
    }

    if (t === 'heading') {
      h += '<div class="cvg-heading">';
      if (block.heading) h += '<h2>' + esc(block.heading) + '</h2>';
      if (block.subtitle) h += '<p>' + esc(block.subtitle) + '</p>';
      h += '</div>';
    } else if (t === 'text') {
      h += '<div class="cvg-text">' + inl(block.text||'').replace(/\n/g,'<br>') + '</div>';
    } else if (t === 'icon_text') {
      h += '<div class="cvg-icon-text">';
      if (block.icon) h += '<div class="ci-icon">' + esc(block.icon) + '</div>';
      h += '<div><h3>' + esc(block.heading||'') + '</h3><p>' + esc(block.text||'') + '</p></div>';
      h += '</div>';
    } else if (t === 'two_col') {
      h += '<div class="cvg-two-col"><div class="cc"><h3>' + esc(block.col1_heading||'') + '</h3><p>' + esc(block.col1_text||'') + '</p></div>';
      h += '<div class="cc"><h3>' + esc(block.col2_heading||'') + '</h3><p>' + esc(block.col2_text||'') + '</p></div></div>';
    } else if (t === 'image_text') {
      var side = block.img_side === 'right' ? 'dir:rtl' : '';
      h += '<div class="cvg-img-text" style="' + side + '">';
      if (block.image) h += '<img src="' + esc(block.image) + '" alt="" loading="lazy">';
      h += '<div style="direction:ltr"><h3>' + esc(block.heading||'') + '</h3><p>' + esc(block.text||'') + '</p></div></div>';
    } else if (t === 'info_box') {
      var cls = {info:'si',success:'ss',warning:'sw',tip:'st'}[block.style||'info'] || 'si';
      h += '<div class="cvg-info-box ' + cls + '">';
      if (block.icon) h += '<div class="bi">' + esc(block.icon) + '</div>';
      h += '<div class="bt">' + inl(block.text||'').replace(/\n/g,'<br>') + '</div></div>';
    } else if (t === 'list') {
      h += '<div class="cvg-list">';
      if (block.heading) h += '<h3>' + esc(block.heading) + '</h3>';
      var items = (block.items||'').split('\n').map(function(s){return s.trim();}).filter(Boolean);
      if (items.length) {
        h += '<ul>';
        items.forEach(function(it){ h += '<li>' + esc(it) + '</li>'; });
        h += '</ul>';
      }
      h += '</div>';
    } else if (t === 'divider') {
      h += '<div class="cvg-divider"></div>';
    } else if (t === 'qr') {
      if (block.url) {
        h += '<div class="cvg-qr">';
        if (block.heading) h += '<h3>' + esc(block.heading) + '</h3>';
        h += '<img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(block.url) + '" alt="QR">';
        if (block.label) h += '<p>' + esc(block.label) + '</p>';
        h += '</div>';
      }
    } else if (t === 'cta_button') {
      if (block.label) {
        var cls2 = {primary:'p',secondary:'s',large:'l'}[block.style||'primary'] || 'p';
        h += '<div class="cvg-cta"><a href="' + esc(block.url||'#') + '" class="' + cls2 + '">' + esc(block.label) + '</a></div>';
      }
    }

    return h + '</div>';
  }

  function handleNav(dir) {
    var btns = getAllBtns();
    if (!btns.length) return;
    var focused = getFocusedBtn();
    var idx  = focused ? btns.indexOf(focused) : 0;
    var cols = getColumns();
    var next = idx;
    if      (dir === 'right')  next = Math.min(btns.length - 1, idx + 1);
    else if (dir === 'left')   next = Math.max(0, idx - 1);
    else if (dir === 'down')   next = Math.min(btns.length - 1, idx + cols);
    else if (dir === 'up')     next = Math.max(0, idx - cols);
    else if (dir === 'select') { if (focused) activateBtn(focused); return; }
    if (next !== idx) setFocus(btns[next]);
  }

  document.addEventListener('keydown', e => {
    const btns = getAllBtns();
    if (!btns.length) {
      // No buttons — allow slide advance with any key
      if (e.key === 'PageDown' || e.key === 'MediaNextTrack') {
        clearTimeout(slideTimer);
        showSlide((currentIdx + 1) % screens.length);
      }
      return;
    }

    switch (e.key) {
      case 'ArrowRight': handleNav('right'); e.preventDefault(); return;
      case 'ArrowLeft':  handleNav('left');  e.preventDefault(); return;
      case 'ArrowDown':  handleNav('down');  e.preventDefault(); return;
      case 'ArrowUp':    handleNav('up');    e.preventDefault(); return;
      case 'Enter':
      case ' ':
        e.preventDefault();
        handleNav('select');
        return;
      case 'PageDown':
      case 'MediaNextTrack':
        clearTimeout(slideTimer);
        showSlide((currentIdx + 1) % screens.length);
        return;
      default: return;
    }
  });

  /* ── Auto-reload at schedule boundary ──────────────────────── */
  // Dual strategy: one-shot timeout al prossimo boundary + watchdog ogni 60s
  // Il watchdog è il rimedio principale: se Chromium throttla il setTimeout
  // (kiosk, schermo spento, tab inattivo), il reload avviene comunque.

  var _schedResolvedBy = (data.resolved_by || '');
  var _schedRefreshAt  = (data.refresh_at  || 300);

  // One-shot al prossimo boundary (fallback rapido se il watchdog è lento)
  var _schedTimeout = setTimeout(function() { window.location.reload(); }, _schedRefreshAt * 1000);

  // Watchdog: ogni 60s interroga /wp-json/cv-tv/v1/now e ricarica se il
  // contenuto attivo è cambiato (resolved_by diverso = cambio di fascia oraria)
  var _schedWatchdog = setInterval(function() {
    authFetch(REST + '/now')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        var newResolvedBy = d.resolved_by || '';
        if (newResolvedBy !== _schedResolvedBy) {
          clearInterval(_schedWatchdog);
          clearTimeout(_schedTimeout);
          window.location.reload();
        }
      })
      .catch(function() { /* ignora errori di rete temporanei */ });
  }, 60 * 1000);

  /* ── Init ───────────────────────────────────────────────────── */

  function init() {
    if (!screens.length) {
      stageA.innerHTML = `
        <div class="cv-slide__bg"></div>
        <div class="cv-slide__overlay" style="--ov-top:0.7;--ov-bot:0.85"></div>
        <header class="cv-header" style="position:relative;z-index:2;margin:auto;text-align:center;padding:4vh">
          <h1 class="cv-title">Casa Volterra TV</h1>
          <p class="cv-subtitle">Nessuna schermata configurata. Vai in Casa Volterra TV → Screens.</p>
        </header>`;
      stageA.classList.add('cv-active');
      const hud = document.getElementById('cv-hud');
      if (hud) hud.style.display = '';
      startClock();
      return;
    }

    buildDots();
    showSlide(0, 'cut'); // first slide: instant, no transition
  }

  /* ── PDF Viewer overlay ─────────────────────────────────── */

  var _pdfOpen = false;

  function openPdfViewer(pdfUrl) {
    if (_pdfOpen) { closePdfViewer(); return; }
    _pdfOpen = true;

    var overlay = document.createElement('div');
    overlay.id  = 'cv-pdf-overlay';
    overlay.style.cssText = [
      'position:fixed','inset:0','z-index:300',
      'background:#1a1a2e',
      'display:flex','flex-direction:column',
      'overflow:hidden',
    ].join(';');

    // Header bar
    var header = '<div style="' +
      'background:rgba(0,0,0,.6);backdrop-filter:blur(8px);' +
      'padding:clamp(10px,2vh,18px) clamp(16px,3vw,28px);' +
      'display:flex;align-items:center;gap:16px;flex-shrink:0;' +
      'border-bottom:1px solid rgba(255,255,255,.1)' +
    '">' +
      '<button id="cv-pdf-back" style="' +
        'padding:10px 24px;border-radius:50px;border:none;cursor:pointer;' +
        'background:rgba(255,255,255,.15);color:#fff;' +
        'font-size:clamp(.9rem,1.4vw,1.3rem);font-weight:600;' +
        'transition:background .15s' +
      '">BACK</button>' +
      '<button id="cv-pdf-up" style="padding:10px 18px;border-radius:50px;border:none;cursor:pointer;background:rgba(255,255,255,.12);color:#fff;font-size:clamp(.9rem,1.2vw,1.1rem);font-weight:700">▲</button>' +
      '<button id="cv-pdf-down" style="padding:10px 18px;border-radius:50px;border:none;cursor:pointer;background:rgba(255,255,255,.12);color:#fff;font-size:clamp(.9rem,1.2vw,1.1rem);font-weight:700">▼</button>' +
      '<span style="color:#fff;font-size:clamp(.9rem,1.3vw,1.2rem);opacity:.7;flex:1;text-align:center">' +
        '📄 ' + decodeURIComponent(pdfUrl.split('/').pop()).replace('.pdf','') +
      '</span>' +
      '<div style="color:rgba(255,255,255,.4);font-size:clamp(.75rem,1vw,.95rem)">' +
        'Usa ▲ ▼ o il mouse air per scorrere' +
      '</div>' +
    '</div>';

    // PDF iframe — most reliable cross-browser PDF viewer
    var viewer = '<div style="flex:1;overflow:hidden;position:relative">' +
      '<iframe src="' + pdfUrl + '" style="' +
        'width:100%;height:100%;border:none;' +
        'background:#fff' +
      '" title="PDF Viewer"></iframe>' +
    '</div>';

    overlay.innerHTML = header + viewer;
    document.body.appendChild(overlay);

    overlay.querySelector('#cv-pdf-back').addEventListener('click', closePdfViewer);
    function pdfScroll(delta) {
      var iframe = overlay.querySelector('iframe');
      if (!iframe || !iframe.contentWindow) return;
      try { iframe.contentWindow.scrollBy(0, delta); } catch (e) {}
    }
    overlay.querySelector('#cv-pdf-up').addEventListener('click', function(){ pdfScroll(-400); });
    overlay.querySelector('#cv-pdf-down').addEventListener('click', function(){ pdfScroll(400); });

    // Keyboard scroll
    overlay.addEventListener('keydown', function(e) {
      if (e.key === 'ArrowDown' || e.key === 'PageDown') {
        pdfScroll(400);
      }
      if (e.key === 'ArrowUp' || e.key === 'PageUp') {
        pdfScroll(-400);
      }
    });
  }

  function closePdfViewer() {
    var o = document.getElementById('cv-pdf-overlay');
    if (o) o.remove();
    _pdfOpen = false;
  }

  /* ── Tastiera virtuale TV ───────────────────────────────── */

  var _kb = null, _kbTarget = null, _kbShift = false, _kbCaps = false;

  var KB_ROWS = [
    ['1','2','3','4','5','6','7','8','9','0','⌫'],
    ['q','w','e','r','t','y','u','i','o','p'],
    ['a','s','d','f','g','h','j','k','l','⏎'],
    ['⇧','z','x','c','v','b','n','m',',','.','⇧'],
    ['@',{w:4,k:'SPACE'},'_','-','?','✕'],
  ];

  function showKeyboard(targetEl) {
    if (_kb) { _kb.remove(); }
    _kbTarget = targetEl; _kbShift = false; _kbCaps = false;
    _kb = document.createElement('div');
    _kb.id = 'cv-vkb';
    _kb.style.cssText = 'position:fixed;bottom:0;left:0;right:0;z-index:500;background:rgba(12,16,40,0.97);padding:clamp(8px,1.5vh,16px) clamp(6px,1vw,14px);box-shadow:0 -4px 32px rgba(0,0,0,.6);border-top:1px solid rgba(255,255,255,.08)';
    _kbRender();
    document.body.appendChild(_kb);
    setTimeout(function(){ if(targetEl) targetEl.scrollIntoView({behavior:'smooth',block:'center'}); }, 100);
  }

  function hideKeyboard() { if(_kb){_kb.remove();_kb=null;} _kbTarget=null; }

  function _kbRender() {
    if (!_kb) return;
    var html = '';
    KB_ROWS.forEach(function(row) {
      html += '<div style="display:flex;gap:clamp(3px,.4vw,6px);margin-bottom:clamp(3px,.4vh,6px);justify-content:center">';
      row.forEach(function(k) {
        var isObj = typeof k === 'object';
        var key   = isObj ? k.k : k;
        var flex  = isObj ? k.w : 1;
        var lbl   = (_kbShift||_kbCaps) && key.length===1 && key>='a'&&key<='z' ? key.toUpperCase() : (key==='SPACE'?' ':key);
        var sp    = ['⌫','⏎','⇧','✕'].indexOf(key) >= 0;
        var act   = key==='⇧' && (_kbShift||_kbCaps);
        html += '<button data-key="'+key+'" style="flex:'+flex+';min-width:clamp(26px,3.5vw,50px);height:clamp(36px,5.5vh,56px);border:none;border-radius:8px;font-size:clamp(12px,1.6vw,19px);cursor:pointer;font-weight:'+(sp?'700':'400')+';background:'+(act?'rgba(26,86,160,.9)':sp?'rgba(255,255,255,.12)':'rgba(255,255,255,.07)')+';color:#fff">'+lbl+'</button>';
      });
      html += '</div>';
    });
    _kb.innerHTML = html;
    _kb.addEventListener('mousedown', function(e){e.preventDefault();});
    _kb.addEventListener('click', _kbClick);
  }

  function _kbClick(e) {
    var btn = e.target.closest('button[data-key]');
    if (!btn || !_kbTarget) return;
    var key = btn.dataset.key;
    if (key==='✕') { hideKeyboard(); return; }
    if (key==='⌫') {
      var s=_kbTarget.selectionStart, en=_kbTarget.selectionEnd, v=_kbTarget.value;
      _kbTarget.value = s!==en ? v.slice(0,s)+v.slice(en) : (s>0?v.slice(0,s-1)+v.slice(s):v);
      _kbTarget.setSelectionRange(s!==en?s:Math.max(0,s-1), s!==en?s:Math.max(0,s-1));
      _kbTarget.dispatchEvent(new Event('input',{bubbles:true})); return;
    }
    if (key==='⏎') {
      if (_kbTarget.tagName==='TEXTAREA') { _ins(_kbTarget,'\n'); }
      else { _kbTarget.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true})); hideKeyboard(); }
      return;
    }
    if (key==='⇧') {
      if (_kbShift&&!_kbCaps){_kbCaps=true;_kbShift=false;}
      else if (_kbCaps){_kbCaps=false;_kbShift=false;}
      else {_kbShift=true;}
      _kbRender(); return;
    }
    if (key==='SPACE') { _ins(_kbTarget,' '); return; }
    var ch = (_kbShift||_kbCaps)&&key.length===1&&key>='a'&&key<='z' ? key.toUpperCase() : key;
    _ins(_kbTarget, ch);
    if (_kbShift&&!_kbCaps) { _kbShift=false; _kbRender(); }
  }

  function _ins(el, ch) {
    var s=el.selectionStart, en=el.selectionEnd;
    el.value = el.value.slice(0,s)+ch+el.value.slice(en);
    el.setSelectionRange(s+ch.length, s+ch.length);
    el.dispatchEvent(new Event('input',{bubbles:true}));
  }

  // Auto-show on focus inside overlays
  document.addEventListener('focusin', function(e) {
    var el = e.target;
    if (!el) return;
    if ((el.tagName==='INPUT'||el.tagName==='TEXTAREA') &&
        el.closest('#cv-guide-overlay,#cv-ai-qr-overlay')) {
      showKeyboard(el);
    }
  }, true);


  /* ── Remote command polling (per tasto BACK telecomando) ──── */
  (function startRemoteCommandPoll() {
    var localProxy = (data && data.local_proxy || '').replace(/\/+$/, '');
    if (!localProxy) return; // Solo se c'è il proxy locale

    setInterval(function() {
      authFetch(localProxy + '/poll')
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d.command === 'back') {
            window.cvTvCloseAll();
          }
          if (d.nav) {
            handleNav(d.nav);
          }
        })
        .catch(function() {}); // ignora errori di rete
    }, 200);
  })();

  // Esponi funzioni di chiusura overlay globalmente
  // (necessario per onclick="" negli innerHTML dinamici)
  window.cvTvCloseAi  = function() { closeAiOverlay(); };
  window.cvTvClosePdf = function() { closePdfViewer(); };
  window.cvTvCloseAll = function() {
    if (_pdfOpen)              { closePdfViewer(); return; }
    if (_aiPollTimer !== null)  { closeAiOverlay(); return; }
    if (_guideOpen)             { closeGuide(null); }
  };

  // ── Feature flags: nasconde pulsanti disabilitati ──────────
  (function enforceFeatureFlags() {
    if (KODI_ENABLED && NETFLIX_ENABLED && BROWSER_ENABLED) return;
    // Cerca pulsanti con data-type="protocol" che puntano a /launch/kodi, /launch/netflix, /launch/browser
    document.querySelectorAll('[data-type="protocol"]').forEach(function(btn) {
      var t = (btn.dataset.target || '').toLowerCase();
      if (!KODI_ENABLED    && t.indexOf('/launch/kodi')    !== -1) btn.style.display = 'none';
      if (!NETFLIX_ENABLED && t.indexOf('/launch/netflix') !== -1) btn.style.display = 'none';
      if (!BROWSER_ENABLED && t.indexOf('/launch/browser') !== -1) btn.style.display = 'none';
    });
  })();


  init();
})();