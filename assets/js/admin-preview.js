/* Cookiebaas 3 — live voorbeeld (Banner › Vormgeving en Teksten).
 * De banner is de échte frontend-markup met frontend.css, in een iframe
 * zonder scripts (sandbox). Dit script zet alleen CSS-variabelen en teksten. */
(function () {
  'use strict';

  var cfg = window.CM_PREVIEW;
  var frame = document.getElementById('cm-preview-frame');
  var tpl = document.getElementById('cm-preview-markup');
  if (!cfg || !frame || !tpl) return;

  var HEX = /^#[0-9a-fA-F]{6}$/;
  var state = { theme: cfg.theme, lang: cfg.lang, view: 'banner', device: 'desktop' };
  var doc = null;

  function fieldValue(key) {
    var els = document.querySelectorAll('.cm-form [data-cm-key="' + key + '"]');
    if (!els.length) return null;
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (el.type === 'radio') { if (el.checked) return el.value; continue; }
      if (el.type === 'checkbox') return el.checked ? '1' : '0';
      return el.value;
    }
    return null;
  }

  function fmt(v, unit, empty) {
    if (v === null) return null; // veld staat niet op dit tabblad: opgeslagen waarde blijft staan
    if (v === '') return empty;
    if (unit === 'px' || unit === 'alpha') {
      var n = parseInt(v, 10);
      if (isNaN(n)) return null;
      return unit === 'px' ? n + 'px' : String(n / 100);
    }
    return HEX.test(v.trim()) ? v.trim().toLowerCase() : null;
  }

  function applyVars() {
    var root = doc.documentElement;
    var base = cfg.initial[state.theme] || {};
    Object.keys(base).forEach(function (name) { root.style.setProperty(name, base[name]); });
    cfg.vars.forEach(function (m) {
      var key = state.theme === 'dark' && m[1] ? m[1] : m[0];
      var v = fieldValue(key);
      // Net als de frontend (`?:`): donker 0 of leeg wordt de standaardwaarde, zodat de preview de site toont.
      if (state.theme === 'dark' && m[5] != null && v !== null && (v === '' || parseInt(v, 10) === 0)) v = String(m[5]);
      var out = fmt(v, m[3], m[4]);
      if (out) root.style.setProperty(m[2], out);
    });
  }

  function applyTexts() {
    var sfx = state.lang === 'en' ? '_en' : '';
    Object.keys(cfg.texts).forEach(function (key) {
      var t = cfg.texts[key];
      var v = fieldValue(key + sfx);
      if (v === null) return;
      if (v === '' && sfx) v = fieldValue(key) || ''; // net als de frontend: lege EN valt terug op NL
      if (key === 'txt_embed_body') v = v.split('{service}').join('<strong>YouTube</strong>');
      doc.querySelectorAll(t.sel).forEach(function (el) {
        if (key === 'txt_float_label' && el.querySelector('svg, img')) return; // icoonknop: tekst is alleen het label
        if (t.html) el.innerHTML = v; else el.textContent = v;
      });
    });
  }

  function applyView() {
    var ov = doc.getElementById('cm-overlay'), bn = doc.getElementById('cm-banner');
    var pr = doc.getElementById('cm-prefs'), fl = doc.getElementById('cm-float');
    if (ov) ov.classList.toggle('cm-active', state.view === 'banner' || state.view === 'prefs');
    if (bn) bn.classList.toggle('cm-active', state.view === 'banner');
    if (pr) pr.classList.toggle('cm-active', state.view === 'prefs');
    if (fl) fl.classList.toggle('cm-visible', state.view === 'float');
    doc.documentElement.setAttribute('data-cm-view', state.view);
  }

  function applyDevice() {
    var w = state.device === 'mobile' ? 390 : 1280;
    var h = state.device === 'mobile' ? 760 : 800;
    var stage = frame.parentNode;
    var scale = Math.min(1, stage.clientWidth / w);
    frame.style.width = w + 'px';
    frame.style.height = h + 'px';
    frame.style.transform = 'scale(' + scale + ')';
    stage.style.height = Math.round(h * scale) + 'px';
  }

  function applyAll() { if (doc) { applyVars(); applyTexts(); applyView(); } }

  function build() {
    doc = frame.contentDocument;
    doc.open();
    doc.write('<!DOCTYPE html><html><head><meta charset="utf-8">' +
      '<link rel="stylesheet" href="' + cfg.frontendCss + '">' +
      '<link rel="stylesheet" href="' + cfg.stageCss + '">' +
      '</head><body><div class="cm-stage-page"><div></div><div></div><div></div><div></div></div>' +
      tpl.innerHTML + '</body></html>');
    doc.close();
    ['cm-overlay', 'cm-banner', 'cm-prefs'].forEach(function (id) {
      var el = doc.getElementById(id);
      if (el) el.style.display = '';
    });
    applyAll();
  }

  document.addEventListener('input', function (e) { if (e.target.closest('.cm-form')) applyAll(); });
  document.addEventListener('change', function (e) { if (e.target.closest('.cm-form')) applyAll(); });
  document.addEventListener('cm-switch', function (e) {
    if (e.detail.name === 'theme') state.theme = e.detail.value;
    if (e.detail.name === 'lang') state.lang = e.detail.value;
    if (e.detail.name === 'view') state.view = e.detail.value;
    if (e.detail.name === 'device') { state.device = e.detail.value; applyDevice(); }
    applyAll();
  });
  window.addEventListener('resize', applyDevice);

  build();
  applyDevice();
})();
