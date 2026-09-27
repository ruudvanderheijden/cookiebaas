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
      var known = ck.type === 'functional' || ck.type === 'analytics' || ck.type === 'marketing';
      var cat = tr.insertCell();
      if (known) cat.textContent = LABELS[ck.type];
      else {
        // Onbekend: zelf kiezen, anders zou hij stil als functioneel (zonder toestemming) in de lijst komen
        var sel = el('select', null, 'cm-scan-cat');
        sel.setAttribute('aria-label', 'Categorie voor ' + ck.name);
        [['', 'Onbekend: kies…'], ['functional', LABELS.functional], ['analytics', LABELS.analytics], ['marketing', LABELS.marketing]].forEach(function (o) {
          var opt = el('option', o[1]); opt.value = o[0]; sel.appendChild(opt);
        });
        cat.appendChild(sel);
      }
      tr.insertCell().textContent = ck.provider || '';
      tr.insertCell().textContent = ck.description || '—';
      tr.insertCell().textContent = ck.duration || '';
      tr.insertCell().textContent = HOW_LABELS[ck.how] || 'Script';
      var add = el('button', 'Toevoegen', 'button button-small cm-scan-add-one');
      add.type = 'button';
      add.setAttribute('data-i', String(i));
      if (!known) add.disabled = true; // pas na een categoriekeuze
      tr.insertCell().appendChild(add);
    });
    result.appendChild(table);
    result.appendChild(el('p', 'HTTP-header: gezet door de server. Script: afgeleid uit trackingscripts (de browser zet de cookie). Embed: gezet door een ingesloten dienst (bijv. een video). Browser: gevonden in uw browser tijdens de uitgebreide scan. Opslag: localStorage of sessionStorage. Extern script: afgeleid uit een geladen dienst. Onbekend: kies eerst een categorie, dan kunt u de cookie toevoegen. Functioneel alleen als de site zonder deze cookie niet werkt; twijfelt u, kies dan Marketing, dan vraagt de banner altijd toestemming.', 'description'));
  }

  result.addEventListener('change', function (e) {
    var sel = e.target.closest('.cm-scan-cat');
    if (!sel) return;
    var btn = sel.closest('tr').querySelector('.cm-scan-add-one');
    found[+btn.getAttribute('data-i')].type = sel.value || 'unknown';
    btn.disabled = !sel.value;
  });

  /** Onbekende cookies waarvoor nog geen categorie is gekozen (en die nog niet zijn toegevoegd). */
  function unchosenCount() {
    return Array.prototype.filter.call(result.querySelectorAll('.cm-scan-cat'), function (s) { return !s.disabled && !s.value; }).length;
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
        var sel = b.closest('tr').querySelector('.cm-scan-cat');
        if (sel) sel.disabled = true; // categorie ligt nu vast; aanpassen kan in de cookielijst
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
    var buttons = Array.prototype.slice.call(result.querySelectorAll('.cm-scan-add-one:not([disabled])'));
    var skipped = unchosenCount();
    var skipMsg = skipped ? ' ' + skipped + ' onbekende cookie' + (skipped === 1 ? '' : 's') + ' overgeslagen: kies eerst een categorie.' : '';
    if (!buttons.length) { showAddStatus(skipMsg.trim() || 'Alle cookies staan al in de lijst.', 'description'); return; }
    all.disabled = true;
    addCookies(buttons.map(function (b) { return found[+b.getAttribute('data-i')]; }), buttons).then(function (n) {
      if (n < 0) { all.disabled = false; return; } // addCookies toont de foutmelding al via het statuselement
      var msg = n > 0 ? ' ' + n + ' cookies toegevoegd.' : ' Alle cookies staan al in de lijst.';
      var prev = all.parentNode.querySelector('span.description');
      if (prev) prev.remove(); // bij een tweede klik niet stapelen
      all.parentNode.appendChild(el('span', msg + skipMsg, 'description'));
      if (skipped) all.disabled = false; // later alsnog de gekozen onbekende cookies in één keer toevoegen
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

  /* ---- Browserscan (3.1): pagina's in een verborgen iframe, in twee rondes.
   * Ronde 1 als nieuwe bezoeker die nog niets koos (controle vóór toestemming),
   * ronde 2 alsof alles is geaccepteerd (wat er echt laadt, voor de cookielijst).
   * Alleen voor de ingelogde beheerder; de pagina's zien hem als niet-ingelogd. */
  var bscanBtn = document.getElementById('cm-bscan-start');

  function cookieMap() {
    var out = {};
    document.cookie.split(';').forEach(function (p) {
      var i = p.indexOf('=');
      var n = (i === -1 ? p : p.slice(0, i)).trim();
      if (n) out[n] = i === -1 ? '' : p.slice(i + 1);
    });
    return out;
  }
  function storageMap(store) {
    var out = {};
    try { for (var i = 0; i < store.length; i++) { var k = store.key(i); out[k] = store.getItem(k); } } catch (e) {}
    return out;
  }
  function newKeys(before, after) {
    return Object.keys(after).filter(function (k) { return !(k in before); });
  }
  function changedKeys(before, after) {
    return Object.keys(after).filter(function (k) { return !(k in before) || before[k] !== after[k]; });
  }
  /** Echte looptijd per cookie in seconden (0 = sessie), waar de browser die geeft (cookieStore). */
  function cookieExpiries() {
    if (!window.cookieStore || !window.cookieStore.getAll) return Promise.resolve({});
    return window.cookieStore.getAll().then(function (list) {
      var out = {};
      list.forEach(function (c) { out[c.name] = c.expires ? Math.max(0, Math.round((c.expires - Date.now()) / 1000)) : 0; });
      return out;
    }).catch(function () { return {}; });
  }
  function scanUrl(url, fresh) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + 'cm_browser_scan=' + encodeURIComponent(cfg.browserScan) + (fresh ? '&cm_scan_fresh=1' : '');
  }
  /** Eén pagina in een verborgen iframe; geeft de geladen adressen terug, of null als het niet lukte. */
  function loadInFrame(url, holder, fresh) {
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
      frame.src = scanUrl(url, fresh);
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
  /** Opruimen na een ronde: nieuwe cookies en opslag weg, gewijzigde opslag terug naar de oude waarde. */
  function tidy(before, after) {
    newKeys(before.c, after.c).forEach(clearCookie);
    [['l', window.localStorage], ['s', window.sessionStorage]].forEach(function (p) {
      try {
        changedKeys(before[p[0]], after[p[0]]).forEach(function (k) {
          if (k in before[p[0]]) p[1].setItem(k, before[p[0]][k]); else p[1].removeItem(k);
        });
      } catch (e) {}
    });
  }
  function snapshot() {
    return { c: cookieMap(), l: storageMap(window.localStorage), s: storageMap(window.sessionStorage) };
  }

  function renderPreconsent(pre) {
    var wrap = el('div');
    wrap.appendChild(el('h3', 'Vóór toestemming'));
    var box = el('div');
    var items = pre.items || [];
    var count = function (n, one, more) { return n + ' ' + (n === 1 ? one : more); };
    if (pre.errors) notice(box, 'error', 'Vóór toestemming gebeurt er ' + count(pre.errors, 'ding', 'dingen') + ' waarvoor toestemming nodig is.');
    else if (pre.warnings) notice(box, 'warning', 'Vóór toestemming laadt of plaatst de site ' + count(pre.warnings, 'onbekende cookie of dienst', 'onbekende cookies of diensten') + '. Controleer of dat strikt noodzakelijk is.');
    else notice(box, 'success', 'Vóór toestemming plaatst of laadt de site niets waarvoor toestemming nodig is.');
    if (items.length) {
      var ul = el('ul', null, 'cm-preconsent');
      var LEVEL = { error: 'Toestemming nodig', warn: 'Controleren', info: 'Ter info' };
      items.forEach(function (it) {
        var li = el('li', null, 'cm-preconsent-' + it.level);
        li.appendChild(el('strong', (LEVEL[it.level] || '') + ': '));
        li.appendChild(el('code', it.name));
        li.appendChild(document.createTextNode(' ' + it.text));
        if (it.block) {
          var b = el('button', 'Blokkeren', 'button button-small cm-block-host');
          b.type = 'button';
          b.setAttribute('data-host', it.name);
          b.setAttribute('data-cat', it.block);
          li.appendChild(document.createTextNode(' '));
          li.appendChild(b);
        }
        ul.appendChild(li);
      });
      box.appendChild(ul);
    }
    if (pre.errors || pre.warnings) {
      box.appendChild(el('p', 'Blokkeren zet de host bij Blokkering › Patronen: scripts van die host laden dan pas na toestemming. Komt het via Google Tag Manager? Laat de tag dan vuren op het event cm_consent_update (zie Blokkering › Google). Een cookie zonder host komt van een script op uw eigen site: zet een stukje van dat script bij de patronen. Draai de scan daarna opnieuw.'));
    }
    wrap.appendChild(box);
    wrap.appendChild(el('h3', 'Na toestemming'));
    return wrap;
  }

  result.addEventListener('click', function (e) {
    var b = e.target.closest('.cm-block-host');
    if (!b) return;
    b.disabled = true;
    post('cm_block_host', { nonce: cfg.nonces.scan, host: b.getAttribute('data-host'), category: b.getAttribute('data-cat') }).then(function (r) {
      if (!r || !r.success) { b.textContent = 'Mislukt'; return; }
      b.textContent = 'Geblokkeerd';
      // Werd hij al geblokkeerd, dan komt hij langs een andere weg binnen
      if (r.data && r.data.already) b.parentNode.appendChild(el('span', ' Deze host stond al in de blokkering: hij laadt waarschijnlijk via Google Tag Manager of een iframe.', 'description'));
    }).catch(function () { b.textContent = 'Mislukt'; });
  });

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

    var urls = [];
    var pre = {};
    var failedPre = 0;
    var failed = 0;

    function round(fresh, text, offset) {
      var resources = {};
      var bad = 0;
      var i = 0;
      function next() {
        if (i >= urls.length) return Promise.resolve({ resources: resources, failed: bad });
        label.textContent = text + ': pagina ' + (i + 1) + ' van ' + urls.length + '…';
        return loadInFrame(urls[i], holder, fresh).then(function (list) {
          if (list === null) bad++;
          else list.forEach(function (u) { if (u.indexOf(location.origin + '/') !== 0) resources[u] = true; }); // alleen externe adressen
          i++;
          progress.value = Math.round((offset + i) / (urls.length * 2) * 100);
          return next();
        });
      }
      return next();
    }

    post('cm_scan_urls', { nonce: cfg.nonces.scan }).then(function (r) {
      if (!r || !r.success) {
        var e = new Error((r && r.data && r.data.msg) || 'De pagina’s konden niet worden opgehaald.');
        e.cmKnown = true;
        throw e;
      }
      urls = r.data.urls || [];
      if (!document.getElementById('cm-bscan-all').checked) urls = urls.slice(0, 21); // homepage + 20
      var before = snapshot();
      return round(true, 'Ronde 1 van 2, als nieuwe bezoeker', 0).then(function (res) {
        var after = snapshot();
        // Nieuw of gewijzigd: een tracker die al een cookie had (bijv. _ga_ van een eerder bezoek) werkt die bij
        pre = { cookies: changedKeys(before.c, after.c), local: changedKeys(before.l, after.l), session: changedKeys(before.s, after.s), resources: Object.keys(res.resources) };
        failedPre = res.failed;
        tidy(before, after);
      });
    }).then(function () {
      var before = snapshot();
      return round(false, 'Ronde 2 van 2, alles geaccepteerd', urls.length).then(function (res) {
        failed = res.failed;
        return cookieExpiries().then(function (durations) {
          var after = snapshot();
          var fresh = newKeys(before.c, after.c);
          // Cookies die er al stonden meldt de server alleen als ze bekend zijn: onbekende komen vaak van plugins in de admin
          var data = {
            cookies: fresh,
            existing: Object.keys(after.c).filter(function (n) { return fresh.indexOf(n) === -1; }),
            local: newKeys(before.l, after.l),
            session: newKeys(before.s, after.s),
            durations: durations,
            resources: Object.keys(res.resources),
            pre: pre,
            pages: urls.length - failedPre
          };
          tidy(before, after);
          return post('cm_browser_scan_lookup', { nonce: cfg.nonces.scan, data: JSON.stringify(data) });
        });
      });
    }).then(function (r) {
      if (!r || !r.success) throw new Error('lookup');
      found = r.data.cookies || [];
      if (urls.length > 0 && failed === urls.length) {
        notice(result, 'error', 'De uitgebreide scan kon geen enkele pagina laden. Mogelijk verbiedt de website het laden in een frame (X-Frame-Options of frame-ancestors), of draait het beheer op een ander domein dan de website. Gebruik dan de snelle scan.');
        return;
      }
      renderResults(urls.length, failed);
      if (r.data.preconsent) result.insertBefore(renderPreconsent(r.data.preconsent), result.firstChild);
      if (r.data.external && r.data.external.length) {
        result.appendChild(el('p', 'Geen cookies, wel het IP-adres van de bezoeker: ' + r.data.external.join(', ') + '. Host deze bestanden (zoals lettertypen) bij voorkeur op uw eigen website; anders horen deze ontvangers in de privacyverklaring.', 'description'));
      }
      if (r.data.hosts && r.data.hosts.length) {
        result.appendChild(el('p', 'Ook geladen, maar niet in de kennisbank: ' + r.data.hosts.join(', ') + '. Controleer of deze diensten cookies zetten.', 'description'));
      }
    }).catch(function (err) {
      notice(result, 'error', err && err.cmKnown ? err.message : 'De uitgebreide scan is mislukt.');
    }).then(function () {
      holder.remove();
      bscanBtn.disabled = false;
      scanBtn.disabled = false;
    });
  });
})();
