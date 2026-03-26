/* ============================================================
   COOKIEMELDING — Admin JS  |  WAKKR  |  v1.0.0
============================================================ */
(function($){
    'use strict';

    /* ---- TABS ---- */
    var previewTabs = ['kleuren', 'teksten']; // alleen deze tabs tonen de preview
    function cmTogglePreview(tab) {
        var $prev = $('.cm-preview-col');
        if ($prev.length) {
            if (previewTabs.indexOf(tab) !== -1) {
                $prev.show();
            } else {
                $prev.hide();
            }
        }
    }
    $(document).on('click', '.cm-nav-tabs .nav-tab', function(e){
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.cm-nav-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.cm-tab-pane').removeClass('active');
        $('#cm-pane-' + tab).addClass('active');
        cmTogglePreview(tab);
        // Update URL hash zodat links vanuit andere pagina's direct op het juiste tabje uitkomen
        if (history.replaceState) history.replaceState(null, '', '#tab=' + tab);
    });

    // Activeer tab op basis van URL hash bij pageload (bijv. vanuit compliance check)
    (function() {
        var hash = window.location.hash;
        var m = hash.match(/^#tab=(.+)$/);
        if (m) {
            var tab = m[1];
            var $t = $('.cm-nav-tabs .nav-tab[data-tab="' + tab + '"]');
            if ($t.length) {
                $('.cm-nav-tabs .nav-tab').removeClass('nav-tab-active');
                $t.addClass('nav-tab-active');
                $('.cm-tab-pane').removeClass('active');
                $('#cm-pane-' + tab).addClass('active');
                cmTogglePreview(tab);
            }
        }
    })();

    /* ---- COLOR PICKERS ↔ HEX (altijd HEX, nooit RGB) ---- */
    $(document).on('input', '.cm-color-picker', function(){
        var hex = $(this).val();
        $('[data-for="' + $(this).attr('name') + '"]').val(hex).removeClass('cm-hex-disabled');
        applyPreview();
    });
    $(document).on('input change', '.cm-hex-input', function(){
        var hex = $(this).val().trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
            var name = $(this).data('for');
            $('#' + name).val(hex).prop('disabled', false);
            applyPreview();
        }
    });

    /* ---- OPTIONELE BORDER TOGGLE ---- */
    $(document).on('change', '.cm-enable-color', function(){
        var target = $(this).data('target');
        var $picker = $('#' + target);
        var $hex    = $('[data-for="' + target + '"]');
        if ($(this).is(':checked')) {
            $picker.prop('disabled', false);
            $hex.removeClass('cm-hex-disabled').val($picker.val());
        } else {
            $picker.prop('disabled', true);
            $hex.addClass('cm-hex-disabled').val('');
        }
        applyPreview();
    });

    /* ---- RADIUS: slider ↔ cijferinvoer ---- */
    // Slider (data-for-number) → number input
    $(document).on('input', '.cm-range[data-for-number]', function(){
        var target = $(this).data('for-number');
        $('#' + target).val($(this).val());
        applyPreview();
    });
    // Number input → slider
    $(document).on('input', '.cm-number-input', function(){
        var name = $(this).attr('name');
        $('[data-for-number="' + name + '"]').val($(this).val());
        applyPreview();
    });

    /* ---- GEWONE RANGE SLIDERS (overlay, expiry) ---- */
    $(document).on('input', '.cm-range:not([data-for-number])', function(){
        var unit = $(this).data('unit') || '';
        $('#val_' + $(this).attr('name')).text($(this).val() + unit);
        applyPreview();
    });

    /* ---- TEXT INPUTS → preview ---- */
    $(document).on('input', 'input[type="text"], textarea', function(){ applyPreview(); });

    /* ---- PREVIEW: categorie uitklap + toggle ---- */
    $(document).on('click', '.cm-prev-cat-toggle', function(e) {
        // Niet uitklappen als men op de toggle-label klikt
        if ($(e.target).closest('.cm-toggle').length) return;
        var catId = $(this).data('prev-cat');
        var $cat = $('#' + catId);
        $cat.toggleClass('cm-open');
        // Herschaal na animatie
        setTimeout(scalePreview, 400);
    });

    /* ---- HELPERS ---- */
    function get(name) {
        var $el = $('[name="' + name + '"]');
        if (!$el.length) return '';
        if ($el.is(':checkbox')) return $el.is(':checked') ? 1 : 0;
        if ($el.is('input[type="range"]')) return parseInt($el.val());
        return $el.val() || '';
    }

    // Taalafhankelijke get: leest _en variant als EN-tabblad actief is
    var _activeLang = 'nl';
    function getT(name) {
        if (_activeLang !== 'nl') {
            var enVal = get(name + '_' + _activeLang);
            if (enVal !== '') return enVal;
        }
        return get(name);
    }

    function getColor(name) {
        // Als het een optionele kleur is en uitgeschakeld, return ''
        var $hex = $('[data-for="' + name + '"]');
        if ($hex.hasClass('cm-hex-disabled')) return '';
        var $picker = $('#' + name);
        return $picker.val() || '';
    }
    function stripTags(html) {
        var d = document.createElement('div'); d.innerHTML = html; return d.textContent || d.innerText || '';
    }

    /* ---- LIVE PREVIEW — schaling ---- */
    function scalePreview() {
        // Banner
        var $bvp = $('#cm-prev-banner-vp');
        var $bwrap = $('#cm-prev-banner-wrap');
        if ($bvp.length && $bwrap.length) {
            var vpW = $bvp.width();
            var srcW = 760;
            var s = Math.min(vpW / srcW, 1);
            $bwrap.css('transform', 'scale(' + s + ')');
            // Stel viewport hoogte in op geschaalde hoogte
            $bvp.css('height', ($bwrap[0].scrollHeight * s) + 'px');
        }
        // Prefs
        var $pvp = $('#cm-prev-prefs-vp');
        var $pwrap = $('#cm-prev-prefs-wrap');
        if ($pvp.length && $pwrap.length) {
            var vpW2 = $pvp.width();
            var srcW2 = 620;
            var s2 = Math.min(vpW2 / srcW2, 1);
            $pwrap.css('transform', 'scale(' + s2 + ')');
            $pvp.css('height', ($pwrap[0].scrollHeight * s2) + 'px');
        }
    }

    // Schaal bij laden en resize
    $(window).on('load resize', function(){ setTimeout(scalePreview, 50); });

    /* ---- LIVE PREVIEW ---- */
    function applyPreview() {
        // Bepaal actief thema
        var isDark = $('#cm-color-theme-value').val() === 'dark';
        var p = isDark ? 'dm_' : 'color_';

        function gc(key) { return getColor(p + key) || getColor('color_' + key) || ''; }

        var popupBg       = gc('popup_bg')         || (isDark ? '#1a1a1a' : '#ffffff');
        var titleClr      = gc('title')             || (isDark ? '#f2f2f2' : '#111111');
        var bodyClr       = gc('body')              || (isDark ? '#a8a8a8' : '#444444');
        var acceptBg      = gc('accept_bg')         || (isDark ? '#f2f2f2' : '#111111');
        var acceptTxt     = gc('accept_text')       || (isDark ? '#111111' : '#ffffff');
        var acceptHoverBg = gc('accept_hover_bg')   || (isDark ? '#6eb8ff' : '#0091ff');
        var acceptHoverTxt= gc('accept_hover_text') || (isDark ? '#111111' : '#ffffff');
        var rejectBg      = gc('reject_bg')         || (isDark ? '#f2f2f2' : '#111111');
        var rejectTxt     = gc('reject_text')       || (isDark ? '#111111' : '#ffffff');
        var rejectBdr     = gc('reject_border')     || 'transparent';
        var acceptBdr     = gc('accept_border')     || 'transparent';
        var prefsBdr      = gc('prefs_border')      || (isDark ? '#3a3a3a' : '#d5d0c8');
        var prefsTxt      = gc('prefs_text')        || (isDark ? '#aaaaaa' : '#555555');
        var allowBg       = gc('allowall_bg')       || (isDark ? '#6eb8ff' : '#111111');
        var allowTxt      = gc('allowall_text')     || (isDark ? '#111111' : '#ffffff');
        var allowHoverBg  = gc('allowall_hover_bg') || (isDark ? '#ffffff' : '#0091ff');
        var allowBdr      = gc('allowall_border')   || 'transparent';
        var closeBg       = gc('close_bg')          || (isDark ? '#2a2a2a' : '#f0ede8');
        var closeHoverBg  = gc('close_hover_bg')    || (isDark ? '#f2f2f2' : '#111111');
        var closeIcon     = gc('close_icon')        || (isDark ? '#888888' : '#555555');
        var toggleOn      = gc('toggle_on')         || (isDark ? '#6eb8ff' : '#0091ff');
        var alwaysBg      = gc('always_bg')         || (isDark ? '#0c2a45' : '#e8f4ff');
        var catBorder     = gc('cat_border')        || (isDark ? '#2e2e2e' : '#e8e4de');

        var rp_id = isDark ? 'dm_radius_popup' : 'radius_popup';
        var rb_id = isDark ? 'dm_radius_btn'   : 'radius_btn';
        var op_id = isDark ? 'dm_overlay_opacity' : 'overlay_opacity';
        var popupR   = parseInt($('#' + rp_id).val());   if (isNaN(popupR))   popupR = isDark ? 18 : 18;
        var btnR     = parseInt($('#' + rb_id).val());   if (isNaN(btnR))     btnR   = isDark ? 6 : 6;
        var overlayOp= parseInt($('[name="' + op_id + '"]').val()); if (isNaN(overlayOp)) overlayOp = isDark ? 75 : 19;

        var rejectHoverBg  = gc('reject_hover_bg')  || (isDark ? '#6eb8ff' : '#e8e4de');
        var rejectHoverTxt = gc('reject_hover_text') || (isDark ? '#111111' : '#111111');

        // Zet CSS custom properties op beide viewports — identiek aan frontend
        var vars = {
            '--cm-popup-bg': popupBg,
            '--cm-popup-radius': popupR + 'px',
            '--cm-btn-radius': btnR + 'px',
            '--cm-overlay-alpha': (overlayOp / 100),
            '--cm-title-color': titleClr,
            '--cm-body-color': bodyClr,
            '--cm-accept-bg': acceptBg,
            '--cm-accept-text': acceptTxt,
            '--cm-accept-border': acceptBdr,
            '--cm-accept-hover-bg': acceptHoverBg,
            '--cm-accept-hover-text': acceptHoverTxt,
            '--cm-reject-bg': rejectBg,
            '--cm-reject-text': rejectTxt,
            '--cm-reject-border': rejectBdr,
            '--cm-reject-hover-bg': rejectHoverBg,
            '--cm-reject-hover-text': rejectHoverTxt,
            '--cm-prefs-border': prefsBdr,
            '--cm-prefs-text': prefsTxt,
            '--cm-allowall-bg': allowBg,
            '--cm-allowall-text': allowTxt,
            '--cm-allowall-hover-bg': allowHoverBg,
            '--cm-allowall-border': allowBdr,
            '--cm-close-bg': closeBg,
            '--cm-close-hover-bg': closeHoverBg,
            '--cm-close-icon': closeIcon,
            '--cm-toggle-on': toggleOn,
            '--cm-always-bg': alwaysBg,
            '--cm-cat-border': catBorder,
            '--cm-expand-bg': gc('expand_bg') || (isDark ? '#2a2a2a' : '#f0ede8'),
            '--cm-expand-icon': gc('expand_icon') || (isDark ? '#aaaaaa' : '#666666'),
            '--cm-expand-open-bg': gc('expand_open_bg') || (isDark ? '#6eb8ff' : '#111111'),
            '--cm-expand-open-icon': gc('expand_open_icon') || (isDark ? '#111111' : '#ffffff'),
            '--cm-cookie-item-bg': gc('cookie_item_bg') || (isDark ? '#1e1e1e' : '#ffffff'),
            '--cm-service-bg': gc('service_bg') || (isDark ? '#252525' : '#f6f4f1'),
            '--cm-allowall-hover-text': gc('allowall_hover_text') || (isDark ? '#111111' : '#ffffff'),
            '--cm-outline-border': gc('outline_border') || (isDark ? '#3a3a3a' : '#d5d0c8'),
            '--cm-outline-text': gc('outline_text') || (isDark ? '#ffffff' : '#555555'),
            '--cm-outline-hover-border': gc('outline_hover_border') || (isDark ? '#888888' : '#999999'),
            '--cm-outline-hover-text': gc('outline_hover_text') || (isDark ? '#f2f2f2' : '#111111'),
            '--cm-outline-hover-bg': gc('outline_hover_bg') || 'transparent',
            '--cm-prefs-hover-border': gc('prefs_hover_border') || (isDark ? '#666666' : '#999999'),
            '--cm-prefs-hover-text': gc('prefs_hover_text') || (isDark ? '#f2f2f2' : '#111111'),
            '--cm-cat-header-hover': gc('cat_header_hover') || (isDark ? '#383838' : 'rgb(250 252 255)'),
            '--cm-cat-desc-color': gc('cat_desc') || (isDark ? '#acacac' : '#1d2327'),
            '--cm-cat-detail-color': gc('cat_detail') || (isDark ? '#a6a6a6' : '#666666'),
            '--cm-cookie-name-color': gc('cookie_name') || (isDark ? '#acacac' : '#333333'),
            '--cm-cookie-meta-color': gc('cookie_meta') || (isDark ? '#acacac' : '#4e4e4e'),
            '--cm-toggle-off': gc('toggle_off') || (isDark ? '#6d6d6d' : '#dddddd')
        };

        var $bvp = $('#cm-prev-banner-vp');
        var $pvp = $('#cm-prev-prefs-vp');
        $.each(vars, function(k, v) {
            if ($bvp.length) $bvp[0].style.setProperty(k, v);
            if ($pvp.length) $pvp[0].style.setProperty(k, v);
        });

        // Overlay
        $('#prev-overlay').css('background', 'rgba(0,0,0,' + (overlayOp / 100) + ')');

        // Teksten
        $('#prev-title').text(getT('txt_banner_title'));
        $('#prev-text').text(stripTags(getT('txt_banner_body')));
        $('#prev-btn-prefs').text(getT('txt_btn_prefs'));
        $('#prev-btn-reject').text(getT('txt_btn_reject'));
        $('#prev-btn-accept').text(getT('txt_btn_accept'));
        $('#prev-prefs-title').text(getT('txt_prefs_title'));
        $('#prev-prefs-text').text(stripTags(getT('txt_prefs_body')));
        $('#prev-allowall').text(getT('txt_btn_allowall'));
        $('#prev-btn-rejectall').text(getT('txt_btn_rejectall'));
        $('#prev-btn-save').text(getT('txt_btn_save'));
        $('#prev-cat1-name').text(getT('txt_cat1_name'));
        $('#prev-cat2-name').text(getT('txt_cat2_name'));
        $('#prev-cat3-name').text(getT('txt_cat3_name'));
        $('#prev-cat1-short').text(getT('txt_cat1_short'));
        $('#prev-cat2-short').text(getT('txt_cat2_short'));
        $('#prev-cat3-short').text(getT('txt_cat3_short'));
        $('#prev-cat1-long').text(getT('txt_cat1_long') || (_activeLang === 'en' ? 'Functional cookies are strictly necessary for the website to work.' : 'Deze cookies zijn noodzakelijk voor het functioneren van de website.'));
        $('#prev-cat2-long').text(getT('txt_cat2_long') || (_activeLang === 'en' ? 'Analytical cookies help us understand how visitors use the website.' : 'Analytische cookies helpen ons begrijpen hoe bezoekers de website gebruiken.'));
        $('#prev-cat3-long').text(getT('txt_cat3_long') || (_activeLang === 'en' ? 'Marketing cookies are used to better tailor advertisements to your interests.' : 'Marketingcookies worden gebruikt om bezoekers te volgen over websites heen.'));

        // Hardcoded UI-strings — taalafhankelijk
        var isEn = _activeLang === 'en';
        $('#prev-section-label').text(isEn ? 'Manage cookie preferences' : 'Cookievoorkeuren beheren');
        $('#prev-always-badge').text(isEn ? 'Always active' : 'Altijd actief');
        $('#prev-cookie-purpose').text(isEn ? 'Stores your cookie preferences so you are not asked again on every visit.' : 'Slaat uw cookievoorkeuren op zodat u niet bij elk bezoek opnieuw gevraagd wordt.');
        $('#prev-cookie-duration').text((isEn ? 'Duration' : 'Looptijd') + ': ' + (isEn ? '12 months' : '12 maanden'));


        // Hover CSS injectie
        var allowHoverTxt  = gc('allowall_hover_text')  || (isDark ? '#111111' : '#ffffff');
        var prefsHoverBdr  = gc('prefs_hover_border')   || (isDark ? '#666666' : '#999999');
        var prefsHoverTxt  = gc('prefs_hover_text')     || (isDark ? '#f2f2f2' : '#111111');
        var outlineBdr     = gc('outline_border')       || (isDark ? '#3a3a3a' : '#d5d0c8');
        var outlineTxt     = gc('outline_text')         || (isDark ? '#ffffff' : '#555555');
        var outlineHoverBdr= gc('outline_hover_border') || (isDark ? '#888888' : '#999999');
        var outlineHoverTxt= gc('outline_hover_text')   || (isDark ? '#f2f2f2' : '#111111');
        var outlineHoverBg = gc('outline_hover_bg')     || 'transparent';

        $('#cm-hover-style').remove();
        $('<style id="cm-hover-style">' +
            '.cm-prev-viewport .cm-btn-accept:hover{background:' + acceptHoverBg + '!important;color:' + acceptHoverTxt + '!important}' +
            '.cm-prev-viewport .cm-btn-reject:hover{background:' + rejectHoverBg + '!important;color:' + rejectHoverTxt + '!important}' +
            '.cm-prev-viewport .cm-allow-all:hover{background:' + allowHoverBg + '!important;color:' + allowHoverTxt + '!important}' +
            '.cm-prev-viewport .cm-btn-ghost:hover{border-color:' + prefsHoverBdr + '!important;color:' + prefsHoverTxt + '!important;background:transparent!important}' +
            '.cm-prev-viewport .cm-btn-outline{border-color:' + outlineBdr + '!important;color:' + outlineTxt + '!important;background:transparent!important}' +
            '.cm-prev-viewport .cm-btn-outline:hover{border-color:' + outlineHoverBdr + '!important;color:' + outlineHoverTxt + '!important;background:' + outlineHoverBg + '!important}' +
            '.cm-prev-viewport .cm-prefs-close:hover{background:' + closeHoverBg + '!important;color:' + closeIcon + '!important}' +
        '</style>').appendTo('head');

        // Herschaal na tekstwijziging
        setTimeout(scalePreview, 10);
    }

    /* ---- PREVIEW: categorie uitklappen/inklappen ---- */

    /* ---- COLLECT ALL FIELDS ---- */
    function collectSettings() {
        var data = { action: 'cm_save_settings', nonce: CM_DATA.nonce };

        // Hidden inputs (o.a. color_theme)
        $('input[type="hidden"]').each(function(){
            var name = $(this).attr('name');
            if (name) data[name] = $(this).val();
        });

        // Text inputs & textareas
        $('input[type="text"], textarea').each(function(){
            if ($(this).attr('name')) data[$(this).attr('name')] = $(this).val();
        });

        // Number inputs (radius_popup, radius_btn)
        $('input[type="number"].cm-number-input').each(function(){
            if ($(this).attr('name')) data[$(this).attr('name')] = $(this).val();
        });

        // Regular color pickers (non-optional)
        $('.cm-color-picker:not([disabled])').each(function(){
            var name = $(this).attr('name');
            if (!name) return;
            // Check if this is an optional field
            var $toggle = $('[data-target="' + name + '"]');
            if ($toggle.length && !$toggle.is(':checked')) {
                data[name] = ''; // uitgeschakeld = geen rand
            } else {
                data[name] = $(this).val();
            }
        });
        // Also send empty string for disabled optional pickers
        $('.cm-enable-color:not(:checked)').each(function(){
            data[$(this).data('target')] = '';
        });

        // Normal ranges (non-radius)
        $('.cm-range:not([data-for-number])').each(function(){
            if ($(this).attr('name')) data[$(this).attr('name')] = $(this).val();
        });

        // Checkboxes
        $('input[type="checkbox"]').each(function(){
            if ($(this).attr('name')) data[$(this).attr('name')] = $(this).is(':checked') ? 1 : 0;
        });

        // Radio buttons — gebruik de geselecteerde waarde
        $('input[type="radio"]:checked').each(function(){
            if ($(this).attr('name')) data[$(this).attr('name')] = $(this).val();
        });

        // Select dropdowns
        $('select').each(function(){
            var name = $(this).attr('name');
            if (name && name.indexOf('[]') === -1) data[name] = $(this).val();
        });

        return data;
    }

    /* ---- Opgeslagen feedback: groene flash op save footer + vaste toast bovenin ---- */
    function cmShowFooterSaved($footer) {
        if (!$footer || !$footer.length) return;
        $footer.find('.cm-saved-msg').stop(true,true).show().css('opacity',1);
        $footer.addClass('cm-saved');
        setTimeout(function() {
            $footer.find('.cm-saved-msg').fadeOut(500);
            $footer.removeClass('cm-saved');
        }, 2000);
    }

    function cmShowSavedNotice($triggerBtn) {
        // Flash de dichtstbijzijnde save footer
        if ($triggerBtn) {
            cmShowFooterSaved($triggerBtn.closest('.cm-save-footer'));
        }
        // Vaste toast bovenin — geen layout-verschuiving
        var $n = $('#cm-notice-global');
        if (!$n.length) return;
        $n.stop(true,true)
          .css({ opacity: 0, display: 'block' })
          .animate({ opacity: 1 }, 200)
          .delay(2200)
          .fadeOut(500);
    }

    /* ---- SAVE ---- */
    function saveSettings(e) {
        var $btn  = $(e.currentTarget);
        var $btns = $('.cm-save-btn');
        $btns.prop('disabled', true).text('Opslaan...');
        $.post(CM_DATA.ajax_url, collectSettings(), function(response){
            if (response.success) {
                cmShowSavedNotice($btn);
            } else {
                alert('Fout bij opslaan.');
            }
        }).always(function(){ $btns.prop('disabled', false).text('Instellingen opslaan'); });
    }
    $(document).on('click', '.cm-save-btn', saveSettings);

    /* ---- Google integratie wissen ---- */
    $(document).on('click', '#cm-reset-google', function(){
        if (!confirm('GA4 Measurement ID en GTM Container ID wissen? Dit kan niet ongedaan worden gemaakt.')) return;
        var $btn = $(this), $status = $('#cm-reset-google-status');
        $btn.prop('disabled', true);
        $.post(CM_DATA.ajax_url, { action: 'cm_reset_google', nonce: CM_DATA.nonce }, function(r) {
            $status.css('color', r && r.success ? '#00a32a' : '#b32d2e')
                   .text(r && r.success ? '✓ Google IDs gewist.' : 'Fout bij wissen.');
            setTimeout(function(){ $status.text(''); $btn.prop('disabled', false); }, 3000);
        }).fail(function(){ $status.css('color','#b32d2e').text('Verbindingsfout.'); $btn.prop('disabled', false); });
    });

    /* ---- Embed diensten checkboxes ---- */
    function cmSyncEmbedServices() {
        var checked = [];
        $('.cm-embed-service-cb:checked').each(function() { checked.push($(this).val()); });
        // Als alle checkboxes aangevinkt zijn, sla lege string op (= alles blokkeren, standaard)
        var total = $('.cm-embed-service-cb').length;
        if (checked.length === total) {
            $('#embed_blocked_services').val('');
        } else {
            $('#embed_blocked_services').val(checked.join(','));
        }
    }
    function cmStyleEmbedCb($cb) {
        var $label = $cb.closest('label');
        if ($cb.is(':checked')) {
            $label.css({ background: '#f0faf4', borderColor: '#b7dfca' });
        } else {
            $label.css({ background: '#fafafa', borderColor: '#dcdcde' });
        }
    }
    $(document).on('change', '.cm-embed-service-cb', function() {
        cmStyleEmbedCb($(this));
        cmSyncEmbedServices();
    });
    $(document).on('click', '#cm-embed-check-all', function() {
        $('.cm-embed-service-cb').prop('checked', true).each(function() { cmStyleEmbedCb($(this)); });
        cmSyncEmbedServices();
    });
    $(document).on('click', '#cm-embed-uncheck-all', function() {
        $('.cm-embed-service-cb').prop('checked', false).each(function() { cmStyleEmbedCb($(this)); });
        cmSyncEmbedServices();
    });

    /* ---- Layout positie radio buttons: visuele highlight ---- */
    function cmHighlightRadioCards(name) {
        var val = $('input[name="' + name + '"]:checked').val();
        $('input[name="' + name + '"]').each(function() {
            var $label = $(this).closest('label');
            if ($(this).val() === val) {
                $label.css({ borderColor: '#2271b1', background: '#f0f6fb' });
            } else {
                $label.css({ borderColor: '#dcdcde', background: '#fff' });
            }
        });
    }
    $(document).on('change', 'input[name="banner_position"]', function() { cmHighlightRadioCards('banner_position'); });
    $(document).on('change', '.cm-float-style-radio', function() {
        cmHighlightRadioCards('float_btn_style');
        // Toon/verberg icoontje-opties
        if ($(this).val() === 'icon') {
            $('#cm-float-icon-options').show();
        } else {
            $('#cm-float-icon-options').hide();
        }
    });
    $(document).on('change', '.cm-float-pos-radio', function() { cmHighlightRadioCards('float_position'); });
    $(document).on('change', '.cm-float-size-radio', function() { cmHighlightRadioCards('float_icon_size'); });
    $(document).on('change', '.cm-icon-type-radio', function() {
        var val = $(this).val();
        $('.cm-icon-type-radio').each(function() {
            var $l = $(this).closest('label');
            $l.css({ borderColor: $(this).val() === val ? '#2271b1' : '#dcdcde', background: $(this).val() === val ? '#f0f6fb' : '#fff' });
        });
        if (val === 'custom') {
            $('#cm-custom-svg-area').show();
        } else {
            $('#cm-custom-svg-area').hide();
            $('#float_icon_custom_svg').val('');
        }
    });

    /* ---- Selectief resetten: checkbox wijzigt knopstatus ---- */
    var resetLabels = {
        'settings':     'Instellingen terugzetten naar standaard',
        'cookielist':   'Cookielijst leegmaken',
        'privacy':      'Privacyverklaring wissen',
        'log':          'Consent log leegmaken',
        'consent':      'Consent data wissen (alle bezoekers zien banner opnieuw)',
        'consent_data': 'Consent data wissen (alle bezoekers zien banner opnieuw)',
        'license':      'Licentie verwijderen'
    };

    $(document).on('change', 'input[name="cm_reset_item"]', function(){
        var anyChecked = $('input[name="cm_reset_item"]:checked').length > 0;
        $('#cm-reset-selective-preview').prop('disabled', !anyChecked);
        $('#cm-reset-summary').hide();
    });

    $(document).on('click', '#cm-reset-selective-preview', function(){
        var items = [];
        $('input[name="cm_reset_item"]:checked').each(function(){
            items.push(resetLabels[$(this).val()] || $(this).val());
        });
        if (!items.length) { $('#cm-reset-selective-none').show(); return; }
        $('#cm-reset-selective-none').hide();
        var listHtml = '';
        $.each(items, function(i, label){ listHtml += '<li>' + $('<div>').text(label).html() + '</li>'; });
        $('#cm-reset-summary-list').html(listHtml);
        $('#cm-reset-summary').show();
        $('#cm-reset-summary')[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    $(document).on('click', '#cm-reset-selective-cancel', function(){
        $('#cm-reset-summary').hide();
    });

    $(document).on('click', '#cm-reset-selective-confirm', function(){
        var $btn = $(this), $status = $('#cm-reset-selective-status');
        var items = [];
        $('input[name="cm_reset_item"]:checked').each(function(){ items.push($(this).val()); });
        if (!items.length) return;

        $btn.prop('disabled', true).text('Bezig...');
        $status.css('color','#646970').text('');
        var done = 0, total = items.length;
        var errors = [];

        function checkDone(label) {
            return function(r) {
                done++;
                if (!r || !r.success) errors.push(label);
                if (done >= total) {
                    if (errors.length) {
                        $status.css('color','#b32d2e').text('Fout bij: ' + errors.join(', '));
                        $btn.prop('disabled', false).text('Bevestig reset');
                    } else {
                        $status.css('color','#00a32a').text('✓ Reset voltooid.');
                        // Uncheck alle boxes en verberg samenvatting
                        $('input[name="cm_reset_item"]').prop('checked', false);
                        $('#cm-reset-selective-preview').prop('disabled', true);
                        setTimeout(function(){ window.location.reload(); }, 1500);
                    }
                }
            };
        }

        $.each(items, function(i, item) {
            if (item === 'settings')   $.post(CM_DATA.ajax_url, { action:'cm_reset_settings',  nonce:CM_DATA.nonce }, checkDone('Instellingen')).fail(checkDone('Instellingen'));
            if (item === 'cookielist') $.post(CM_DATA.ajax_url, { action:'cm_reset_cookielist', nonce:CM_DATA.nonce }, checkDone('Cookielijst')).fail(checkDone('Cookielijst'));
            if (item === 'privacy')    $.post(CM_DATA.ajax_url, { action:'cm_reset_privacy',    nonce:CM_DATA.nonce }, checkDone('Privacyverklaring')).fail(checkDone('Privacyverklaring'));
            if (item === 'log')        $.post(CM_DATA.ajax_url, { action:'cm_clear_log',         nonce:CM_DATA.nonce }, checkDone('Consent log')).fail(checkDone('Consent log'));
            if (item === 'consent' || item === 'consent_data') $.post(CM_DATA.ajax_url, { action:'cm_bump_consent_version', nonce:CM_DATA.nonce }, checkDone('Consent data')).fail(checkDone('Consent data'));
            if (item === 'license') $.post(CM_DATA.ajax_url, { action:'cm_reset_license', nonce:CM_DATA.nonce }, checkDone('Licentie')).fail(checkDone('Licentie'));
        });
    });

    /* ---- RESET DEFAULTS (wordt nog gebruikt door instellingen-pagina reset-knop) ---- */
    $(document).on('click', '#cm-reset-defaults', function(){
        if (!confirm('Alle instellingen terugzetten naar standaard? Dit kan niet ongedaan worden gemaakt.')) return;
        var $btn = $(this), $status = $('#cm-reset-status');
        $btn.prop('disabled', true);
        if (typeof CM_DATA !== 'undefined' && CM_DATA.defaults) {
            var d = CM_DATA.defaults;
            $.each(d, function(key, val){
                var $num = $('input[type="number"][name="' + key + '"]');
                if ($num.length) { $num.val(val); $('[data-for-number="' + key + '"]').val(val); return; }
                var $range = $('input[type="range"][name="' + key + '"]');
                if ($range.length) { $range.val(val); $('#val_' + key).text(val + ($range.data('unit') || '')); return; }
                var $chk = $('input[type="checkbox"][name="' + key + '"]');
                if ($chk.length) { $chk.prop('checked', val == 1); return; }
                var $picker = $('input[type="color"]#' + key);
                if ($picker.length) {
                    if (val === '') {
                        $picker.prop('disabled', true);
                        $('[data-for="' + key + '"]').addClass('cm-hex-disabled').val('');
                        $('[data-target="' + key + '"]').prop('checked', false);
                    } else {
                        $picker.prop('disabled', false).val(val);
                        $('[data-for="' + key + '"]').removeClass('cm-hex-disabled').val(val);
                        $('[data-target="' + key + '"]').prop('checked', true);
                    }
                    return;
                }
                $('[name="' + key + '"]').val(val);
            });
            if (typeof applyPreview === 'function') applyPreview();
        }
        $.post(CM_DATA.ajax_url, { action:'cm_reset_settings', nonce:CM_DATA.nonce }, function(r) {
            if (r && r.success) {
                if ($status.length) {
                    $status.css('color','#00a32a').text('✓ Instellingen teruggezet. Pagina wordt herladen…');
                }
                setTimeout(function(){ window.location.reload(); }, 1200);
            }
        }).always(function(){ $btn.prop('disabled', false); });
    });

    /* ---- RESET CONSENT (wist cookie bij bezoekers) ---- */
    $(document).on('click', '#cm-reset-consent', function(){
        if (!confirm('Weet u het zeker? Alle bezoekers krijgen de cookiemelding opnieuw te zien bij hun volgende bezoek.')) return;
        var $btn = $(this), $status = $('#cm-reset-consent-status');
        var reason = $('#cm-bump-reason').val() || '';
        $btn.prop('disabled', true);
        $.post(CM_DATA.ajax_url, { action: 'cm_bump_consent_version', nonce: CM_DATA.nonce, reason: reason }, function(r) {
            $status.css('color', r && r.success ? '#00a32a' : '#b32d2e')
                   .text(r && r.success ? '✓ Consent data gewist. Bezoekers zien de melding opnieuw.' : 'Fout bij verwerking.');
            if (r && r.success) {
                $('#cm-bump-reason').val('');
                setTimeout(function(){ window.location.reload(); }, 2000);
            } else {
                setTimeout(function(){ $status.text(''); }, 5000);
            }
        }).fail(function(){
            $status.css('color','#b32d2e').text('Verbindingsfout.');
        }).always(function(){ $btn.prop('disabled', false); });
    });

    /* ---- RESET LOG (wist consent log tabel) ---- */
    $(document).on('click', '#cm-reset-log', function(){
        if (!confirm('Weet u het zeker? Alle logregistraties worden permanent verwijderd.')) return;
        var $btn = $(this), $status = $('#cm-reset-log-status');
        $btn.prop('disabled', true);
        $.post(CM_DATA.ajax_url, { action: 'cm_clear_log', nonce: CM_DATA.nonce }, function(r) {
            $status.css('color', r && r.success ? '#00a32a' : '#b32d2e')
                   .text(r && r.success ? '✓ Consent log geleegd.' : 'Fout bij leegmaken.');
            setTimeout(function(){ $status.text(''); }, 3000);
        }).fail(function(){
            $status.css('color','#b32d2e').text('Verbindingsfout.');
        }).always(function(){ $btn.prop('disabled', false); });
    });

    /* ---- Cookielijst wissen (fallback voor directe aanroepen) ---- */
    $(document).on('click', '#cm-reset-cookielist', function(){
        if (!confirm('Alle handmatig toegevoegde cookies verwijderen uit de lijst?')) return;
        var $btn = $(this), $status = $('#cm-reset-cl-status');
        $btn.prop('disabled', true);
        $.post(CM_DATA.ajax_url, { action: 'cm_reset_cookielist', nonce: CM_DATA.nonce }, function(r) {
            if (r && r.success) { $status.css('color','#00a32a').text('✓ Cookielijst geleegd.'); setTimeout(function(){ $status.text(''); }, 3000); }
            else { $status.css('color','#b32d2e').text('Fout bij wissen.'); }
        }).always(function(){ $btn.prop('disabled', false); });
    });

    /* ---- API-sleutel genereren ---- */
    $(document).on('click', '#cm-generate-api-key', function() {
        // Genereer een cryptografisch veilige 40-karakter sleutel
        var arr = new Uint8Array(20);
        window.crypto.getRandomValues(arr);
        var key = Array.from(arr).map(function(b){ return b.toString(16).padStart(2,'0'); }).join('');
        $('#api_key').val(key).removeAttr('readonly').trigger('input');
        // Toon tip
        $(this).after('<span style="margin-left:8px;font-size:12px;color:#00a32a">&#10003; Sla instellingen op om de sleutel te activeren.</span>');
        setTimeout(function(){ $('#cm-generate-api-key').nextAll('span').first().fadeOut(500, function(){ $(this).remove(); }); }, 4000);
    });

    /* ---- API-sleutel intrekken ---- */
    $(document).on('click', '#cm-revoke-api-key', function() {
        if (!confirm('API-sleutel intrekken? Externe integraties die deze sleutel gebruiken verliezen direct toegang.')) return;
        $('#api_key').val('').trigger('input');
        var $btn = $(this);
        $.post(CM_DATA.ajax_url, { action: 'cm_save_settings', nonce: CM_DATA.nonce, api_key: '' }, function(r) {
            if (r && r.success) {
                $btn.after('<span style="margin-left:8px;font-size:12px;color:#00a32a">&#10003; Sleutel ingetrokken.</span>');
                setTimeout(function(){ window.location.reload(); }, 1500);
            }
        });
    });

    /* ---- Open Cookie Database importeren ---- */
    $(document).on('click', '#cm-import-db-btn', function() {
        var $btn = $(this), $status = $('#cm-import-db-status');
        $btn.prop('disabled', true).text('Bezig met laden...');
        $status.css('color','#646970').text('Open Cookie Database wordt gedownload...');

        $.post(CM_DATA.ajax_url, { action:'cm_import_cookie_db', nonce:CM_DATA.nonce }, function(r) {
            if (r.success) {
                $btn.text('Database bijwerken').prop('disabled', false);
                $status.css('color','#00a32a').text('✓ ' + r.data.imported + ' cookies geïmporteerd.');
            } else {
                $btn.text('Database laden').prop('disabled', false);
                $status.css('color','#b32d2e').text('Fout: ' + (r.data && r.data.msg ? r.data.msg : 'Onbekende fout'));
            }
        }).fail(function() {
            $btn.text('Database laden').prop('disabled', false);
            $status.css('color','#b32d2e').text('Verbindingsfout. Controleer of de server internet-toegang heeft.');
        });
    });

    /* ---- AUTOMATISCHE SCAN — toon/verberg intervál en e-mailadres ---- */
    function cmUpdateScanRows() {
        var mode = $('input[name="auto_scan_mode"]:checked').val();
        $('#cm-scan-interval-row').toggle( mode !== 'off' );
        $('#cm-scan-email-row').toggle( mode === 'notify' );
    }
    $(document).on('change', 'input[name="auto_scan_mode"]', cmUpdateScanRows);
    cmUpdateScanRows(); // initialiseer bij paginaload

    /* ---- COOKIE SCAN ---- */

    $(document).on('click', '#cm-scan-btn', function(){
        var $btn = $(this), $result = $('#cm-scan-result');
        $btn.prop('disabled', true).text('Bezig met crawlen...');
        $result.show().html(
            '<div id="cm-scan-progress" style="padding:12px 0">'
            + '<div style="font-size:13px;color:#1d2327;font-weight:500;margin-bottom:8px">'
            + '<span id="cm-scan-status-text">URLs worden opgehaald&hellip;</span></div>'
            + '<div style="background:#f0f0f1;border-radius:4px;height:12px;overflow:hidden;margin-bottom:6px">'
            + '<div id="cm-scan-bar" style="background:#2271b1;height:100%;width:0%;border-radius:4px;transition:width .3s"></div></div>'
            + '<div style="font-size:12px;color:#787c82"><span id="cm-scan-count">0</span> / <span id="cm-scan-total">?</span> pagina\'s gescand</div>'
            + '</div>'
        );

        var scanStart = Date.now();
        var BATCH_SIZE = 5;

        // Stap 1: haal alle URLs op
        $.post(CM_DATA.ajax_url, { action:'cm_scan_urls', nonce:CM_DATA.nonce }, function(urlRes){
            if (!urlRes.success) {
                $result.html('<p style="color:#b32d2e">Kon geen URLs ophalen.</p>');
                $btn.prop('disabled', false).text('Cookie scan starten');
                return;
            }
            var allUrls = urlRes.data.urls;
            var total = allUrls.length;
            var scannedCount = 0;
            var allCookies = [];
            var totalHttp = 0;
            var totalScript = 0;

            $('#cm-scan-total').text(total);
            $('#cm-scan-status-text').text('Bezig met scannen\u2026');

            // Stap 2: scan in batches
            var batches = [];
            for (var i = 0; i < allUrls.length; i += BATCH_SIZE) {
                batches.push(allUrls.slice(i, i + BATCH_SIZE));
            }

            function runBatch(idx) {
                if (idx >= batches.length) {
                    // Klaar — toon resultaten
                    finishScan();
                    return;
                }
                $.ajax({
                    url: CM_DATA.ajax_url,
                    type: 'POST',
                    data: { action:'cm_scan_batch', nonce:CM_DATA.nonce, urls: batches[idx] },
                    timeout: 60000,
                    success: function(batchRes) {
                        if (batchRes.success) {
                            var d = batchRes.data;
                            scannedCount += d.scanned || 0;
                            totalHttp += d.http_count || 0;
                            totalScript += d.script_count || 0;
                            // Merge cookies (deduplicate by name)
                            var existing = {};
                            allCookies.forEach(function(c) { existing[c.name] = true; });
                            (d.cookies || []).forEach(function(c) {
                                if (!existing[c.name]) {
                                    allCookies.push(c);
                                    existing[c.name] = true;
                                }
                            });
                        }
                        // Update voortgang
                        var pct = Math.round((idx + 1) / batches.length * 100);
                        $('#cm-scan-bar').css('width', pct + '%');
                        $('#cm-scan-count').text(scannedCount);
                        $('#cm-scan-status-text').text('Bezig met scannen\u2026 (' + pct + '%)');

                        runBatch(idx + 1);
                    },
                    error: function() {
                        // Bij fout: sla batch over, ga door
                        scannedCount += batches[idx].length;
                        $('#cm-scan-count').text(scannedCount);
                        runBatch(idx + 1);
                    }
                });
            }

            function finishScan() {
                var elapsed = ((Date.now() - scanStart) / 1000);
                var duration = elapsed < 60 ? elapsed.toFixed(1) + 's' : (elapsed / 60).toFixed(1) + ' min';

                $('#cm-scan-bar').css('width', '100%');
                $('#cm-scan-count').text(scannedCount);
                $('#cm-scan-status-text').text('Scan voltooid!');

                // Sorteer cookies
                var order = { functional:0, analytics:1, marketing:2, unknown:3 };
                allCookies.sort(function(a,b) {
                    var oa = order[a.type] !== undefined ? order[a.type] : 9;
                    var ob = order[b.type] !== undefined ? order[b.type] : 9;
                    return oa !== ob ? oa - ob : a.name.localeCompare(b.name);
                });
                window._cmLastScan = allCookies;

                setTimeout(function() {
                var colorMap = { 'functional':'#00a32a', 'analytics':'#2271b1', 'marketing':'#cc6600', 'unknown':'#787c82' };
                var labelMap = { 'functional':'Functioneel', 'analytics':'Analytisch', 'marketing':'Marketing', 'unknown':'Onbekend' };

                var html = '';
                html += '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">';
                html += '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:7px 12px;font-size:12px"><strong>' + scannedCount + '</strong> pagina\'s gescand</div>';
                html += '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:7px 12px;font-size:12px"><strong>' + allCookies.length + '</strong> cookies gevonden</div>';
                html += '<div style="background:#f0f6fc;border:1px solid #c3d9f0;border-radius:4px;padding:7px 12px;font-size:12px"><strong>' + totalHttp + '</strong> via HTTP header</div>';
                html += '<div style="background:#fef8f0;border:1px solid #f0dfc3;border-radius:4px;padding:7px 12px;font-size:12px"><strong>' + totalScript + '</strong> via script detectie</div>';
                html += '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:7px 12px;font-size:12px">&#9201; ' + duration + '</div>';
                html += '</div>';

                if (allCookies.length === 0) {
                    html += '<p style="color:#646970;padding:12px 0">Geen cookies gevonden. Controleer F12 &rarr; Applicatie &rarr; Cookies in uw browser voor een volledig beeld.</p>';
                } else {
                    html += '<div style="margin-bottom:10px">';
                    html += '<button type="button" class="button button-primary" id="cm-scan-import-all">&#x2795; Alle gevonden cookies toevoegen aan lijst</button>';
                    html += '<span id="cm-scan-import-all-msg" style="margin-left:12px;font-size:13px;display:none"></span>';
                    html += '</div>';
                    html += '<div style="overflow-x:auto;margin:0 -1px">';
                    html += '<table style="width:100%;min-width:620px;border-collapse:collapse;font-size:12px;border:1px solid #dcdcde">';
                    html += '<thead><tr style="background:#f6f7f7">'
                        + '<th style="text-align:left;padding:8px 10px;border-bottom:1px solid #dcdcde;font-weight:600;white-space:nowrap">Cookie</th>'
                        + '<th style="text-align:left;padding:8px 10px;border-bottom:1px solid #dcdcde;font-weight:600;white-space:nowrap">Type</th>'
                        + '<th style="text-align:left;padding:8px 10px;border-bottom:1px solid #dcdcde;font-weight:600;white-space:nowrap">Provider</th>'
                        + '<th style="text-align:left;padding:8px 10px;border-bottom:1px solid #dcdcde;font-weight:600">Omschrijving</th>'
                        + '<th style="text-align:left;padding:8px 10px;border-bottom:1px solid #dcdcde;font-weight:600;white-space:nowrap">Looptijd</th>'
                        + '<th style="text-align:left;padding:8px 10px;border-bottom:1px solid #dcdcde;font-weight:600;white-space:nowrap">Bron</th>'
                        + '<th style="width:70px"></th>'
                        + '</tr></thead><tbody>';

                    $.each(allCookies, function(i, ck){
                        var clr   = colorMap[ck.type] || '#787c82';
                        var label = labelMap[ck.type] || ck.type;
                        var bg    = (i % 2 === 0) ? '#fff' : '#fafafa';
                        var bronClr   = ck.how === 'server' ? '#00a32a' : '#c46a00';
                        var bronLabel = ck.how === 'server' ? 'HTTP' : 'Script';
                        var desc  = ck.description ? ck.description : '<span style="color:#aaa">\u2014</span>';
                        html += '<tr style="background:' + bg + ';border-bottom:1px solid #f0f0f1">';
                        html += '<td style="padding:6px 10px;font-family:monospace;font-size:11px;font-weight:700;color:#1d2327;white-space:nowrap">' + ck.name + '</td>';
                        html += '<td style="padding:6px 10px;white-space:nowrap"><span style="background:' + clr + '18;color:' + clr + ';padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700">' + label + '</span></td>';
                        html += '<td style="padding:6px 10px;color:#444;white-space:nowrap">' + ck.provider + '</td>';
                        html += '<td style="padding:6px 10px;color:#646970;font-size:11px;max-width:200px">' + desc + '</td>';
                        html += '<td style="padding:6px 10px;color:#646970;white-space:nowrap">' + ck.duration + '</td>';
                        html += '<td style="padding:6px 10px;white-space:nowrap"><span style="color:' + bronClr + ';font-size:11px;font-weight:600">' + bronLabel + '</span></td>';
                        html += '<td style="padding:4px 6px"><button type="button" class="button button-small cm-scan-import-btn" data-scan-idx="' + i + '">+ Lijst</button></td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    html += '</div>';
                }

                // Legenda
                html += '<div style="margin-top:10px;font-size:11px;color:#646970;display:flex;gap:16px;flex-wrap:wrap">'
                    + '<span><strong style="color:#00a32a">HTTP</strong> \u2014 cookie gezet door server</span>'
                    + '<span><strong style="color:#c46a00">Script</strong> \u2014 afgeleid uit tracking-scripts (door browser na JS-uitvoering)</span>'
                    + '</div>';

                $result.html(html);
                $btn.prop('disabled', false).text('Cookie scan starten');

                }, 500); // setTimeout
            }

            runBatch(0);

        }).fail(function(){
            $result.html('<p style="color:#b32d2e">Kon geen URLs ophalen. Probeer opnieuw.</p>');
            $btn.prop('disabled', false).text('Cookie scan starten');
        });
    });

    /* ================================================================
       COOKIELIJST BEHEER
    ================================================================ */

    var cmCookies = []; // in-memory lijst van beheerde cookies

    var catOptions =
        '<option value="functional">Functioneel</option>' +
        '<option value="analytics">Analytisch</option>' +
        '<option value="marketing">Marketing</option>';

    function cmCookieRow(ck, idx) {
        var cat = ck.category || 'functional';
        var selA = cat==='functional' ? ' selected' : '';
        var selB = cat==='analytics'  ? ' selected' : '';
        var selC = cat==='marketing'  ? ' selected' : '';
        return '<tr data-idx="' + idx + '">' +
            '<td><input type="text" class="regular-text cm-ck-name" value="' + escHtml(ck.name) + '" placeholder="Cookie naam" style="width:100%"></td>' +
            '<td><input type="text" class="regular-text cm-ck-provider" value="' + escHtml(ck.provider||'') + '" placeholder="Provider" style="width:100%"></td>' +
            '<td><input type="text" class="regular-text cm-ck-purpose" value="' + escHtml(ck.purpose||'') + '" placeholder="Doel / omschrijving" style="width:100%"></td>' +
            '<td><input type="text" class="cm-ck-duration" value="' + escHtml(ck.duration||'Sessie') + '" placeholder="Sessie" style="width:90px"></td>' +
            '<td><select class="cm-ck-cat">' +
                '<option value="functional"' + selA + '>Functioneel</option>' +
                '<option value="analytics"'  + selB + '>Analytisch</option>' +
                '<option value="marketing"'  + selC + '>Marketing</option>' +
            '</select></td>' +
            '<td><button type="button" class="button cm-ck-del" title="Verwijderen" style="color:#b32d2e;padding:2px 6px">&#x2715;</button></td>' +
        '</tr>';
    }

    function escHtml(s) {
        return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function cmRenderRows() {
        var $tbody = $('#cm-cookie-rows');
        if (!$tbody.length) return;
        if (cmCookies.length === 0) {
            $tbody.html('<tr><td colspan="6" style="color:#787c82;padding:16px;text-align:center">Nog geen cookies toegevoegd. Klik op "+ Cookie toevoegen" of importeer vanuit F12.</td></tr>');
        } else {
            var html = '';
            $.each(cmCookies, function(i, ck) { html += cmCookieRow(ck, i); });
            $tbody.html(html);
        }
    }

    function cmReadRows() {
        cmCookies = [];
        $('#cm-cookie-rows tr[data-idx]').each(function() {
            var $r = $(this);
            var name = $.trim($r.find('.cm-ck-name').val());
            if (!name) return;
            cmCookies.push({
                name:     name,
                provider: $.trim($r.find('.cm-ck-provider').val()),
                purpose:  $.trim($r.find('.cm-ck-purpose').val()),
                duration: $.trim($r.find('.cm-ck-duration').val()) || 'Sessie',
                category: $r.find('.cm-ck-cat').val(),
            });
        });
    }

    // Laad bij tabwissel naar 'cookies' (legacy, tab bestaat niet meer maar voor zekerheid)
    $(document).on('click', '[data-tab="cookies"]', function() {
        $.post(CM_DATA.ajax_url, { action:'cm_get_cookie_list', nonce:CM_DATA.nonce }, function(r) {
            if (r.success) { cmCookies = r.data.managed || []; cmRenderRows(); }
        });
    });

    // Laad cookies bij pagina-init — render als de tabel aanwezig is (cookielijst pagina)
    $.post(CM_DATA.ajax_url, { action:'cm_get_cookie_list', nonce:CM_DATA.nonce }, function(r) {
        if (r.success) {
            cmCookies = r.data.managed || [];
            if ($('#cm-cookie-rows').length) cmRenderRows();
        }
    });

    // Cookie toevoegen
    $(document).on('click', '#cm-cookie-add-btn', function() {
        cmReadRows();
        cmCookies.push({ name:'', provider:'', purpose:'', duration:'Sessie', category:'functional' });
        cmRenderRows();
        // Focus op laatste rij naam-veld
        $('#cm-cookie-rows tr:last .cm-ck-name').focus();
    });

    // Cookie verwijderen
    $(document).on('click', '.cm-ck-del', function() {
        cmReadRows();
        var idx = parseInt($(this).closest('tr').attr('data-idx'));
        cmCookies.splice(idx, 1);
        cmRenderRows();
    });

    // Cookielijst opslaan
    $(document).on('click', '#cm-cookie-save-btn, .cm-cookie-save-footer-btn', function() {
        cmReadRows();
        var $btn = $(this);
        $btn.prop('disabled', true);

        // Sla cookielijst op
        var p1 = $.post(CM_DATA.ajax_url, {
            action:       'cm_save_cookie_list',
            nonce:        CM_DATA.nonce,
            cookies_json: JSON.stringify(cmCookies),
        });

        // Sla scan-instellingen tegelijk op (als het formulier aanwezig is)
        var p2 = $('input[name="auto_scan_mode"]').length
            ? $.post(CM_DATA.ajax_url, {
                action             : 'cm_save_scan_settings',
                nonce              : CM_DATA.nonce,
                auto_scan_mode     : $('input[name="auto_scan_mode"]:checked').val() || 'off',
                auto_scan_interval : $('#auto_scan_interval').val() || '30',
                auto_scan_email    : $('#auto_scan_email').val() || '',
            })
            : $.when();

        $.when(p1, p2).done(function(r1, r2) {
            if (r1 && r1[0] && r1[0].success) {
                cmShowSavedNotice($btn);
                cmRenderRows();
            }
            // Countdown bijwerken als scan-instellingen opgeslagen zijn
            if (r2 && r2[0] && r2[0].success && r2[0].data) {
                var d = r2[0].data;
                var mode = $('input[name="auto_scan_mode"]:checked').val();
                if (mode === 'off') {
                    $('#cm-scan-countdown-row').hide();
                } else if (d.next_ts && d.next_formatted) {
                    if (!$('#cm-scan-countdown-row').length) {
                        location.reload(); return;
                    }
                    $('#cm-scan-countdown-row').show();
                    $('#cm-scan-next-date').text(d.next_formatted);
                    cmStartCountdown(d.next_ts);
                }
            }
        }).always(function() { $btn.prop('disabled', false); });
    });

    // Herbruikbare countdown starter
    var cmCountdownTimer = null; // Globale timer variabele

    function cmStartCountdown(nextTs) {
        // Stop oude timer als die bestaat
        if (cmCountdownTimer) {
            clearTimeout(cmCountdownTimer);
            cmCountdownTimer = null;
        }

        var target = nextTs * 1000;
        var el = document.getElementById('cm-scan-countdown');
        if (!el) return;
        function pad(n){ return n < 10 ? '0'+n : n; }
        function tick(){
            var diff = Math.max(0, Math.floor((target - Date.now()) / 1000));
            if (!el) return;
            if (diff <= 0) { el.textContent = 'Binnenkort'; return; }
            var dy = Math.floor(diff / 86400);
            var h  = Math.floor((diff % 86400) / 3600);
            var m  = Math.floor((diff % 3600) / 60);
            var s  = diff % 60;
            var parts = [];
            if (dy > 0) parts.push(dy + 'd');
            parts.push(pad(h) + 'u');
            parts.push(pad(m) + 'm');
            parts.push(pad(s) + 's');
            el.textContent = parts.join(' ');
            cmCountdownTimer = setTimeout(tick, 1000);
        }
        tick();
    }
    
    // Maak de functie globaal beschikbaar voor inline script
    window.cmStartCountdown = cmStartCountdown;

    // Reset timer — herplant cron naar nu + interval
    $(document).on('click', '#cm-scan-reset-btn', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Bezig...');
        $.post(CM_DATA.ajax_url, {
            action             : 'cm_reset_scan_timer',
            nonce              : CM_DATA.nonce,
            auto_scan_mode     : $('input[name="auto_scan_mode"]:checked').val() || 'off',
            auto_scan_interval : $('#auto_scan_interval').val() || '30',
            auto_scan_email    : $('#auto_scan_email').val() || '',
        }, function(res) {
            $btn.prop('disabled', false).html('&#x21BA; Reset timer');
            if (res.success && res.data && res.data.next_ts) {
                $('#cm-scan-next-date').text(res.data.next_formatted);
                cmStartCountdown(res.data.next_ts);
            }
        });
    });

    // Auto-scan mode radio buttons — toon/verberg relevante rows
    $(document).on('change', 'input[name="auto_scan_mode"]', function() {
        var mode = $(this).val();
        // Scanfrequentie row: tonen bij auto en notify
        if (mode === 'off') {
            $('#cm-scan-interval-row').hide();
        } else {
            $('#cm-scan-interval-row').show();
        }
        // Email row: alleen bij notify
        if (mode === 'notify') {
            $('#cm-scan-email-row').show();
        } else {
            $('#cm-scan-email-row').hide();
        }
        // Countdown row: tonen bij auto en notify
        if (mode === 'off') {
            $('#cm-scan-countdown-row').hide();
        } else {
            $('#cm-scan-countdown-row').show();
        }
    });

    // Import paneel tonen/verbergen
    $(document).on('click', '#cm-cookie-import-btn', function() {
        $('#cm-import-panel').slideToggle(150);
        $('#cm-import-textarea').val('').focus();
    });
    $(document).on('click', '#cm-import-cancel-btn', function() {
        $('#cm-import-panel').slideUp(150);
    });

    // Parseer geplakte F12 cookie tekst
    $(document).on('click', '#cm-import-parse-btn', function() {
        var raw = $('#cm-import-textarea').val();
        if (!raw.trim()) return;

        cmReadRows();
        var existingNames = {};
        $.each(cmCookies, function(_, ck) { existingNames[ck.name.toLowerCase()] = true; });

        // Bekende cookie-kennisbank voor automatische categorisering
        var knownCats = {
            '_ga': 'analytics', '_gid': 'analytics', '_gat': 'analytics', '_gcl': 'analytics',
            '_hjid': 'analytics', '_hjFirstSeen': 'analytics', '_clck': 'analytics', '_clsk': 'analytics',
            '_fbp': 'marketing', '_fbc': 'marketing', 'IDE': 'marketing', 'PREF': 'marketing',
            'NID': 'marketing', 'VISITOR_INFO1_LIVE': 'marketing', 'YSC': 'marketing',
            'bcookie': 'marketing', 'lidc': 'marketing', 'fr': 'marketing',
            '_gcl_au': 'marketing', '_gcl_aw': 'marketing',
            'PHPSESSID': 'functional', 'cc_cm_consent': 'functional',
            'wordpress': 'functional', 'wp-': 'functional', 'woocommerce': 'functional',
        };

        function guessCat(name) {
            var n = name.toLowerCase();
            for (var pat in knownCats) {
                if (n.indexOf(pat.toLowerCase()) === 0) return knownCats[pat];
            }
            return 'functional';
        }

        var imported = 0;
        var lines = raw.split(/\n/);
        $.each(lines, function(_, line) {
            line = $.trim(line);
            if (!line || line.length < 2) return;

            // Probeer naam te extraheren: eerste woord / kolom is meestal de naam
            // F12 exporteert vaak: naam [tab] value [tab] domain [tab] path [tab] expires [tab] size [tab] ...
            var parts = line.split(/\t/);
            var name = '';
            var duration = 'Sessie';

            if (parts.length >= 2) {
                name = $.trim(parts[0]);
                // Expires staat meestal op positie 4 of 5
                if (parts[4] && parts[4].match(/\d{4}/)) {
                    var ts = new Date(parts[4]);
                    if (ts && ts > new Date()) {
                        var days = Math.round((ts - new Date()) / 86400000);
                        if (days >= 365) duration = Math.round(days/365) + ' jaar';
                        else if (days >= 30) duration = Math.round(days/30) + ' maanden';
                        else duration = days + ' dagen';
                    }
                }
            } else {
                // Geen tabs — probeer eerste token
                name = line.split(/\s+/)[0];
            }

            // Validatie: moet een redelijke cookie-naam zijn
            if (!name || name.length < 1 || name.length > 128) return;
            if (name.match(/^(Name|Cookie|naam|#)/i)) return; // header-rij overslaan
            if (existingNames[name.toLowerCase()]) return; // duplicaat

            existingNames[name.toLowerCase()] = true;
            imported++;
            cmCookies.push({
                name:     name,
                provider: '',
                purpose:  '',
                duration: duration,
                category: guessCat(name),
            });
        });

        cmRenderRows();
        $('#cm-import-panel').slideUp(150);
        $('#cm-import-textarea').val('');

        if (imported > 0) {
            alert(imported + ' cookie(s) geimporteerd. Vul provider en doel in, en sla de lijst op.');
        } else {
            alert('Geen nieuwe cookies herkend. Controleer het formaat (tab-gescheiden uit F12).');
        }
    });

    // Scan-resultaten importeren naar cookielijst (zonder tab te verlaten)
    $(document).on('click', '.cm-scan-import-btn', function() {
        var idx = parseInt($(this).attr('data-scan-idx'));
        var ck = window._cmLastScan && window._cmLastScan[idx];
        if (!ck) return;
        var $btn = $(this);
        $btn.text('Bezig...').prop('disabled', true);

        // Haal altijd de actuele lijst op (ook als cookielijst op aparte pagina staat)
        $.post(CM_DATA.ajax_url, { action:'cm_get_cookie_list', nonce:CM_DATA.nonce }, function(r) {
            if (r.success) {
                cmCookies = r.data.managed || [];
            }

            // Check of cookie al in lijst staat
            var exists = false;
            $.each(cmCookies, function(_, c) {
                if (c.name === ck.name) { exists = true; return false; }
            });

            if (exists) {
                $btn.text('Al in lijst').prop('disabled', true);
                return;
            }

            // Voeg toe
            cmCookies.push({
                name:     ck.name,
                provider: ck.provider || '',
                purpose:  ck.description || '',
                duration: ck.duration || 'Sessie',
                category: ck.type || 'functional',
            });

            // Render als cookielijst-tabel zichtbaar is op deze pagina
            if ($('#cm-cookie-rows').length) cmRenderRows();

            // Sla direct op
            $.post(CM_DATA.ajax_url, {
                action:       'cm_save_cookie_list',
                nonce:        CM_DATA.nonce,
                cookies_json: JSON.stringify(cmCookies),
            }, function(r2) {
                if (r2.success) {
                    $btn.text('✓ Toegevoegd').prop('disabled', true);
                } else {
                    $btn.text('+ Lijst').prop('disabled', false);
                }
            }).fail(function() {
                $btn.text('+ Lijst').prop('disabled', false);
            });
        }).fail(function() {
            $btn.text('+ Lijst').prop('disabled', false);
        });
    });

    /* ---- Alles toevoegen aan cookielijst ---- */
    $(document).on('click', '#cm-scan-import-all', function() {
        var cookies = window._cmLastScan;
        if (!cookies || !cookies.length) return;
        var $btn = $(this), $msg = $('#cm-scan-import-all-msg');
        $btn.prop('disabled', true).text('Bezig...');

        $.post(CM_DATA.ajax_url, { action:'cm_get_cookie_list', nonce:CM_DATA.nonce }, function(r) {
            if (r.success) cmCookies = r.data.managed || [];

            var added = 0;
            $.each(cookies, function(_, ck) {
                var exists = false;
                $.each(cmCookies, function(_, c) { if (c.name === ck.name) { exists = true; return false; } });
                if (!exists) {
                    cmCookies.push({ name: ck.name, provider: ck.provider || '', purpose: ck.description || '', duration: ck.duration || 'Sessie', category: ck.type || 'functional' });
                    added++;
                }
                // Markeer de individuele knop
                $('[data-scan-idx]').filter(function() {
                    return $(this).closest('tr').find('td:first').text() === ck.name || window._cmLastScan[parseInt($(this).attr('data-scan-idx'))]?.name === ck.name;
                }).text(exists ? 'Al in lijst' : '✓ Toegevoegd').prop('disabled', true);
            });
            // Markeer alle + Lijst knoppen
            $('.cm-scan-import-btn').each(function() {
                var idx = parseInt($(this).attr('data-scan-idx'));
                var ck = window._cmLastScan[idx];
                if (!ck) return;
                var exists = false;
                $.each(r.success ? (r.data.managed || []) : [], function(_, c) { if (c.name === ck.name) { exists = true; return false; } });
                $(this).text(exists ? 'Al in lijst' : '✓ Toegevoegd').prop('disabled', true);
            });

            if (added === 0) {
                $msg.text('Alle cookies staan al in de lijst.').css('color','#646970').show();
                $btn.prop('disabled', false).html('&#x2795; Alle gevonden cookies toevoegen aan lijst');
                return;
            }

            if ($('#cm-cookie-rows').length) cmRenderRows();

            $.post(CM_DATA.ajax_url, { action:'cm_save_cookie_list', nonce:CM_DATA.nonce, cookies_json: JSON.stringify(cmCookies) }, function(r2) {
                if (r2.success) {
                    $msg.text('✓ ' + added + ' cookie(s) toegevoegd.').css('color','#00a32a').show();
                    $btn.text('✓ Klaar').prop('disabled', true);
                } else {
                    $btn.html('&#x2795; Alle gevonden cookies toevoegen aan lijst').prop('disabled', false);
                }
            }).fail(function() {
                $btn.html('&#x2795; Alle gevonden cookies toevoegen aan lijst').prop('disabled', false);
            });
        }).fail(function() {
            $btn.html('&#x2795; Alle gevonden cookies toevoegen aan lijst').prop('disabled', false);
        });
    });

    /* ---- Cookielijst leegmaken ---- */
    $(document).on('click', '#cm-cookie-clear-btn', function() {
        if (!confirm('Weet u zeker dat u de cookielijst wilt leegmaken? De ingebouwde cookies blijven aanwezig.')) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(CM_DATA.ajax_url, { action:'cm_save_cookie_list', nonce:CM_DATA.nonce, cookies_json: JSON.stringify([]) }, function(r) {
            if (r.success) {
                cmCookies = [];
                if ($('#cm-cookie-rows').length) cmRenderRows();
                cmShowSavedNotice($btn);
            }
            $btn.prop('disabled', false);
        }).fail(function() { $btn.prop('disabled', false); });
    });

    /* ---- Alles resetten ---- */
    $(document).on('click', '#cm-reset-all', function() {
        if (!confirm('LET OP: Dit reset ALLES — instellingen, cookielijst, privacyverklaring, consent log, consent data én licentie. Dit kan niet ongedaan worden gemaakt. Weet u het zeker?')) return;
        var $btn = $(this), $status = $('#cm-reset-all-status');
        $btn.prop('disabled', true).text('Bezig...');
        var done = 0, total = 6;
        function checkDone() {
            done++;
            if (done === total) {
                $status.text('✓ Alles gereset. Pagina wordt herladen...').css('color','#00a32a');
                setTimeout(function() { location.reload(); }, 1500);
            }
        }
        $.post(CM_DATA.ajax_url, { action: 'cm_reset_settings',        nonce: CM_DATA.nonce }, checkDone).fail(checkDone);
        $.post(CM_DATA.ajax_url, { action: 'cm_reset_cookielist',       nonce: CM_DATA.nonce }, checkDone).fail(checkDone);
        $.post(CM_DATA.ajax_url, { action: 'cm_reset_privacy',          nonce: CM_DATA.nonce }, checkDone).fail(checkDone);
        $.post(CM_DATA.ajax_url, { action: 'cm_clear_log',              nonce: CM_DATA.nonce }, checkDone).fail(checkDone);
        $.post(CM_DATA.ajax_url, { action: 'cm_bump_consent_version',   nonce: CM_DATA.nonce }, checkDone).fail(checkDone);
        $.post(CM_DATA.ajax_url, { action: 'cm_reset_license',          nonce: CM_DATA.nonce }, checkDone).fail(checkDone);
    });

    /* ---- Privacy terugzetten ---- */
    $(document).on('click', '#cm-reset-privacy', function() {
        if (!confirm('Weet u het zeker? Alle ingevulde privacyverklaring gegevens worden gewist.')) return;
        var $btn = $(this), $status = $('#cm-reset-privacy-status');
        $btn.prop('disabled', true);
        $.post(CM_DATA.ajax_url, { action: 'cm_reset_privacy', nonce: CM_DATA.nonce }, function(r) {
            if (r && r.success) {
                $status.css('color','#00a32a').text('✓ Privacyverklaring teruggezet.');
                setTimeout(function(){ $status.text(''); }, 3000);
            } else {
                $status.css('color','#b32d2e').text('Fout bij terugzetten.');
            }
        }).always(function(){ $btn.prop('disabled', false); });
    });

    /* ================================================================
       PRIVACYVERKLARING TAB
    ================================================================ */

    // Rij verwijderen uit tabellen
    $(document).on('click', '.cm-pv-del-row', function() {
        $(this).closest('tr').remove();
    });

    // Doeleinden — rij toevoegen
    $(document).on('click', '#cm-pv-add-doel', function() {
        $('#cm-pv-doeleinden-body').append(
            '<tr>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Doel"></td>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Grondslag"></td>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Termijn"></td>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px;text-align:center"><button type="button" class="button button-small cm-pv-del-row" style="color:#b32d2e">&#x2715;</button></td>' +
            '</tr>'
        );
    });

    // Opt-out links — rij toevoegen
    $(document).on('click', '.cm-pv-add-optout', function() {
        $('#cm-pv-optout-body').append(
            '<tr>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Naam"></td>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="https://..."></td>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px;text-align:center"><button type="button" class="button button-small cm-pv-del-row" style="color:#b32d2e">&#x2715;</button></td>' +
            '</tr>'
        );
    });

    // Ontvangers — rij toevoegen
    $(document).on('click', '#cm-pv-add-ontvanger', function() {
        $('#cm-pv-ontvangers-body').append(
            '<tr>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Partij"></td>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="Doel"></td>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px"><input type="text" class="widefat" style="border:0;box-shadow:none" placeholder="NL / VS* / VK"></td>' +
            '<td style="border:1px solid #dcdcde;padding:4px 6px;text-align:center"><button type="button" class="button button-small cm-pv-del-row" style="color:#b32d2e">&#x2715;</button></td>' +
            '</tr>'
        );
    });

    // Privacyverklaring opslaan
    $(document).on('click', '#cm-pv-save-btn', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Opslaan...');

        var data = { action: 'cm_save_privacy', nonce: CM_DATA.nonce };

        // Tekstvelden + textareas
        $('.cm-pv-field').each(function() {
            var n = $(this).attr('name');
            if (n) data[n] = $(this).val();
        });

        // Checkboxes
        $('.cm-pv-cb').each(function() {
            var n = $(this).attr('name');
            if (n) data[n] = $(this).is(':checked') ? '1' : '0';
        });

        // Doeleinden → JSON
        var doeleinden = [];
        $('#cm-pv-doeleinden-body tr').each(function() {
            var inp = $(this).find('input');
            var doel = inp.eq(0).val(), grondslag = inp.eq(1).val(), termijn = inp.eq(2).val();
            if (doel || grondslag) doeleinden.push({ doel: doel, grondslag: grondslag, termijn: termijn });
        });
        data['pv_doeleinden'] = JSON.stringify(doeleinden);

        // Opt-out links → JSON
        var optout = [];
        $('#cm-pv-optout-body tr').each(function() {
            var inp = $(this).find('input');
            var naam = inp.eq(0).val(), url = inp.eq(1).val();
            if (naam || url) optout.push({ naam: naam, url: url });
        });
        data['pv_optout_links'] = JSON.stringify(optout);

        // Ontvangers → JSON
        var ontvangers = [];
        $('#cm-pv-ontvangers-body tr').each(function() {
            var inp = $(this).find('input');
            var partij = inp.eq(0).val(), doel = inp.eq(1).val(), locatie = inp.eq(2).val();
            if (partij) ontvangers.push({ partij: partij, doel: doel, locatie: locatie });
        });
        data['pv_ontvangers'] = JSON.stringify(ontvangers);

        $.post(CM_DATA.ajax_url, data, function(r) {
            if (r.success) {
                cmShowSavedNotice($btn);
            } else {
                alert('Fout bij opslaan.');
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Privacyverklaring opslaan');
        });
    });

    /* ================================================================
       EXPORT / IMPORT
    ================================================================ */

    // Export — download JSON bestand
    $(document).on('click', '#cm-export-btn', function() {
        var $btn = $(this), $status = $('#cm-export-status');
        $btn.prop('disabled', true).text('Bezig...');
        $status.css('color','#646970').text('Instellingen ophalen...');

        $.post(CM_DATA.ajax_url, { action: 'cm_export_settings', nonce: CM_DATA.nonce }, function(r) {
            if (!r.success) {
                $status.css('color','#b32d2e').text('Export mislukt.');
                return;
            }
            var json     = JSON.stringify(r.data, null, 2);
            var blob     = new Blob([json], { type: 'application/json' });
            var url      = URL.createObjectURL(blob);
            var datum    = new Date().toISOString().slice(0,10);
            var filename = 'cookiemelding-backup-' + datum + '.json';
            var a        = document.createElement('a');
            a.href = url; a.download = filename; a.click();
            URL.revokeObjectURL(url);
            $status.css('color','#00a32a').text('✓ ' + filename + ' gedownload.');
        }).fail(function() {
            $status.css('color','#b32d2e').text('Verbindingsfout.');
        }).always(function() {
            $btn.prop('disabled', false).text('⬇ Instellingen exporteren (.json)');
        });
    });

    // Verwerkingsregister exporteren
    $(document).on('click', '#cm-export-register-btn', function() {
        var $btn = $(this), $status = $('#cm-export-register-status');
        $btn.prop('disabled', true).text('Bezig...');
        // Directe download via URL met nonce
        var url = CM_DATA.ajax_url + '?action=cm_export_register&nonce=' + CM_DATA.nonce;
        var a = document.createElement('a');
        a.href = url; a.download = ''; a.click();
        $status.css('color','#00a32a').text('✓ Download gestart.');
        setTimeout(function(){ $status.text(''); $btn.prop('disabled', false).text('⬇ Verwerkingsregister downloaden (.csv)'); }, 3000);
    });

    // Import — dropzone + bestandskiezer
    var _importData = null;

    function cmImportSetFile(file) {
        if (!file || !file.name.match(/\.json$/i)) {
            $('#cm-import-status').css('color','#b32d2e').text('Kies een .json bestand.');
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            try {
                var data = JSON.parse(e.target.result);
                if (!data._meta || data._meta.plugin !== 'cookiemelding') {
                    $('#cm-import-status').css('color','#b32d2e').text('Ongeldig bestand — niet afkomstig van de Cookiemelding plugin.');
                    _importData = null;
                    $('#cm-import-btn').prop('disabled', true);
                    $('#cm-import-preview').hide();
                    return;
                }
                _importData = data;
                var cookieCount = (data.cookie_list || []).length;
                $('#cm-import-filename').text(file.name);
                $('#cm-import-summary').html(
                    'Plugin versie: <strong>' + (data._meta.version || '?') + '</strong> &mdash; ' +
                    'Geëxporteerd: <strong>' + (data._meta.exported ? data._meta.exported.slice(0,10) : '?') + '</strong> &mdash; ' +
                    'Van: <strong>' + (data._meta.site || '?') + '</strong> &mdash; ' +
                    cookieCount + ' cookies'
                );
                $('#cm-import-preview').show();
                $('#cm-import-btn').prop('disabled', false);
                $('#cm-import-status').text('');
            } catch(err) {
                $('#cm-import-status').css('color','#b32d2e').text('Fout bij lezen: ongeldig JSON-bestand.');
                _importData = null;
                $('#cm-import-btn').prop('disabled', true);
            }
        };
        reader.readAsText(file);
    }

    // Klik op dropzone wordt native afgehandeld via <label for="cm-import-file">
    $(document).on('change', '#cm-import-file', function() {
        cmImportSetFile(this.files[0]);
    });

    // Drag & drop op de label
    $(document).on('dragover', '#cm-import-dropzone', function(e) {
        e.preventDefault();
        $(this).css({ 'border-color':'#2271b1', 'background':'#f0f6fc' });
    });
    $(document).on('dragleave drop', '#cm-import-dropzone', function(e) {
        e.preventDefault();
        $(this).css({ 'border-color':'#c3c4c7', 'background':'' });
        if (e.type === 'drop') {
            var file = e.originalEvent.dataTransfer.files[0];
            cmImportSetFile(file);
        }
    });

    // Import — verstuur naar server
    $(document).on('click', '#cm-import-btn', function() {
        if (!_importData) return;
        if (!confirm('Weet u zeker dat u wilt importeren? Alle huidige instellingen worden overschreven.')) return;

        var $btn = $(this), $status = $('#cm-import-status');
        $btn.prop('disabled', true).text('Importeren...');
        $status.css('color','#646970').text('Bezig...');

        $.post(CM_DATA.ajax_url, {
            action: 'cm_import_settings',
            nonce:  CM_DATA.nonce,
            data:   JSON.stringify(_importData),
        }, function(r) {
            if (r.success) {
                $status.css('color','#00a32a').text('✓ ' + r.data.msg + ' Pagina wordt herladen...');
                setTimeout(function() { location.reload(); }, 1800);
            } else {
                $status.css('color','#b32d2e').text('Fout: ' + (r.data && r.data.msg ? r.data.msg : 'Onbekend'));
                $btn.prop('disabled', false).text('⬆ Importeren & overschrijven');
            }
        }).fail(function() {
            $status.css('color','#b32d2e').text('Verbindingsfout.');
            $btn.prop('disabled', false).text('⬆ Importeren & overschrijven');
        });
    });

    /* ---- Zweefknop stijl: toon/verberg icoontje kleuren ---- */
    function cmToggleFloatIconRows() {
        var isIcon = $('input[name="float_btn_style"]:checked').val() === 'icon';
        $('#cm-float-icon-colors, .cm-float-icon-row').toggle(isIcon);
    }
    $(document).on('change', 'input[name="float_btn_style"]', function() {
        cmToggleFloatIconRows();
    });

    /* ---- Pagina-uitzonderingen: sync multi-select naar hidden field ---- */
    window.cmSyncExcludeIds = function() {
        var ids = [];
        $('#cm-exclude-pages option:selected').each(function() {
            ids.push($(this).val());
        });
        $('#cm-exclude-page-ids-hidden').val(ids.join(','));
    };
    // Init: zorg dat hidden field al gevuld is bij pageload
    $(function() {
        if ($('#cm-exclude-pages').length) cmSyncExcludeIds();
    });

    /* ---- Accordeon voor kleuren-secties ---- */
    $(document).on('click', '.cm-accordion-head', function() {
        var $group = $(this).closest('.cm-accordion');
        var $body  = $group.find('.cm-accordion-body').first();
        var isOpen = $group.hasClass('cm-acc-open');
        if (isOpen) {
            $body.slideUp(180);
            $group.removeClass('cm-acc-open');
        } else {
            $body.slideDown(180);
            $group.addClass('cm-acc-open');
        }
    });

    /* ---- Meertaligheid: taalwisselaar tabs ---- */
    $(document).on('click', '.cm-lang-tab', function() {
        var lang = $(this).data('lang');
        _activeLang = lang;
        // Update tab styling
        $('.cm-lang-tab').css({ 'border-bottom-color': 'transparent', 'font-weight': '400' });
        $(this).css({ 'border-bottom-color': '#2271b1', 'font-weight': '600' });
        // Show/hide panes
        $('.cm-lang-pane').hide();
        $('.cm-lang-pane[data-lang="' + lang + '"]').show();
        // Preview bijwerken met teksten van actief taalpaneel
        applyPreview();
    });

    /* ---- Bannertaal: radio NL/EN wisselt preview en taalpaneel direct ---- */
    // Initialiseer _activeLang op de opgeslagen waarde bij paginaload
    var $savedLangRadio = $('input[name="banner_language"]:checked');
    if ($savedLangRadio.length) {
        _activeLang = $savedLangRadio.val();
        $('.cm-lang-tab').css({ 'border-bottom-color': 'transparent', 'font-weight': '400' });
        $('.cm-lang-tab[data-lang="' + _activeLang + '"]').css({ 'border-bottom-color': '#2271b1', 'font-weight': '600' });
        $('.cm-lang-pane').hide();
        $('.cm-lang-pane[data-lang="' + _activeLang + '"]').show();
    }

    $(document).on('change', 'input[name="banner_language"]', function() {
        var lang = $(this).val();
        _activeLang = lang;
        $('.cm-lang-tab').css({ 'border-bottom-color': 'transparent', 'font-weight': '400' });
        $('.cm-lang-tab[data-lang="' + lang + '"]').css({ 'border-bottom-color': '#2271b1', 'font-weight': '600' });
        $('.cm-lang-pane').hide();
        $('.cm-lang-pane[data-lang="' + lang + '"]').show();
        applyPreview();
    });

    /* ---- Dark mode: toon/verberg kleurvelden ---- */
    // Thema standaard kleuren herstellen
    $(document).on('click', '.cm-reset-theme-defaults', function() {
        var theme = $(this).data('theme');
        var label = theme === 'dark' ? 'Dark' : 'Light';
        if (!confirm('Alle ' + label + '-kleuren terugzetten naar standaard? Dit kan niet ongedaan worden gemaakt.')) return;
        var $btn = $(this).prop('disabled', true).text('Bezig...');
        $.post(CM_DATA.ajax_url, { action: 'cm_reset_theme_defaults', nonce: CM_DATA.nonce, theme: theme }, function(r) {
            if (r && r.success) {
                var defs = r.data.defaults;
                var prefix = theme === 'dark' ? 'dm_' : 'color_';
                // Update alle kleurpickers in de pane
                $.each(defs, function(key, val) {
                    if (theme === 'dark' && key.indexOf('dm_') !== 0) return;
                    if (theme === 'light' && key.indexOf('dm_') === 0) return;
                    if (theme === 'light' && key.indexOf('color_') !== 0 && key.indexOf('radius_') !== 0 && key !== 'overlay_opacity') return;
                    var $picker = $('[name="' + key + '"].cm-color-picker');
                    var $hex    = $('[data-for="' + key + '"].cm-hex-input');
                    var $num    = $('[name="' + key + '"].cm-number-input');
                    var $range  = $('[name="' + key + '"].cm-range, input[name="' + key + '"][type="range"]');
                    if ($picker.length && val && val.toString().indexOf('#') === 0) {
                        $picker.val(val).trigger('input');
                        $hex.val(val);
                    }
                    if ($num.length) $num.val(val).trigger('input');
                    if ($range.length) $range.val(val).trigger('input');
                });
                if (typeof applyPreview === 'function') applyPreview();
                $btn.text('✓ Hersteld');
                setTimeout(function() { $btn.prop('disabled', false).text('↺ Standaard kleuren herstellen'); }, 2000);
            }
        }).fail(function() { $btn.prop('disabled', false).text('↺ Standaard kleuren herstellen'); });
    });
    $(document).on('click', '.cm-theme-switcher .cm-theme-tab[data-theme-tab]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var tab = $(this).data('theme-tab');

        // Styling: geselecteerde tab = grijs (actief), andere = wit (inactief)
        $('.cm-theme-switcher .cm-theme-tab').each(function() {
            var isSelected = $(this).data('theme-tab') === tab;
            var label = $(this).data('theme-tab') === 'light' ? '☀️ Light' : '🌙 Dark';
            $(this).css({
                background: isSelected ? '#f6f7f7' : '#fff',
                color:      isSelected ? '#787c82' : '#1d2327'
            }).html((isSelected ? '✓ ' : '') + label);
        });

        // Panes tonen/verbergen
        $('.cm-theme-pane').addClass('cm-theme-hidden');
        $('#cm-theme-pane-' + tab).removeClass('cm-theme-hidden');

        // Hidden input updaten zodat opslaan werkt
        $('#cm-color-theme-value').val(tab);

        // Preview updaten
        if (typeof applyPreview === 'function') applyPreview();
    });

    /* ---- Geo-targeting: toon/verberg overige landen optie ---- */
    $(document).on('change', 'input[name="geo_enabled"]', function() {
        $('#cm-geo-outside-row').toggle($(this).val() === '1');
    });

    /* ---- Subdomain sharing: toon/verberg root-domein veld ---- */
    $(document).on('change', '#cm-subdomain-sharing-cb', function() {
        $('#cm-subdomain-root-row').toggle(this.checked);
    });

    /* ---- Licentie knoppen ---- */
    $(document).on('click', '#cm-license-activate', function() {
        var key = $('#cm_license_key').val().trim();
        if (!key) { $('#cm-license-status').text('Vul een licentiesleutel in.').css('color','#b32d2e'); return; }
        var $btn = $(this).prop('disabled', true).text('Activeren...');
        $.post(CM_DATA.ajax_url, { action: 'cm_license_activate', nonce: CM_DATA.nonce, license_key: key }, function(r) {
            $btn.prop('disabled', false).text('Activeren');
            if (r.success) {
                $('#cm-license-status').text(r.data.msg).css('color','#00a32a');
                setTimeout(function(){ location.reload(); }, 1000);
            } else {
                $('#cm-license-status').text(r.data.msg).css('color','#b32d2e');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Activeren');
            $('#cm-license-status').text('Verbinding met licentieserver mislukt.').css('color','#b32d2e');
        });
    });

    $(document).on('click', '#cm-license-deactivate', function() {
        if (!confirm('Weet u zeker dat u de licentie wilt deactiveren? De banner stopt direct.')) return;
        var $btn = $(this).prop('disabled', true).text('Deactiveren...');
        $.post(CM_DATA.ajax_url, { action: 'cm_license_deactivate', nonce: CM_DATA.nonce }, function(r) {
            $btn.prop('disabled', false).text('Deactiveren');
            if (r.success) {
                $('#cm-license-status').text(r.data.msg).css('color','#00a32a');
                setTimeout(function(){ location.reload(); }, 1000);
            } else {
                $('#cm-license-status').text(r.data.msg).css('color','#b32d2e');
            }
        });
    });

    $(document).on('click', '#cm-license-check', function() {
        var $btn = $(this).prop('disabled', true).text('Controleren...');
        $.post(CM_DATA.ajax_url, { action: 'cm_license_check', nonce: CM_DATA.nonce }, function(r) {
            $btn.prop('disabled', false).text('Status controleren');
            if (r.success) {
                $('#cm-license-status').text('Status: ' + r.data.status).css('color', r.data.status === 'active' ? '#00a32a' : '#b32d2e');
                setTimeout(function(){ location.reload(); }, 1500);
            }
        });
    });

    $(document).on('click', '#cm-license-save-url', function() {
        var url = $('#cm_license_api_url').val().trim();
        $.post(CM_DATA.ajax_url, { action: 'cm_save_license_url', nonce: CM_DATA.nonce, api_url: url }, function(r) {
            if (r.success) {
                $('#cm-license-status').text('API-URL opgeslagen.').css('color','#00a32a');
            }
        });
    });

    /* ---- INIT ---- */
    $(function(){
        applyPreview();
        cmToggleFloatIconRows();
    });

    /* ================================================================
       TAARTDIAGRAM
    ================================================================ */
    function cmRenderPieChart(stats) {
        var canvas = document.getElementById('cm-consent-chart');
        if (!canvas || !canvas.getContext) return;

        var accept = parseInt(stats.accept_all) || 0;
        var reject = parseInt(stats.reject_all) || 0;
        var custom = parseInt(stats.custom)     || 0;
        var total  = accept + reject + custom; // alleen actieve keuzes, geen pageloads

        if (total === 0) {
            $(canvas).hide();
            $('#cm-chart-legend').hide();
            $('#cm-chart-empty').show();
            return;
        }
        $(canvas).show();
        $('#cm-chart-legend').show();
        $('#cm-chart-empty').hide();

        var ctx    = canvas.getContext('2d');
        var cx     = canvas.width  / 2;
        var cy     = canvas.height / 2;
        var radius = Math.min(cx, cy) - 8;

        var slices = [
            { label: 'Alles akkoord',   value: accept, color: '#00a32a' },
            { label: 'Alles geweigerd', value: reject, color: '#b32d2e' },
            { label: 'Aangepast',       value: custom, color: '#2271b1' },
        ].filter(function(s) { return s.value > 0; });

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        var startAngle = -Math.PI / 2;
        $.each(slices, function(i, slice) {
            var sliceAngle = (slice.value / total) * 2 * Math.PI;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, startAngle, startAngle + sliceAngle);
            ctx.closePath();
            ctx.fillStyle = slice.color;
            ctx.fill();
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.stroke();
            startAngle += sliceAngle;
        });

        // Donut gat
        ctx.beginPath();
        ctx.arc(cx, cy, radius * 0.52, 0, 2 * Math.PI);
        ctx.fillStyle = '#fff';
        ctx.fill();

        // Totaal actieve keuzes in midden
        ctx.fillStyle = '#1d2327';
        ctx.font = 'bold 22px -apple-system, BlinkMacSystemFont, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(total, cx, cy - 8);
        ctx.font = '11px -apple-system, BlinkMacSystemFont, sans-serif';
        ctx.fillStyle = '#787c82';
        ctx.fillText('keuzes', cx, cy + 10);

        // Legenda
        var legendHtml = '';
        $.each(slices, function(i, slice) {
            var pct = Math.round((slice.value / total) * 100);
            legendHtml += '<div style="display:flex;align-items:center;gap:7px">'
                + '<span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:' + slice.color + ';flex-shrink:0"></span>'
                + '<span style="color:#444">' + slice.label + '</span>'
                + '<span style="color:#787c82;margin-left:auto;padding-left:12px">' + pct + '%</span>'
                + '</div>';
        });
        $('#cm-chart-legend').html(legendHtml);
    }

    /* ================================================================
       CONSENT LOG TAB
    ================================================================ */
    var cmLogPage = 1;
    var cmLogSearch = '';

    function cmLoadLog(page) {
        page = page || 1;
        cmLogPage = page;
        $('#cm-log-loading').show();
        $('#cm-log-table').hide();
        $('#cm-log-pagination').hide();

        $.post(CM_DATA.ajax_url, {
            action: 'cm_get_log',
            nonce:  CM_DATA.nonce,
            page:   page,
            search: cmLogSearch,
        }, function(r) {
            if (!r.success) {
                $('#cm-log-rows').html('<tr><td colspan="6" style="color:#b32d2e;padding:16px;text-align:center">Fout bij laden van de log.</td></tr>');
                return;
            }
            var d = r.data;

            // Stats — nieuwe visuele kaarten met iconen
            if (d.stats) {
                var total = (parseInt(d.stats.accept_all)||0) + (parseInt(d.stats.reject_all)||0) + (parseInt(d.stats.custom)||0);
                var pctA = total > 0 ? Math.round((d.stats.accept_all||0) / total * 100) : 0;
                var pctR = total > 0 ? Math.round((d.stats.reject_all||0) / total * 100) : 0;
                var pctC = total > 0 ? Math.round((d.stats.custom||0) / total * 100) : 0;
                $('#stat-accept').text(d.stats.accept_all || 0);
                $('#stat-reject').text(d.stats.reject_all || 0);
                $('#stat-custom').text(d.stats.custom || 0);
                // Update labels met percentage als er data is
                if (total > 0) {
                    $('#stat-accept').closest('div').find('div:last').text('Akkoord (' + pctA + '%)');
                    $('#stat-reject').closest('div').find('div:last').text('Geweigerd (' + pctR + '%)');
                    $('#stat-custom').closest('div').find('div:last').text('Aangepast (' + pctC + '%)');
                }
            }

            // Status mapping met zijstreep-kleuren
            var statusMap = {
                'accept-all': { label: 'Akkoord',     bg: '#d1f0da', color: '#00561b', stripe: '#00a32a' },
                'reject-all': { label: 'Geweigerd',   bg: '#fcebeb', color: '#d63638', stripe: '#d63638' },
                'custom':     { label: 'Aangepast',    bg: '#dce9f8', color: '#0a4480', stripe: '#2271b1' },
                'pageload':   { label: 'Terugkerend',  bg: '#f6f7f7', color: '#787c82', stripe: '#c3c4c7' }
            };

            var $tbody = $('#cm-log-rows');
            if (!d.rows || d.rows.length === 0) {
                $tbody.html('<tr><td colspan="6" style="color:#787c82;padding:20px;text-align:center;font-size:13px">Geen resultaten gevonden.</td></tr>');
            } else {
                var html = '';
                $.each(d.rows, function(i, row) {
                    var dt    = row.created_at || '';
                    // Toon datum direct uit database (WordPress lokale tijd)
                    // Format: "2026-03-19 19:39:00" → "19 mrt 2026 19:39"
                    var dtStr = dt;
                    try {
                        var parts = dt.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/);
                        if (parts) {
                            var maanden = ['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec'];
                            dtStr = parseInt(parts[3]) + ' ' + maanden[parseInt(parts[2])-1] + ' ' + parts[1] + ' ' + parts[4] + ':' + parts[5];
                        }
                    } catch(e) {}
                    var cid      = row.consent_id || '—';
                    var cidShort = cid.length > 24 ? cid.substring(0,24) + '…' : cid;
                    var sm       = statusMap[row.method] || { label: row.method, bg: '#f6f7f7', color: '#3c434a', stripe: '#c3c4c7' };
                    var badge    = '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:' + sm.bg + ';color:' + sm.color + ';font-size:11px;font-weight:600">' + sm.label + '</span>';
                    var details  = 'Analytics: ' + (row.analytics == 1 ? 'ja' : 'nee') + ', Marketing: ' + (row.marketing == 1 ? 'ja' : 'nee');
                    if (row.plugin_version) details += ' — v' + $('<div>').text(row.plugin_version).html();
                    var proofBtn = '<a href="#" class="cm-proof-btn" data-id="' + $('<div>').text(cid).html() + '" data-date="' + $('<div>').text(row.created_at).html() + '" data-method="' + row.method + '" data-analytics="' + row.analytics + '" data-marketing="' + row.marketing + '" data-version="' + $('<div>').text(row.plugin_version || '').html() + '" style="color:#2271b1;text-decoration:none;font-size:12px">Bewijs</a>';
                    var delBtn   = '<a href="#" class="cm-log-delete-btn" data-id="' + $('<div>').text(cid).html() + '" style="color:#d63638;text-decoration:none;font-size:12px;margin-left:8px">Wissen</a>';

                    html += '<tr data-consent-id="' + $('<div>').text(cid).html() + '" data-method="' + row.method + '" style="border-bottom:1px solid #f0f0f1">' +
                        '<td style="width:4px;padding:0"><div style="width:3px;height:100%;min-height:36px;background:' + sm.stripe + ';border-radius:0 2px 2px 0"></div></td>' +
                        '<td style="padding:8px 10px;font-size:12px;font-family:monospace;white-space:nowrap;color:#646970" title="' + $('<div>').text(cid).html() + '">' + $('<div>').text(cidShort).html() + '</td>' +
                        '<td style="padding:8px 10px">' + badge + '</td>' +
                        '<td style="padding:8px 10px;font-size:11px;color:#787c82">' + details + '</td>' +
                        '<td style="padding:8px 10px;font-size:12px;white-space:nowrap;color:#646970">' + dtStr + '</td>' +
                        '<td style="padding:8px 10px;white-space:nowrap">' + proofBtn + delBtn + '</td>' +
                    '</tr>';
                });
                $tbody.html(html);
            }

            // Paginering
            $('#cm-log-pagination').empty();
            if (d.pages > 1) {
                var pHtml = '';
                // Pagina-nummers
                var startP = Math.max(1, d.page - 2);
                var endP = Math.min(d.pages, d.page + 2);
                if (startP > 1) pHtml += '<button type="button" class="button button-small cm-log-page" data-page="1" style="min-width:28px;text-align:center">1</button>';
                if (startP > 2) pHtml += '<span style="color:#787c82">&hellip;</span>';
                for (var p = startP; p <= endP; p++) {
                    if (p === d.page) {
                        pHtml += '<button type="button" class="button button-small button-primary" style="min-width:28px;text-align:center;background:#2271b1;border-color:#2271b1;color:#fff" disabled>' + p + '</button>';
                    } else {
                        pHtml += '<button type="button" class="button button-small cm-log-page" data-page="' + p + '" style="min-width:28px;text-align:center">' + p + '</button>';
                    }
                }
                if (endP < d.pages - 1) pHtml += '<span style="color:#787c82">&hellip;</span>';
                if (endP < d.pages) pHtml += '<button type="button" class="button button-small cm-log-page" data-page="' + d.pages + '" style="min-width:28px;text-align:center">' + d.pages + '</button>';
                pHtml += '<span style="margin-left:auto;color:#787c82">' + d.total + ' records</span>';
                $('#cm-log-pagination').html(pHtml).show();
            } else if (d.total > 0) {
                $('#cm-log-pagination').html('<span style="margin-left:auto;color:#787c82">' + d.total + ' records</span>').show();
            }

        }).fail(function(xhr) {
            $('#cm-log-rows').html('<tr><td colspan="6" style="color:#b32d2e;padding:16px;text-align:center">Verbindingsfout (HTTP ' + xhr.status + ').</td></tr>');
        }).always(function() {
            $('#cm-log-loading').hide();
            $('#cm-log-table').show();
        });
    }

    // Verwijder individueel log-record (AVG art. 17 recht op vergetelheid)
    $(document).on('click', '.cm-log-delete-btn', function() {
        var cid  = $(this).data('id');
        var $row = $(this).closest('tr');
        if (!confirm('Verwijder dit consent-record?\nConsent ID: ' + cid + '\n\nDit kan niet ongedaan worden gemaakt.')) return;
        var $btn = $(this).prop('disabled', true).text('…');
        $.post(CM_DATA.ajax_url, {
            action:     'cm_delete_log_row',
            nonce:      CM_DATA.nonce,
            consent_id: cid
        }, function(r) {
            if (r && r.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('Verwijderen mislukt.');
                $btn.prop('disabled', false).html('&#x2715;');
            }
        }).fail(function() {
            alert('Verbindingsfout.');
            $btn.prop('disabled', false).html('&#x2715;');
        });
    });

    // Vernieuwen
    $(document).on('click', '#cm-log-refresh-btn', function() {
        cmLogSearch = $('#cm-log-search').val().trim();
        cmLoadLog(1);
    });

    // Zoeken — debounce 500ms
    var cmSearchTimer;
    $(document).on('input', '#cm-log-search', function() {
        clearTimeout(cmSearchTimer);
        var val = $(this).val().trim();
        cmSearchTimer = setTimeout(function() { cmLogSearch = val; cmLoadLog(1); }, 500);
    });
    $(document).on('keydown', '#cm-log-search', function(e) {
        if (e.key === 'Enter') { clearTimeout(cmSearchTimer); cmLogSearch = $(this).val().trim(); cmLoadLog(1); }
    });

    // ---- Bewijs van consent popup ----
    var statusLabels = { 'accept-all':'Geaccepteerd','reject-all':'Geweigerd','custom':'Aangepast','pageload':'Terugkerend' };
    $(document).on('click', '.cm-proof-btn', function() {
        var cid       = $(this).data('id');
        var date      = $(this).data('date');
        var method    = $(this).data('method');
        var analytics = $(this).data('analytics');
        var marketing = $(this).data('marketing');
        var version   = $(this).data('version') || '';
        var dt        = String(date);
        var dtStr     = dt;
        try {
            var parts = dt.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):?(\d{2})?/);
            if (parts) {
                var maanden = ['januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];
                dtStr = parseInt(parts[3]) + ' ' + maanden[parseInt(parts[2])-1] + ' ' + parts[1] + ' ' + parts[4] + ':' + parts[5] + (parts[6] ? ':' + parts[6] : '');
            }
        } catch(e) {}
        $('#cm-proof-id').text(cid);
        $('#cm-proof-date').text(dtStr);
        $('#cm-proof-status').text(statusLabels[method] || method);
        $('#cm-proof-analytics').text(analytics == 1 ? 'Ja' : 'Nee');
        $('#cm-proof-marketing').text(marketing == 1 ? 'Ja' : 'Nee');
        $('#cm-proof-version').text(version || '—');
        $('#cm-proof-overlay').css('display','flex');
        // Sla data op voor PDF
        $('#cm-proof-overlay').data('row', { id: cid, date: dtStr, method: method, analytics: analytics, marketing: marketing, version: version });
    });
    $(document).on('click', '#cm-proof-cancel', function() { $('#cm-proof-overlay').hide(); });
    $(document).on('click', '#cm-proof-overlay', function(e) { if ($(e.target).is('#cm-proof-overlay')) $(this).hide(); });

    // PDF genereren — print-vriendelijke pagina
    $(document).on('click', '#cm-proof-pdf', function() {
        var row = $('#cm-proof-overlay').data('row');
        var site = window.location.hostname;
        var statusL = statusLabels[row.method] || row.method;
        var html = '<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><title>Bewijs van consent</title>'
            + '<style>body{font-family:Arial,sans-serif;padding:40px;color:#222;max-width:600px;margin:0 auto}'
            + 'h1{font-size:22px;margin-bottom:8px}p.sub{color:#787c82;font-size:13px;margin-bottom:32px}'
            + 'table{width:100%;border-collapse:collapse;font-size:14px}'
            + 'td{padding:10px 12px;border-bottom:1px solid #eee;vertical-align:top}'
            + 'td:first-child{color:#787c82;width:160px;font-weight:600}'
            + '.footer{margin-top:40px;font-size:11px;color:#aaa;border-top:1px solid #eee;padding-top:12px}'
            + '@media print{body{padding:20px}}</style></head><body>'
            + '<h1>Bewijs van consent</h1><p class="sub">Gegenereerd door Cookiebaas &mdash; ' + site + '</p>'
            + '<table>'
            + '<tr><td>Consent ID</td><td style="font-family:monospace">' + row.id + '</td></tr>'
            + '<tr><td>Consent datum</td><td>' + row.date + '</td></tr>'
            + '<tr><td>Status</td><td>' + statusL + '</td></tr>'
            + '<tr><td>Analytisch</td><td>' + (row.analytics == 1 ? 'Ja' : 'Nee') + '</td></tr>'
            + '<tr><td>Marketing</td><td>' + (row.marketing == 1 ? 'Ja' : 'Nee') + '</td></tr>'
            + '<tr><td>Plugin versie</td><td>' + (row.version || '—') + '</td></tr>'
            + '</table>'
            + '<p class="footer">Dit document dient als bewijs dat de betreffende bezoeker toestemming heeft gegeven of geweigerd conform de AVG/GDPR.</p>'
            + '</body></html>';
        var w = window.open('', '_blank');
        w.document.write(html);
        w.document.close();
        w.focus();
        setTimeout(function(){ w.print(); }, 400);
        $('#cm-proof-overlay').hide();
    });

    // ---- CSV export met datumkeuze ----
    $(document).on('click', '#cm-log-export-btn', function(e) {
        e.preventDefault();
        // Standaard: laatste 30 dagen
        var today = new Date();
        var from30 = new Date(today); from30.setDate(today.getDate() - 30);
        var fmt = function(d){ return d.toISOString().slice(0,10); };
        $('#cm-export-from').val(fmt(from30));
        $('#cm-export-to').val(fmt(today));
        $('#cm-export-overlay').css('display','flex');
    });
    $(document).on('click', '#cm-export-cancel', function() { $('#cm-export-overlay').hide(); });
    $(document).on('click', '#cm-export-overlay', function(e) { if ($(e.target).is('#cm-export-overlay')) $(this).hide(); });
    $(document).on('click', '#cm-export-confirm', function() {
        var from = $('#cm-export-from').val();
        var to   = $('#cm-export-to').val();
        var url  = CM_DATA.ajax_url + '?action=cm_export_log_csv&nonce=' + encodeURIComponent(CM_DATA.nonce)
                 + (from ? '&from=' + encodeURIComponent(from) : '')
                 + (to   ? '&to='   + encodeURIComponent(to)   : '');
        window.location.href = url;
        $('#cm-export-overlay').hide();
    });

    // Log leegmaken
    $(document).on('click', '#cm-log-clear-btn', function() {
        if (!confirm('Weet u zeker dat u alle consent-logs wilt verwijderen? Dit kan niet ongedaan worden gemaakt.')) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(CM_DATA.ajax_url, { action: 'cm_clear_log', nonce: CM_DATA.nonce }, function(r) {
            if (r.success) { cmLoadLog(1); }
        }).always(function() { $btn.prop('disabled', false); });
    });

    // Paginering
    $(document).on('click', '.cm-log-page', function() {
        cmLoadLog(parseInt($(this).attr('data-page')));
    });

    // ---- Filter pillen ----
    var cmLogFilterMethod = 'all';
    $(document).on('click', '.cm-log-filter', function() {
        var filter = $(this).data('filter');
        cmLogFilterMethod = filter;
        // Update actieve pill styling
        $('.cm-log-filter').each(function() {
            var f = $(this).data('filter');
            var colors = { 'all':'#2271b1', 'accept-all':'#00a32a', 'reject-all':'#d63638', 'custom':'#2271b1' };
            var c = colors[f] || '#2271b1';
            if (f === filter) {
                $(this).css({ background: c, color: '#fff', borderColor: c });
            } else {
                $(this).css({ background: '#fff', color: c, borderColor: c });
            }
        });
        // Filter rijen client-side (sneller dan server request)
        if (filter === 'all') {
            $('#cm-log-rows tr').show();
        } else {
            $('#cm-log-rows tr').each(function() {
                var method = $(this).data('method');
                $(this).toggle(method === filter);
            });
        }
    });

    // Auto-laden bij pagina open
    if ($('#cm-log-table').length) { cmLoadLog(1); }

    // Laad log bij tabwissel
    $(document).on('click', '[data-tab="logging"]', function() { cmLoadLog(1); });

})(jQuery);
