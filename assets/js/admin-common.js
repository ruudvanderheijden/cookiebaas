/* Cookiebaas 3 — gedeelde admin-JS (alle nieuwe pagina's). Vanilla JS. */
(function () {
  'use strict';

  var HEX = /^#[0-9a-fA-F]{6}$/;

  /** Normaliseer wat klanten plakken: 3 of 6 hex-tekens, met of zonder spaties, naar een lowercase hexcode met voorloop-hekje. */
  function normalizeHex(v) {
    v = String(v || '').trim();
    if (/^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/.test(v)) v = '#' + v;
    if (/^#[0-9a-fA-F]{3}$/.test(v)) v = '#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3];
    return HEX.test(v) ? v.toLowerCase() : null;
  }

  /* ---- Kleurvelden: kleurvlak <-> hex-veld, en "Wissen" ---- */
  document.querySelectorAll('.cm-color').forEach(function (row) {
    var swatch = row.querySelector('input[type=color]');
    var hex = row.querySelector('input.cm-hex');
    var clear = row.querySelector('.cm-color-clear');
    function sync() {
      var ok = normalizeHex(hex.value);
      row.classList.toggle('cm-color-empty', !ok);
      if (ok) swatch.value = ok;
      if (clear) clear.hidden = hex.value.trim() === '';
    }
    swatch.addEventListener('input', function () {
      hex.value = swatch.value;
      sync();
      hex.dispatchEvent(new Event('input', { bubbles: true }));
    });
    hex.addEventListener('input', sync);
    hex.addEventListener('blur', function () {
      var ok = normalizeHex(hex.value);
      if (ok && ok !== hex.value) { hex.value = ok; hex.dispatchEvent(new Event('input', { bubbles: true })); }
    });
    if (clear) clear.addEventListener('click', function () {
      hex.value = '';
      sync();
      hex.dispatchEvent(new Event('input', { bubbles: true }));
      hex.focus();
    });
    sync();
  });

  /* ---- Voorwaardelijke rijen (show_if) ---- */
  function fieldValue(key) {
    var els = document.querySelectorAll('[data-cm-key="' + key + '"]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (el.type === 'radio') { if (el.checked) return el.value; continue; }
      if (el.type === 'checkbox') return el.checked ? '1' : '0';
      return el.value;
    }
    return '';
  }
  function applyShowIf() {
    document.querySelectorAll('[data-cm-show-if]').forEach(function (row) {
      var cond = JSON.parse(row.getAttribute('data-cm-show-if'));
      row.hidden = !Object.keys(cond).every(function (k) {
        var want = cond[k], have = fieldValue(k);
        return Array.isArray(want) ? want.indexOf(have) !== -1 : String(want) === have;
      });
    });
  }
  document.addEventListener('change', applyShowIf);
  applyShowIf();

  /* ---- Schakelaars (Licht/Donker, Nederlands/English) ---- */
  document.querySelectorAll('[data-cm-switch]').forEach(function (sw) {
    var name = sw.getAttribute('data-cm-switch');
    function show(val) {
      document.querySelectorAll('[data-cm-pane^="' + name + ':"]').forEach(function (p) {
        p.hidden = p.getAttribute('data-cm-pane') !== name + ':' + val;
      });
      sw.querySelectorAll('a[data-cm-switch-to]').forEach(function (a) {
        var on = a.getAttribute('data-cm-switch-to') === val;
        a.classList.toggle('current', on);
        if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
      });
      document.dispatchEvent(new CustomEvent('cm-switch', { detail: { name: name, value: val } }));
    }
    sw.addEventListener('click', function (e) {
      var a = e.target.closest('a[data-cm-switch-to]');
      if (!a) return;
      e.preventDefault();
      show(a.getAttribute('data-cm-switch-to'));
    });
    var first = sw.querySelector('a.current') || sw.querySelector('a[data-cm-switch-to]');
    if (first) show(first.getAttribute('data-cm-switch-to'));
  });

  /* ---- Media-kiezer (zweefknop-afbeelding) ---- */
  document.querySelectorAll('.cm-media').forEach(function (box) {
    var input = box.querySelector('input[type=hidden]');
    var img = box.querySelector('.cm-media-img');
    var remove = box.querySelector('.cm-media-remove');
    function sync() {
      img.hidden = !input.value;
      if (input.value) img.src = input.value; else img.removeAttribute('src');
      remove.hidden = !input.value;
    }
    box.querySelector('.cm-media-pick').addEventListener('click', function () {
      if (!window.wp || !window.wp.media) return;
      var frame = window.wp.media({ title: 'Afbeelding kiezen', multiple: false, library: { type: 'image' } });
      frame.on('select', function () {
        input.value = frame.state().get('selection').first().toJSON().url;
        sync();
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
      frame.open();
    });
    remove.addEventListener('click', function () {
      input.value = '';
      sync();
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    sync();
  });

  /* ---- Bevestigen bij destructieve acties ---- */
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-cm-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  }, true);

  /* ---- Waarschuwing bij niet-opgeslagen wijzigingen ---- */
  var dirty = false;
  document.querySelectorAll('form.cm-form').forEach(function (form) {
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
  });
  window.addEventListener('beforeunload', function (e) {
    if (!dirty) return;
    e.preventDefault();
    e.returnValue = '';
  });
})();
