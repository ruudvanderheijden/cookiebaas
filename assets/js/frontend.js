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
    function showBanner() {
        if (!overlay || !banner) return;
        overlay.classList.add('cm-active');
        banner.classList.add('cm-active');
        if (floatEl) floatEl.classList.remove('cm-visible');
        setTimeout(function(){
            var btn = document.getElementById('cm-btn-accept');
            if (btn) btn.focus();
        }, 500);
    }

    function hideBanner() {
        if (!overlay || !banner) return;
        overlay.classList.remove('cm-active');
        banner.classList.remove('cm-active');
        if (SHOW_FLOAT && floatEl) floatEl.classList.add('cm-visible');
    }

    function openPrefs() {
        prefsOpen = true;
        if (prefsEl) prefsEl.classList.add('cm-active');
        if (overlay) overlay.classList.add('cm-active');
        banner.classList.remove('cm-active');
        // Load current state into toggles
        var consent = getConsent();
        if (consent && togAnalytics) togAnalytics.checked = !!consent.analytics;
        if (consent && togMarketing) togMarketing.checked = !!consent.marketing;
        setTimeout(function(){
            var closeBtn = document.getElementById('cm-prefs-close');
            if (closeBtn) closeBtn.focus();
        }, 400);
    }

    function closePrefs() {
        prefsOpen = false;
        if (prefsEl) prefsEl.classList.remove('cm-active');
        var consent = getConsent();
        if (!consent || isExpired(consent)) {
            showBanner();
        } else {
            if (overlay) overlay.classList.remove('cm-active');
        }
    }

    /* ---- CONSENT ACTIONS ---- */
    function acceptAll() {
        setConsent({ analytics: true, marketing: true, method: 'accept-all' });
        hideBanner();
        if (prefsEl) prefsEl.classList.remove('cm-active');
        if (overlay) overlay.classList.remove('cm-active');
        if (togAnalytics) togAnalytics.checked = true;
        if (togMarketing) togMarketing.checked = true;
        loadScripts(true, true);
        pushDataLayer(true, true, 'accept-all');
        dispatchEvent('cm_consent_accepted', { analytics: true, marketing: true });
    }

    function rejectAll() {
        setConsent({ analytics: false, marketing: false, method: 'reject-all' });
        hideBanner();
        if (prefsEl) prefsEl.classList.remove('cm-active');
        if (overlay) overlay.classList.remove('cm-active');
        if (togAnalytics) togAnalytics.checked = false;
        if (togMarketing) togMarketing.checked = false;
        loadScripts(false, false);
        pushDataLayer(false, false, 'reject-all');
        dispatchEvent('cm_consent_rejected', { analytics: false, marketing: false });
    }

    function savePrefs() {
        var analytics = togAnalytics ? togAnalytics.checked : false;
        var marketing = togMarketing ? togMarketing.checked : false;
        setConsent({ analytics: analytics, marketing: marketing, method: 'custom' });
        hideBanner();
        if (prefsEl) prefsEl.classList.remove('cm-active');
        if (overlay) overlay.classList.remove('cm-active');
        loadScripts(analytics, marketing);
        pushDataLayer(analytics, marketing, 'custom');
        dispatchEvent('cm_consent_saved', { analytics: analytics, marketing: marketing });
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
        if (btnFloat)    btnFloat.addEventListener('click', function(){ floatEl.classList.remove('cm-visible'); showBanner(); });

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

})();
