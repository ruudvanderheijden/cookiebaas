/* ===========================
   COOKIEMELDING — Frontend JS
   AVG/GDPR Compliant
=========================== */
(function () {
    'use strict';

    var COOKIE_NAME    = 'cc_cm_consent';
    var COOKIE_VERSION = '2.0';
    var EXPIRY_MONTHS  = (window.CM_CONFIG && CM_CONFIG.expiry_months) ? CM_CONFIG.expiry_months : 12;
    var SHOW_FLOAT     = (window.CM_CONFIG && CM_CONFIG.show_float !== undefined) ? CM_CONFIG.show_float : true;
    var SERVER_VERSION = (window.CM_CONFIG && CM_CONFIG.consent_version) ? String(CM_CONFIG.consent_version) : '1';

    /* ---- COOKIE HELPERS ---- */
    function getConsent() {
        var match = document.cookie.split('; ').find(function(r){ return r.startsWith(COOKIE_NAME + '='); });
        if (!match) return null;
        try { return JSON.parse(decodeURIComponent(match.split('=').slice(1).join('='))); }
        catch(e) { return null; }
    }

    function setConsent(data) {
        var now     = new Date();
        var expires = new Date(now.getTime() + EXPIRY_MONTHS * 30 * 24 * 60 * 60 * 1000);
        var payload = {
            version:   COOKIE_VERSION,
            sv:        SERVER_VERSION,
            timestamp: now.toISOString(),
            expires:   expires.toISOString(),
            analytics: data.analytics,
            marketing: data.marketing,
            method:    data.method || 'explicit'
        };
        document.cookie = COOKIE_NAME + '=' + encodeURIComponent(JSON.stringify(payload))
            + '; expires=' + expires.toUTCString()
            + '; path=/; SameSite=Lax';
        return payload;
    }

    function isExpired(consent) {
        if (!consent || !consent.expires) return true;
        return new Date() > new Date(consent.expires);
    }

    /* ---- ELEMENTS ---- */
    var overlay   = document.getElementById('cm-overlay');
    var banner    = document.getElementById('cm-banner');
    var prefsEl   = document.getElementById('cm-prefs');
    var floatEl   = document.getElementById('cm-float');
    var togAnalytics = document.getElementById('cm-toggle-analytics');
    var togMarketing = document.getElementById('cm-toggle-marketing');

    var prefsOpen = false;

    /* ---- SHOW / HIDE ---- */
    function showOverlay() {
        if (!overlay) return;
        overlay.style.display = '';
        overlay.style.opacity = '';
        overlay.offsetHeight; // force reflow voor CSS transitie
        overlay.classList.add('cm-active');
    }

    function hideAll() {
        if (banner)  banner.classList.remove('cm-active');
        if (prefsEl) prefsEl.classList.remove('cm-active');
        if (overlay) {
            overlay.classList.remove('cm-active');
            overlay.style.opacity = '0';
            setTimeout(function() {
                overlay.style.display = 'none';
                overlay.style.opacity = '';
            }, 400);
        }
        if (SHOW_FLOAT && floatEl) floatEl.classList.add('cm-visible');
    }

    function showBanner() {
        if (!overlay || !banner) return;
        showOverlay();
        banner.classList.add('cm-active');
        if (floatEl) floatEl.classList.remove('cm-visible');
        setTimeout(function(){
            // Zet focus direct op Akkoord — eerste element in gewenste tabvolgorde
            var acceptBtn = document.getElementById('cm-btn-accept');
            if (acceptBtn && acceptBtn.offsetParent !== null) {
                acceptBtn.focus();
            } else {
                banner.focus();
            }
        }, 500);
    }

    function openPrefs() {
        prefsOpen = true;
        showOverlay();
        if (prefsEl) prefsEl.classList.add('cm-active');
        banner.classList.remove('cm-active');
        var consent = getConsent();
        if (consent && togAnalytics) togAnalytics.checked = !!consent.analytics;
        if (consent && togMarketing) togMarketing.checked = !!consent.marketing;
        setTimeout(function(){
            // WCAG: focus op eerste focusbaar element in popup (sluitknop = logische start)
            var focusable = Array.from(prefsEl.querySelectorAll(
                'button:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(function(el){ return el.offsetParent !== null; });
            if (focusable.length) {
                focusable[0].focus();
            }
        }, 400);
    }

    function closePrefs() {
        prefsOpen = false;
        if (prefsEl) prefsEl.classList.remove('cm-active');
        var consent = getConsent();
        if (!consent || isExpired(consent)) {
            showBanner();
        } else {
            hideAll();
        }
    }

    /* ---- CONSENT ACTIONS ---- */
    function acceptAll() {
        setConsent({ analytics: true, marketing: true, method: 'accept-all' });
        dispatchEvent('cm_consent_accepted', { analytics: true, marketing: true });
        hideAll();
        // Herlaad pagina zodat geblokkeerde scripts alsnog laden en overlay zeker weg is
        setTimeout(function() { window.location.reload(); }, 300);
    }

    function rejectAll() {
        setConsent({ analytics: false, marketing: false, method: 'reject-all' });
        dispatchEvent('cm_consent_rejected', { analytics: false, marketing: false });
        hideAll();
        setTimeout(function() { window.location.reload(); }, 300);
    }

    function savePrefs() {
        var analytics = togAnalytics ? togAnalytics.checked : false;
        var marketing = togMarketing ? togMarketing.checked : false;
        setConsent({ analytics: analytics, marketing: marketing, method: 'custom' });
        dispatchEvent('cm_consent_saved', { analytics: analytics, marketing: marketing });
        hideAll();
        setTimeout(function() { window.location.reload(); }, 300);
    }

        /* ---- CONDITIONAL SCRIPT LOADING ---- */
    function loadScripts(analytics, marketing) {
        // Dispatch events so theme/other plugins can hook in
        if (analytics) {
            // GA4 — uncomment and add your own ID:
            // loadScript('https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX');
            document.dispatchEvent(new CustomEvent('cm_analytics_enabled'));
        }
        if (marketing) {
            // Facebook Pixel etc.
            document.dispatchEvent(new CustomEvent('cm_marketing_enabled'));
        }
    }

    function loadScript(src) {
        if (document.querySelector('script[src="' + src + '"]')) return;
        var s = document.createElement('script');
        s.src = src;
        s.async = true;
        document.head.appendChild(s);
    }

    function dispatchEvent(name, detail) {
        try { document.dispatchEvent(new CustomEvent(name, { detail: detail })); } catch(e) {}
    }

    /* ---- GTM DATALAYER PUSH ---- */
    // Pusht een gestandaardiseerd event naar de GTM dataLayer zodat alle tags
    // (ook niet-Google, zoals Meta Pixel) conditioneel kunnen worden afgevuurd.
    //
    // Gebruik in GTM:
    //   Trigger type : Custom Event — Event name: cm_consent_update
    //   Variable     : Data Layer Variable — Variable Name: cm_analytics  (of cm_marketing)
    //   Conditie     : cm_analytics equals true
    function pushDataLayer(analytics, marketing, method) {
        var adConsent = marketing ? 'granted' : 'denied';
        var analyticsConsent = analytics ? 'granted' : 'denied';

        // 1. GTM dataLayer — Google Consent Mode v2 + custom cm_ variabelen
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event          : 'cm_consent_update',
            cm_analytics   : analytics,
            cm_marketing   : marketing,
            cm_method      : method || 'unknown',
            analytics_storage      : analyticsConsent,
            ad_storage             : adConsent,
            ad_user_data           : adConsent,
            ad_personalization     : adConsent,
            functionality_storage  : 'granted',
            security_storage       : 'granted'
        });

        // 2. Microsoft UET Consent Mode
        // Werkt voor de UET-tag die direct op de site staat (buiten GTM)
        // Zie: https://help.ads.microsoft.com/apex/index/3/nl/60160
        window.uetq = window.uetq || [];
        window.uetq.push('consent', 'update', {
            ad_storage: adConsent
        });
    }

    /* ---- CATEGORY TOGGLES ---- */
    function toggleCat(id) {
        var el = document.getElementById('cm-cat-' + id);
        if (el) el.classList.toggle('cm-open');
    }

    /* ---- BIND EVENTS ---- */
    function bindEvents() {
        var btnPrefs   = document.getElementById('cm-btn-prefs');
        var btnReject  = document.getElementById('cm-btn-reject');
        var btnAccept  = document.getElementById('cm-btn-accept');
        var btnClose   = document.getElementById('cm-prefs-close');
        var btnAllowAll= document.getElementById('cm-allowall-btn');
        var btnRejectAll= document.getElementById('cm-rejectall-btn');
        var btnSave    = document.getElementById('cm-save-btn');
        var btnFloat   = document.getElementById('cm-float-btn');

        if (btnPrefs)    btnPrefs.addEventListener('click', openPrefs);
        if (btnReject)   btnReject.addEventListener('click', rejectAll);
        if (btnAccept)   btnAccept.addEventListener('click', acceptAll);
        if (btnClose)    btnClose.addEventListener('click', closePrefs);
        if (btnAllowAll) btnAllowAll.addEventListener('click', acceptAll);
        if (btnRejectAll)btnRejectAll.addEventListener('click', rejectAll);
        if (btnSave)     btnSave.addEventListener('click', savePrefs);
        if (btnFloat)    btnFloat.addEventListener('click', function(){ btnFloat.blur(); floatEl.classList.remove('cm-visible'); showBanner(); });

        // Category expand/collapse
        var headers = document.querySelectorAll('.cm-cat-header');
        headers.forEach(function(h){
            h.addEventListener('click', function(){
                var cat = this.getAttribute('data-cat');
                if (cat) toggleCat(cat);
            });
        });

        // Keyboard: Escape closes prefs
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape' && prefsOpen) closePrefs();
        });

        // Focus trap voor banner — volgorde: Akkoord → Weigeren → Cookievoorkeuren → links
        if (banner) {
            function getBannerFocusOrder() {
                var accept  = document.getElementById('cm-btn-accept');
                var reject  = document.getElementById('cm-btn-reject');
                var prefs   = document.getElementById('cm-btn-prefs');
                var links   = Array.from(banner.querySelectorAll('a[href]'))
                                   .filter(function(el) { return el.offsetParent !== null; });
                var list = [];
                if (accept && accept.offsetParent !== null) list.push(accept);
                if (reject && reject.offsetParent !== null) list.push(reject);
                if (prefs  && prefs.offsetParent  !== null) list.push(prefs);
                list = list.concat(links);
                return list;
            }

            banner.addEventListener('keydown', function(e) {
                if (e.key !== 'Tab') return;
                e.preventDefault();
                var focusable = getBannerFocusOrder();
                if (!focusable.length) return;
                var idx = focusable.indexOf(document.activeElement);
                if (e.shiftKey) {
                    focusable[idx <= 0 ? focusable.length - 1 : idx - 1].focus();
                } else {
                    focusable[idx === -1 || idx >= focusable.length - 1 ? 0 : idx + 1].focus();
                }
            });
        }

        // Focus trap voor prefs popup — Tab blijft binnen het popup
        if (prefsEl) {
            prefsEl.addEventListener('keydown', function(e) {
                if (e.key !== 'Tab' || !prefsOpen) return;
                var focusable = Array.from(prefsEl.querySelectorAll(
                    'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
                )).filter(function(el) { return el.offsetParent !== null; });
                if (!focusable.length) return;
                var first = focusable[0];
                var last  = focusable[focusable.length - 1];
                if (e.shiftKey) {
                    if (document.activeElement === first) { e.preventDefault(); last.focus(); }
                } else {
                    if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
                }
            });
        }

        // Enter/Space activeren categorie-headers (role=button) in prefs
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var target = document.activeElement;
            if (target && target.classList.contains('cm-cat-header')) {
                e.preventDefault();
                target.click();
            }
        });
    }

    /* ---- INIT ---- */
    function init() {
        bindEvents();
        var consent = getConsent();
        // Toon banner als: geen consent, verlopen, of server-versie is verhoogd (admin heeft "data wissen" gebruikt)
        var serverVer = SERVER_VERSION;
        var consentVer = consent ? String(consent.sv || '1') : null;
        if (!consent || isExpired(consent) || (consentVer !== serverVer)) {
            setTimeout(showBanner, 600);
        } else {
            loadScripts(consent.analytics, consent.marketing);
            // Bestaande consent doorgeven aan GTM bij pagina-load (terugkerend bezoek)
            pushDataLayer(!!consent.analytics, !!consent.marketing, consent.method || 'returning');
            if (SHOW_FLOAT && floatEl) floatEl.classList.add('cm-visible');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Touch: verwijder focus na tap zodat geen focus-ring zichtbaar is op mobile
    document.addEventListener('touchend', function(e) {
        var el = e.target && e.target.closest ? e.target.closest('.cm-btn, .cm-btn-ghost, .cm-btn-outline, .cm-allow-all') : null;
        if (el) setTimeout(function() { el.blur(); }, 100);
    }, { passive: true });

})();
