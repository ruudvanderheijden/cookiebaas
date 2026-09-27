/* Cookiebaas 3 — Cookies › Scannen: scan in batches, resultaten toevoegen,
 * cookiedatabase laden. Scandata komt alleen via textContent in de pagina. */
(function () {
  'use strict';

  var cfg = window.CM_COOKIES;
  if (!cfg) return;

  var LABELS = { functional: 'Functioneel', analytics: 'Analytisch', marketing: 'Marketing', unknown: 'Onbekend' };
  var ORDER = { functional: 0, analytics: 1, marketing: 2, unknown: 3 };
  var HOW_LABELS = { embed: 'Embed', server: 'HTTP-header', browser: 'Browser', storage: 'Opslag', host: 'Extern script' };

  function post(action, data) {
    var body = new URLSearchParams();
    body.append('action', action);
    Object.keys(data).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) v.forEach(function (x) { body.append(k + '[]', x); });
      else body.append(k, v);
    });
    return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) { return r.json(); });
  }

  function el(tag, text, cls) {
    var e = document.createElement(tag);
    if (text !== undefined && text !== null) e.textContent = text;
    if (cls) e.className = cls;
    return e;
  }

  function notice(box, type, text) {
    box.textContent = '';
    box.className = 'notice notice-' + type + ' inline';
    box.appendChild(el('p', text));
    box.hidden = false;
  }

  /* ---- Cookiedatabase laden ---- */
  var dbBtn = document.getElementById('cm-cookie-db-import');
  var dbOut = document.getElementById('cm-cookie-db-status');
  if (dbBtn && dbOut) dbBtn.addEventListener('click', function () {
    dbBtn.disabled = true;
    notice(dbOut, 'info', 'De Open Cookie Database wordt gedownload…');
    post('cm_import_cookie_db', { nonce: cfg.nonces.importDb }).then(function (r) {
      if (r && r.success) notice(dbOut, 'success', r.data.imported + ' cookies geïmporteerd.');
      else notice(dbOut, 'error', (r && r.data && r.data.msg) || 'Het laden is mislukt.');
    }).catch(function () {
      notice(dbOut, 'error', 'Verbindingsfout. Controleer of de server internettoegang heeft.');
    }).then(function () { dbBtn.disabled = false; });
  });

  /* ---- Scan ---- */
  var scanBtn = document.getElementById('cm-scan-start');
  var result = document.getElementById('cm-scan-result');
  if (!scanBtn || !result) return;
  var found = [];
  var addStatus = null; // één hergebruikt statuselement: herhaalde mislukkingen vervangen elkaar i.p.v. te stapelen

  function showAddStatus(text, cls) {
    if (!addStatus) {
      addStatus = el('p', text, cls);
      result.appendChild(addStatus);
    } else {
      addStatus.textContent = text;
      addStatus.className = cls;
    }
  }

  function renderResults(pages, failedPages) {
    failedPages = failedPages || 0;
    if (pages > 0 && failedPages === pages) {
      notice(result, 'error', 'De scan is mislukt: geen enkele pagina kon worden gescand.');
      return;
    }
    found.sort(function (a, b) {
      var oa = ORDER[a.type] !== undefined ? ORDER[a.type] : 9;
      var ob = ORDER[b.type] !== undefined ? ORDER[b.type] : 9;
      return oa !== ob ? oa - ob : String(a.name).localeCompare(String(b.name));
    });
    result.textContent = '';
    result.className = '';
    if (failedPages > 0) {
      var warn = el('div');
      notice(warn, 'warning', failedPages + ' van ' + pages + ' pagina’s konden niet worden gescand.');
      result.appendChild(warn);
    }
    result.appendChild(el('p', pages + ' pagina’s gescand, ' + found.length + ' cookies gevonden.'));
    if (!found.length) {
      result.appendChild(el('p', 'Geen cookies gevonden. Kijk ook in uw browser via F12 › Applicatie › Cookies.', 'description'));
      return;
    }
    var bar = el('p');
    var all = el('button', 'Alle gevonden cookies toevoegen', 'button button-primary');
    all.type = 'button';
    all.id = 'cm-scan-add-all';
    bar.appendChild(all);
    result.appendChild(bar);

    var table = el('table', null, 'widefat striped');
    var head = table.createTHead().insertRow();
    ['Cookie', 'Categorie', 'Provider', 'Omschrijving', 'Looptijd', 'Bron', ''].forEach(function (h) {
      var th = el('th', h); th.scope = 'col'; head.appendChild(th);
    });
    var body = table.createTBody();
    found.forEach(function (ck, i) {
      var tr = body.insertRow();
      var name = el('code', ck.name);
      var td = tr.insertCell(); td.appendChild(name);
      tr.insertCell().textContent = LABELS[ck.type] || ck.type || '';
      tr.insertCell().textContent = ck.provider || '';
      tr.insertCell().textContent = ck.description || '—';
      tr.insertCell().textContent = ck.duration || '';
      tr.insertCell().textContent = HOW_LABELS[ck.how] || 'Script';
      var add = el('button', 'Toevoegen', 'button button-small cm-scan-add-one');
      add.type = 'button';
      add.setAttribute('data-i', String(i));
      tr.insertCell().appendChild(add);
    });
    result.appendChild(table);
    result.appendChild(el('p', 'HTTP-header: gezet door de server. Script: afgeleid uit trackingscripts (de browser zet de cookie). Embed: gezet door een ingesloten dienst (bijv. een video). Browser: gevonden in uw browser tijdens de browserscan. Opslag: localStorage of sessionStorage. Extern script: afgeleid uit een geladen dienst.', 'description'));
  }

  /* Serialiseer alle cm_scan_add-aanroepen achter elkaar: de server doet
   * read-modify-write op de opgeslagen lijst, dus twee gelijktijdige
   * "Toevoegen"-verzoeken kunnen elkaars toevoeging overschrijven. */
  var addQueue = Promise.resolve();
  function addCookies(list, buttons) {
    buttons.forEach(function (b) { b.disabled = true; });
    var job = addQueue.then(function () {
      return post('cm_scan_add', { nonce: cfg.nonces.scanAdd, cookies: JSON.stringify(list) });
    }).then(function (r) {
      if (!r || !r.success) throw new Error('mislukt');
      if (addStatus) { addStatus.remove(); addStatus = null; } // oude foutmelding weg na een geslaagde poging
      var added = {};
      (r.data.added || []).forEach(function (n) { added[n] = true; });
      buttons.forEach(function (b) {
        var ck = found[+b.getAttribute('data-i')];
        b.textContent = ck && added[ck.name] ? 'Toegevoegd' : 'Staat al in de lijst';
      });
      return (r.data.added || []).length;
    }).catch(function () {
      buttons.forEach(function (b) { b.disabled = false; });
      showAddStatus('Toevoegen is mislukt. Probeer het opnieuw.', 'notice notice-error inline');
      return -1;
    });
    addQueue = job.catch(function () {}); // houd de wachtrij levend na een mislukking
    return job;
  }

  result.addEventListener('click', function (e) {
    var one = e.target.closest('.cm-scan-add-one');
    if (one) { addCookies([found[+one.getAttribute('data-i')]], [one]); return; }
    var all = e.target.closest('#cm-scan-add-all');
    if (!all) return;
    all.disabled = true;
    var buttons = Array.prototype.slice.call(result.querySelectorAll('.cm-scan-add-one:not([disabled])'));
    addCookies(buttons.map(function (b) { return found[+b.getAttribute('data-i')]; }), buttons).then(function (n) {
      if (n < 0) { all.disabled = false; return; } // addCookies toont de foutmelding al via het statuselement
      var msg = n > 0 ? ' ' + n + ' cookies toegevoegd.' : ' Alle cookies staan al in de lijst.';
      all.parentNode.appendChild(el('span', msg, 'description'));
    });
  });

  scanBtn.addEventListener('click', function () {
    scanBtn.disabled = true;
    if (bscanBtn) bscanBtn.disabled = true; // nooit twee scans tegelijk in dezelfde resultatentabel
    found = [];
    addStatus = null;
    result.textContent = '';
    result.className = '';
    var label = el('p', 'Pagina’s ophalen…');
    var progress = el('progress');
    progress.max = 100;
    progress.value = 0;
    result.appendChild(label);
    result.appendChild(progress);

    post('cm_scan_urls', { nonce: cfg.nonces.scan }).then(function (r) {
      if (!r || !r.success) {
        var e = new Error((r && r.data && r.data.msg) || 'De pagina’s konden niet worden opgehaald.');
        e.cmKnown = true; // eigen melding van de server, geen JS-fout — zie de outer .catch
        throw e;
      }
      var urls = r.data.urls || [];
      var batches = [];
      for (var i = 0; i < urls.length; i += 5) batches.push(urls.slice(i, i + 5));
      var seen = {};
      var done = 0;
      var failedPages = 0;
      function next(idx) {
        if (idx >= batches.length) return Promise.resolve();
        return post('cm_scan_batch', { nonce: cfg.nonces.scan, urls: batches[idx] }).then(function (b) {
          if (b && b.success) {
            (b.data.cookies || []).forEach(function (c) {
              if (!seen[c.name]) { seen[c.name] = true; found.push(c); }
            });
          } else {
            failedPages += batches[idx].length;
          }
        }).catch(function () {
          failedPages += batches[idx].length; // batch mislukt: overslaan en doorgaan
        }).then(function () {
          done += batches[idx].length;
          progress.value = Math.round((idx + 1) / batches.length * 100);
          label.textContent = done + ' van ' + urls.length + ' pagina’s gescand…';
          return next(idx + 1);
        });
      }
      return next(0).then(function () { renderResults(urls.length, failedPages); });
    }).catch(function (err) {
      notice(result, 'error', err && err.cmKnown ? err.message : 'De scan is mislukt.');
    }).then(function () { scanBtn.disabled = false; if (bscanBtn) bscanBtn.disabled = false; });
  });

  /* ---- Browserscan (3.1): pagina's in een verborgen iframe, alsof alles is
   * geaccepteerd (scanmodus, alleen voor de ingelogde beheerder). Meet wat er
   * echt gebeurt: nieuwe cookies en opslag, en welke externe adressen laden. */
  var bscanBtn = document.getElementById('cm-bscan-start');

  function cookieNames() {
    var out = {};
    document.cookie.split(';').forEach(function (p) {
      var n = p.split('=')[0].trim();
      if (n) out[n] = true;
    });
    return out;
  }
  function storageKeys(store) {
    var out = {};
    try { for (var i = 0; i < store.length; i++) out[store.key(i)] = true; } catch (e) {}
    return out;
  }
  function newKeys(before, after) {
    return Object.keys(after).filter(function (k) { return !before[k]; });
  }
  function scanUrl(url) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + 'cm_browser_scan=' + encodeURIComponent(cfg.browserScan);
  }
  /** Eén pagina in een verborgen iframe; geeft de geladen adressen terug, of null als het niet lukte. */
  function loadInFrame(url, holder) {
    return new Promise(function (resolve) {
      var frame = document.createElement('iframe');
      frame.className = 'cm-bscan-frame';
      frame.setAttribute('aria-hidden', 'true');
      frame.tabIndex = -1;
      var done = false;
      function finish() {
        if (done) return;
        done = true;
        var list = null;
        try {
          list = frame.contentWindow.performance.getEntriesByType('resource').map(function (e) {
            return String(e.name).split(/[?#]/)[0];
          });
        } catch (e) { list = null; } // bijv. X-Frame-Options of een doorverwijzing naar een ander domein
        frame.remove();
        resolve(list);
      }
      frame.addEventListener('load', function () {
        // Door de pagina scrollen, zodat lazy-loaded embeds en afbeeldingen ook laden; dan tijd voor async tags (GTM)
        var steps = 0;
        (function step() {
          try { frame.contentWindow.scrollBy(0, 800); } catch (e) {}
          if (++steps < 12) setTimeout(step, 200); else setTimeout(finish, 3500);
        })();
      });
      setTimeout(finish, 25000);
      frame.src = scanUrl(url);
      holder.appendChild(frame);
    });
  }
  /** Cookie van deze site weer weghalen (alle domeinvarianten); die van derden kan JS niet bereiken. */
  function clearCookie(name) {
    var parts = location.hostname.split('.');
    var past = '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (location.protocol === 'https:' ? '; secure' : ''); // secure: ook __Host-/__Secure-
    document.cookie = name + past;
    for (var i = 0; i < parts.length - 1; i++) document.cookie = name + past + '; domain=.' + parts.slice(i).join('.');
  }

  if (bscanBtn) bscanBtn.addEventListener('click', function () {
    if (!cfg.browserScan) return;
    bscanBtn.disabled = true;
    scanBtn.disabled = true;
    found = [];
    addStatus = null;
    result.textContent = '';
    result.className = '';
    var label = el('p', 'Pagina’s ophalen…');
    var progress = el('progress');
    progress.max = 100;
    progress.value = 0;
    result.appendChild(label);
    result.appendChild(progress);
    var holder = el('div', null, 'cm-bscan-holder');
    document.body.appendChild(holder);

    var beforeC = cookieNames();
    var beforeL = storageKeys(window.localStorage);
    var beforeS = storageKeys(window.sessionStorage);
    var resources = {};
    var failed = 0;
    var total = 0;

    post('cm_scan_urls', { nonce: cfg.nonces.scan }).then(function (r) {
      if (!r || !r.success) {
        var e = new Error((r && r.data && r.data.msg) || 'De pagina’s konden niet worden opgehaald.');
        e.cmKnown = true;
        throw e;
      }
      var urls = r.data.urls || [];
      if (!document.getElementById('cm-bscan-all').checked) urls = urls.slice(0, 21); // homepage + 20
      total = urls.length;
      var i = 0;
      function next() {
        if (i >= urls.length) return Promise.resolve();
        label.textContent = 'Pagina ' + (i + 1) + ' van ' + urls.length + ' laden in de browser…';
        return loadInFrame(urls[i], holder).then(function (list) {
          if (list === null) failed++;
          else list.forEach(function (u) { if (u.indexOf(location.origin + '/') !== 0) resources[u] = true; }); // alleen externe adressen
          i++;
          progress.value = Math.round(i / urls.length * 100);
          return next();
        });
      }
      return next();
    }).then(function () {
      var after = cookieNames();
      var local = newKeys(beforeL, storageKeys(window.localStorage));
      var session = newKeys(beforeS, storageKeys(window.sessionStorage));
      // Opruimen wat de scan in deze browser achterliet (van deze site); wat er al stond blijft staan
      newKeys(beforeC, after).forEach(clearCookie);
      try { local.forEach(function (k) { window.localStorage.removeItem(k); }); } catch (e) {}
      try { session.forEach(function (k) { window.sessionStorage.removeItem(k); }); } catch (e) {}
      // Nieuwe cookies altijd; cookies die er al stonden (bijv. _ga van een eerder bezoek) meldt de server alleen
      // als ze bekend zijn: onbekende komen vaak van plugins in de admin. Opslag alleen nieuw.
      var fresh = newKeys(beforeC, after);
      var existing = Object.keys(after).filter(function (n) { return fresh.indexOf(n) === -1; });
      var data = { cookies: fresh, existing: existing, storage: local.concat(session), resources: Object.keys(resources) };
      return post('cm_browser_scan_lookup', { nonce: cfg.nonces.scan, data: JSON.stringify(data) });
    }).then(function (r) {
      if (!r || !r.success) throw new Error('lookup');
      found = r.data.cookies || [];
      if (total > 0 && failed === total) {
        notice(result, 'error', 'De browserscan kon geen enkele pagina laden. Mogelijk verbiedt de website het laden in een frame (X-Frame-Options of frame-ancestors), of draait het beheer op een ander domein dan de website. Gebruik dan de gewone scan.');
        return;
      }
      renderResults(total, failed);
      (r.data.notes || []).forEach(function (t) { result.appendChild(el('p', t, 'description')); });
      if (r.data.hosts && r.data.hosts.length) {
        result.appendChild(el('p', 'Ook geladen, maar niet in de kennisbank: ' + r.data.hosts.join(', ') + '. Controleer of deze diensten cookies zetten.', 'description'));
      }
    }).catch(function (err) {
      notice(result, 'error', err && err.cmKnown ? err.message : 'De browserscan is mislukt.');
    }).then(function () {
      holder.remove();
      bscanBtn.disabled = false;
      scanBtn.disabled = false;
    });
  });
})();
