/* =========================================
   Sync Modal Controller, Premium UX
   ========================================= */
(function () {
    'use strict';

    const root = document.querySelector('[data-rhbp-sync]');
    if (!root) {
        return;
    }

    const ajaxUrl = root.dataset.ajaxUrl;
    const nonce = root.dataset.ajaxNonce;
    const modal = root.querySelector('[data-rhbp-modal]');
    if (!modal || !ajaxUrl || !nonce) {
        return;
    }

    const GROUP_LABELS = {
        content: 'Inhalte',
        taxonomies: 'Taxonomien',
        comments: 'Kommentare',
        users: 'Benutzer',
        options: 'Einstellungen',
        links: 'Links',
        customTables: 'Custom-Tabellen',
        uploads: 'Mediathek-Dateien',
    };

    const PHASE_LABELS = {
        manifest: 'Verbindung prüfen',
        export: 'Snapshot erstellen',
        upload: 'Daten hochladen',
        download: 'Daten herunterladen',
        safety: 'Sicherheits-Backup',
        import: 'Daten einspielen',
    };

    // ---- Profil-Form: Schnellwahl-Presets ----
    // Das Profil-Form lebt im Einstellungen-Modal des Peers. Die is-checked-Optik
    // der Optionen uebernimmt der generische Option-Toggle (siehe UI-Mechanik unten),
    // hier wird nur die Schnellwahl verdrahtet.

    const PROFILE_PRESETS = {
        all: { content: true, taxonomies: true, comments: true, users: true, options: true, links: true, customTables: true, uploads: true },
        'no-users': { content: true, taxonomies: true, comments: true, users: false, options: true, links: true, customTables: true, uploads: true },
        'content-only': { content: true, taxonomies: true, comments: false, users: false, options: false, links: false, customTables: false, uploads: true },
        'db-only': { content: true, taxonomies: true, comments: true, users: true, options: true, links: true, customTables: true, uploads: false },
    };

    function setupProfileForm(form) {
        const checkboxes = Array.from(form.querySelectorAll('input[type="checkbox"][data-profile-flag]'));

        form.querySelectorAll('[data-preset]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const cfg = PROFILE_PRESETS[btn.dataset.preset];
                if (!cfg) return;
                checkboxes.forEach((cb) => {
                    cb.checked = !!cfg[cb.dataset.profileFlag];
                    const option = cb.closest('.rhbp-option');
                    if (option) {
                        option.classList.toggle('is-checked', cb.checked);
                    }
                });
            });
        });
    }

    // ---- Modal Logic ----

    let activeJobId = null;
    let pollTimer = null;
    let elapsedTimer = null;
    let modalAction = null; // 'pull' | 'push'
    let activePeer = null;

    function setState(state) {
        modal.querySelectorAll('[data-state]').forEach((el) => {
            el.hidden = el.dataset.state !== state;
        });
    }

    function setIcon(variant, dashicon) {
        const iconWrap = modal.querySelector('[data-modal-icon]');
        if (!iconWrap) return;
        iconWrap.classList.remove('rhbp-modal__head-icon--ok', 'rhbp-modal__head-icon--err');
        if (variant === 'success') iconWrap.classList.add('rhbp-modal__head-icon--ok');
        if (variant === 'error') iconWrap.classList.add('rhbp-modal__head-icon--err');
        iconWrap.innerHTML = '<span class="dashicons dashicons-' + dashicon + '" aria-hidden="true"></span>';
    }

    function setFooterButtons(visible) {
        ['cancel', 'confirm', 'retry', 'finish', 'login'].forEach((key) => {
            const sel = key === 'cancel' ? '[data-modal-close]' : '[data-modal-' + key + ']';
            const buttons = modal.querySelectorAll('.rhbp-modal__foot ' + sel);
            buttons.forEach((b) => {
                b.hidden = !visible.includes(key);
            });
        });
    }

    function openModal(action, peerId, peerName) {
        modalAction = action;
        activePeer = { id: peerId, name: peerName };

        modal.hidden = false;
        document.body.style.overflow = 'hidden';

        const verb = action === 'pull' ? 'Pull' : 'Push';
        const dir = action === 'pull' ? 'von' : 'zu';
        modal.querySelector('[data-modal-title]').textContent = verb + ' ' + dir + ' „' + peerName + '"';
        modal.querySelector('[data-modal-subtitle]').textContent = 'Vorbereitung läuft...';
        setIcon('default', action === 'pull' ? 'download' : 'upload');
        setState('loading');
        setFooterButtons(['cancel']);

        fetchPreflight(peerId);
    }

    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        stopPolling();
        stopElapsed();
        activeJobId = null;
        modalAction = null;
        activePeer = null;
    }

    function stopPolling() {
        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }
    }

    function stopElapsed() {
        if (elapsedTimer) {
            clearInterval(elapsedTimer);
            elapsedTimer = null;
        }
    }

    async function ajax(action, params = {}, method = 'GET') {
        const url = new URL(ajaxUrl, window.location.origin);
        url.searchParams.set('action', action);
        url.searchParams.set('nonce', nonce);

        let response;
        if (method === 'GET') {
            Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
            response = await fetch(url.toString(), { credentials: 'same-origin' });
        } else {
            const body = new URLSearchParams();
            Object.entries(params).forEach(([k, v]) => body.set(k, v));
            response = await fetch(url.toString(), {
                method: 'POST',
                credentials: 'same-origin',
                body: body,
            });
        }

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const msg = data && data.data && data.data.message ? data.data.message : 'HTTP ' + response.status;
            throw new Error(msg);
        }
        const data = await response.json();
        if (!data.success) {
            throw new Error((data.data && data.data.message) || 'Unbekannter Fehler');
        }
        return data.data;
    }

    function formatBytes(bytes) {
        if (!bytes || bytes < 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let n = bytes;
        let i = 0;
        while (n >= 1024 && i < units.length - 1) {
            n /= 1024;
            i++;
        }
        return n.toFixed(n >= 100 || i === 0 ? 0 : n >= 10 ? 1 : 2) + ' ' + units[i];
    }

    function formatDuration(ms) {
        if (!ms || ms < 0) return '0 ms';
        if (ms < 1000) return ms + ' ms';
        const seconds = ms / 1000;
        if (seconds < 60) return seconds.toFixed(seconds >= 10 ? 0 : 1) + ' s';
        const minutes = Math.floor(seconds / 60);
        const rem = Math.round(seconds % 60);
        return minutes + ':' + String(rem).padStart(2, '0') + ' min';
    }

    function renderProfileList(container, profile) {
        container.innerHTML = '';
        Object.entries(GROUP_LABELS).forEach(([key, label]) => {
            const on = !!profile[key];
            const item = document.createElement('div');
            item.className = 'rhbp-modal-profile-item ' + (on ? 'is-on' : 'is-off');
            item.innerHTML = '<span class="dashicons dashicons-' + (on ? 'yes' : 'no-alt') + '" aria-hidden="true"></span><span>' + label + '</span>';
            container.appendChild(item);
        });
    }

    async function fetchPreflight(peerId) {
        try {
            const data = await ajax('rhbp_peer_preflight', { peer_id: peerId }, 'GET');
            renderPreflight(data);
        } catch (err) {
            renderError('Verbindung zur Quelle fehlgeschlagen', err.message, 'manifest');
        }
    }

    function renderPreflight(data) {
        const manifest = data.manifest || {};
        const profile = data.profile || {};
        const peer = data.peer || {};

        modal.querySelector('[data-modal-subtitle]').textContent =
            (manifest.wp_version ? 'WordPress ' + manifest.wp_version : '') +
            (manifest.plugin_version ? ' · Plugin ' + manifest.plugin_version : '');

        const sourceEl = modal.querySelector('[data-source]');
        sourceEl.innerHTML = '<a href="' + peer.url + '" target="_blank" rel="noopener">' + peer.url + '</a>' +
            (manifest.last_modified ? '<span class="rhbp-sync-modal__source-meta">Letzte Änderung: ' + manifest.last_modified + '</span>' : '');

        const stats = modal.querySelector('[data-source-stats]');
        stats.innerHTML = '';
        const statRows = [
            { label: 'Beiträge', value: manifest.post_count != null ? manifest.post_count : ', ' },
            { label: 'DB-Größe', value: manifest.db_size ? formatBytes(manifest.db_size) : ', ' },
            { label: 'Mediathek', value: manifest.uploads_size ? formatBytes(manifest.uploads_size) : ', ' },
        ];
        statRows.forEach((s) => {
            const el = document.createElement('div');
            el.className = 'rhbp-stat';
            el.innerHTML = '<span class="rhbp-stat__label">' + s.label + '</span><span class="rhbp-stat__value">' + s.value + '</span>';
            stats.appendChild(el);
        });

        renderProfileList(modal.querySelector('[data-profile-list]'), profile);

        // Critical-Warning nur bei Pull + users:true (Session wird gekillt)
        const criticalWarn = modal.querySelector('[data-critical-warn]');
        if (criticalWarn) {
            const willKillSession = modalAction === 'pull' && profile.users === true;
            criticalWarn.hidden = !willKillSession;
        }

        setState('preflight');
        setFooterButtons(['cancel', 'confirm']);
        modal._profile = profile;
    }

    function startSync() {
        if (!activePeer || !modalAction) return;

        // Pull mit users:true überschreibt unsere Session. Polling wäre sinnlos
        // weil admin-ajax die wp_ajax_*-Handler nur für eingeloggte User registriert.
        // Stattdessen Standalone-Mode mit Logout-Hinweis.
        const willKillSession = modalAction === 'pull' && modal._profile && modal._profile.users === true;
        if (willKillSession) {
            startStandaloneSync();
            return;
        }

        startProgressSync();
    }

    function startProgressSync() {
        setState('progress');
        setFooterButtons(['cancel']);
        renderInitialSteps(modal._profile);

        const startTime = Date.now();
        startElapsed(startTime);

        const ajaxAction = modalAction === 'pull' ? 'rhbp_peer_pull_async' : 'rhbp_peer_push_async';
        ajax(ajaxAction, { peer_id: activePeer.id }, 'POST')
            .then((data) => {
                activeJobId = data.job_id;
                pollStatus();
            })
            .catch((err) => {
                renderError('Sync konnte nicht gestartet werden', err.message, null);
            });
    }

    function startStandaloneSync() {
        // Peer-Name ins Standalone-Template
        const peerEl = modal.querySelector('[data-standalone-peer]');
        if (peerEl && activePeer) {
            peerEl.textContent = '„' + activePeer.name + '"';
        }

        modal.querySelector('[data-modal-subtitle]').textContent = 'Im Hintergrund, du wirst gleich abgemeldet';

        setState('standalone');
        setFooterButtons(['login']);

        // Login-Button erst nach 5s aktivieren (Backend braucht Vorlauf bis Daten kopiert sind)
        const loginBtn = modal.querySelector('[data-modal-login]');
        if (loginBtn) {
            loginBtn.disabled = true;
            setTimeout(() => {
                loginBtn.disabled = false;
            }, 5000);
        }

        // Backend triggern, Connection nicht beobachten (stirbt sowieso beim User-Import).
        // Der eigentliche Sync läuft via fastcgi_finish_request unabhaengig weiter.
        ajax('rhbp_peer_pull_async', { peer_id: activePeer.id }, 'POST').catch(() => {
            // Fehler hier sind erwartet, nicht anzeigen. User sieht das Ergebnis nach Re-Login im Verlauf.
        });
    }

    function renderInitialSteps(profile) {
        const stepIds = modalAction === 'pull'
            ? ['manifest', 'export', 'download', 'safety', 'import']
            : ['export', 'upload', 'import'];

        const list = modal.querySelector('[data-steps]');
        list.innerHTML = '';
        stepIds.forEach((id) => {
            const li = document.createElement('li');
            li.className = 'rhbp-step';
            li.dataset.stepId = id;
            li.innerHTML = '<div class="rhbp-step__marker"><span class="dashicons dashicons-marker" aria-hidden="true"></span></div>' +
                '<div class="rhbp-step__content"><div class="rhbp-step__label">' + (PHASE_LABELS[id] || id) + '</div></div>';
            list.appendChild(li);
        });

        const summary = modal.querySelector('[data-profile-summary]');
        if (summary && profile) {
            const active = Object.entries(profile).filter(([, v]) => v).map(([k]) => GROUP_LABELS[k] || k);
            summary.textContent = active.length === Object.keys(GROUP_LABELS).length ? 'Voll, alle Bereiche' : active.join(', ');
        }
    }

    function startElapsed(startTime) {
        stopElapsed();
        const el = modal.querySelector('[data-elapsed]');
        const update = () => {
            if (!el) return;
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const min = Math.floor(elapsed / 60);
            const sec = elapsed % 60;
            el.textContent = 'Läuft seit ' + min + ':' + String(sec).padStart(2, '0');
        };
        update();
        elapsedTimer = setInterval(update, 1000);
    }

    async function pollStatus() {
        if (!activeJobId) return;

        try {
            const status = await ajax('rhbp_sync_status', { job_id: activeJobId }, 'GET');
            updateProgressUI(status);
            updateStallNotice(status);

            if (status.phase === 'done') {
                stopElapsed();
                renderSuccess(status);
                return;
            }
            if (status.phase === 'failed') {
                stopElapsed();
                renderFailure(status);
                return;
            }

            pollTimer = setTimeout(pollStatus, 1200);
        } catch (err) {
            // 404 = Job nicht mehr da, eventuell schon fertig und cleared
            if (err.message.includes('nicht gefunden')) {
                stopPolling();
                stopElapsed();
                renderError('Status verloren', 'Der Job-Status ist nicht mehr verfügbar. Bitte Seite neu laden um Ergebnis zu sehen.', null);
                return;
            }
            // Sonst: stilles Retry
            pollTimer = setTimeout(pollStatus, 2500);
        }
    }

    // Stillstand-Erkennung: das Backend markiert einen Job als "stale", wenn seit > 90s kein
    // Heartbeat kam (Prozess gestorben / Loopback blockiert). Statt stumm weiterzudrehen,
    // sehen wir das und bieten einen Abbruch/Neustart an. Der Cron-Watchdog versucht parallel,
    // den Job wiederzubeleben, darum läuft das Polling weiter.
    function updateStallNotice(status) {
        var running = status.phase !== 'done' && status.phase !== 'failed';
        var notice = modal.querySelector('[data-stall-notice]');

        if (!status.stale || !running) {
            if (notice) notice.hidden = true;
            return;
        }

        if (!notice) {
            notice = document.createElement('div');
            notice.setAttribute('data-stall-notice', '');
            notice.style.cssText = 'margin-top:12px;padding:10px 12px;border:1px solid #dba617;background:#fcf9e8;border-radius:6px;font-size:13px;line-height:1.5;';
            notice.innerHTML = 'Seit über einer Minute kein Fortschritt. Der Watchdog versucht, den Sync wiederzubeleben. ' +
                '<button type="button" data-stall-restart class="button-link" style="color:#b32d2e;">Abbrechen und neu starten</button>';
            var host = modal.querySelector('[data-state="progress"]') || modal.querySelector('.rhbp-modal__body') || modal;
            host.appendChild(notice);

            var btn = notice.querySelector('[data-stall-restart]');
            if (btn) {
                btn.addEventListener('click', function () {
                    if (activeJobId) ajax('rhbp_sync_clear', { job_id: activeJobId }, 'POST').catch(function () {});
                    stopPolling();
                    stopElapsed();
                    closeModal();
                });
            }
        }
        notice.hidden = false;
    }

    function updateProgressUI(status) {
        const steps = status.steps || [];
        steps.forEach((s) => {
            const li = modal.querySelector('[data-step-id="' + s.id + '"]');
            if (!li) return;

            li.classList.remove('rhbp-step--pending', 'rhbp-step--running', 'rhbp-step--done', 'rhbp-step--failed');
            li.classList.add('rhbp-step--' + s.status);

            const marker = li.querySelector('.rhbp-step__marker');
            if (s.status === 'done') {
                marker.innerHTML = '<span class="dashicons dashicons-yes" aria-hidden="true"></span>';
            } else if (s.status === 'failed') {
                marker.innerHTML = '<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>';
            } else if (s.status === 'running') {
                marker.innerHTML = '<span class="dashicons dashicons-clock" aria-hidden="true"></span>';
            } else {
                marker.innerHTML = '<span class="dashicons dashicons-marker" aria-hidden="true"></span>';
            }

            // Message + Duration
            const content = li.querySelector('.rhbp-step__content');
            const existingMsg = content.querySelector('.rhbp-step__message');
            const existingDur = content.querySelector('.rhbp-step__duration');
            const existingProgress = content.querySelector('.rhbp-step__progress');

            if (existingMsg) existingMsg.remove();
            if (existingDur) existingDur.remove();
            if (existingProgress) existingProgress.remove();

            if (s.message && (s.status === 'done' || s.status === 'failed' || s.status === 'running')) {
                const msg = document.createElement('div');
                msg.className = 'rhbp-step__message';
                msg.textContent = s.message;
                content.appendChild(msg);
            }

            if (s.duration_ms != null && (s.status === 'done' || s.status === 'failed')) {
                const dur = document.createElement('span');
                dur.className = 'rhbp-step__duration';
                dur.textContent = formatDuration(s.duration_ms);
                content.querySelector('.rhbp-step__label').appendChild(dur);
            }

            // Progress-Bar nur für aktiven Download/Upload-Step
            if (s.status === 'running' && (s.id === 'download' || s.id === 'upload')) {
                const bytesNow = status.bytes_now || 0;
                const bytesTotal = status.bytes_total || 0;
                if (bytesTotal > 0) {
                    const pct = Math.min(100, Math.round((bytesNow / bytesTotal) * 100));
                    const prog = document.createElement('div');
                    prog.className = 'rhbp-step__progress';
                    prog.innerHTML = '<div class="rhbp-step__progress-bar"><div class="rhbp-step__progress-fill" style="width:' + pct + '%"></div></div>' +
                        '<div class="rhbp-step__progress-info"><span>' + formatBytes(bytesNow) + ' / ' + formatBytes(bytesTotal) + '</span><span>' + pct + '%</span></div>';
                    content.appendChild(prog);
                }
            }
        });
    }

    function renderSuccess(status) {
        setIcon('success', 'yes-alt');
        const verb = modalAction === 'pull' ? 'Pull' : 'Push';
        modal.querySelector('[data-modal-title]').textContent = verb + ' erfolgreich';
        modal.querySelector('[data-modal-subtitle]').textContent = 'Von „' + activePeer.name + '"';

        const summary = status.summary || {};
        const sumEl = modal.querySelector('[data-summary]');
        sumEl.innerHTML = '';

        const sumItems = [
            { label: 'Übertragen', value: formatBytes(summary.bytes || 0) },
            { label: 'Gesamtdauer', value: formatDuration(summary.duration_ms || 0) },
        ];
        if (summary.chunks) {
            sumItems.push({ label: 'Chunks', value: summary.chunks });
        }
        if (summary.remote_import_ms != null) {
            sumItems.push({ label: 'Remote-Import', value: formatDuration(summary.remote_import_ms) });
        }
        sumItems.forEach((s) => {
            const el = document.createElement('div');
            el.className = 'rhbp-stat';
            el.innerHTML = '<span class="rhbp-stat__label">' + s.label + '</span><span class="rhbp-stat__value">' + s.value + '</span>';
            sumEl.appendChild(el);
        });

        const phaseEl = modal.querySelector('[data-phase-timings]');
        phaseEl.innerHTML = '';
        const timings = summary.phase_timings || {};
        Object.entries(timings).forEach(([phase, ms]) => {
            const el = document.createElement('div');
            el.className = 'rhbp-phase-timing';
            el.innerHTML = '<span class="rhbp-phase-timing__label">' + (PHASE_LABELS[phase] || phase) + '</span>' +
                '<span class="rhbp-phase-timing__value">' + formatDuration(ms) + '</span>';
            phaseEl.appendChild(el);
        });

        if (summary.profile) {
            renderProfileList(modal.querySelector('[data-success-profile]'), summary.profile);
        } else {
            modal.querySelector('[data-success-profile-section]').hidden = true;
        }

        if (summary.safety_backup_path) {
            modal.querySelector('[data-success-safety]').textContent = summary.safety_backup_path.split('/').pop();
            modal.querySelector('[data-success-safety-section]').hidden = false;
        } else {
            modal.querySelector('[data-success-safety-section]').hidden = true;
        }

        setState('success');
        setFooterButtons(['finish']);
    }

    function renderFailure(status) {
        const error = status.error || {};
        renderError('Sync fehlgeschlagen', error.message || 'Unbekannter Fehler', error.phase, error.safety_backup_path);
    }

    function renderError(title, message, phase, safetyBackup) {
        setIcon('error', 'warning');
        modal.querySelector('[data-modal-title]').textContent = title;
        modal.querySelector('[data-modal-subtitle]').textContent = activePeer ? 'Peer: ' + activePeer.name : '';

        modal.querySelector('[data-error-title]').textContent = title;
        const phaseEl = modal.querySelector('[data-error-phase]');
        if (phase) {
            phaseEl.textContent = 'Phase: ' + (PHASE_LABELS[phase] || phase);
            phaseEl.hidden = false;
        } else {
            phaseEl.hidden = true;
        }
        modal.querySelector('[data-error-message]').textContent = message;

        if (safetyBackup) {
            modal.querySelector('[data-error-safety]').textContent = safetyBackup.split('/').pop();
            modal.querySelector('[data-error-safety-section]').hidden = false;
        } else {
            modal.querySelector('[data-error-safety-section]').hidden = true;
        }

        setState('error');
        setFooterButtons(['cancel', 'retry']);
    }

    function clearJobAndReload() {
        if (activeJobId) {
            ajax('rhbp_sync_clear', { job_id: activeJobId }, 'POST').catch(() => {});
        }
        closeModal();
        // Soft reload um History zu aktualisieren
        window.location.reload();
    }

    // ---- Bindings ----

    // Profil-Forms (im jeweiligen Einstellungen-Modal) verdrahten
    root.querySelectorAll('[data-profile-form]').forEach(setupProfileForm);

    // Card-Actions hijacken: Pull/Push oeffnen das Progress-Modal statt admin-post
    root.querySelectorAll('[data-peer-id]').forEach((card) => {
        const peerId = card.dataset.peerId;
        const peerName = card.dataset.peerName;

        ['pull', 'push'].forEach((action) => {
            const form = card.querySelector('.rhbp-peer-action[data-action="' + action + '"]');
            if (!form) return;
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (form.querySelector('button').disabled) return;
                openModal(action, peerId, peerName);
            });
        });
    });

    // History expandable
    root.querySelectorAll('.rhbp-history-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const target = document.getElementById(targetId);
            if (!target) return;
            const isOpen = !target.hidden;
            target.hidden = isOpen;
            btn.classList.toggle('is-open', !isOpen);
        });
    });

    // Modal-Buttons
    modal.querySelectorAll('[data-modal-close]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });
    modal.querySelector('[data-modal-confirm]').addEventListener('click', startSync);
    modal.querySelector('[data-modal-retry]').addEventListener('click', () => {
        if (activePeer && modalAction) {
            openModal(modalAction, activePeer.id, activePeer.name);
        }
    });
    modal.querySelector('[data-modal-finish]').addEventListener('click', clearJobAndReload);

    const loginBtn = modal.querySelector('[data-modal-login]');
    if (loginBtn) {
        loginBtn.addEventListener('click', () => {
            if (loginBtn.disabled) return;
            const redirectTo = encodeURIComponent(window.location.href);
            window.location.href = '/wp-login.php?redirect_to=' + redirectTo;
        });
    }

    // ESC schliesst Modal, aber nicht während laufendem Sync (Progress/Standalone)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden) {
            const inProgress = !modal.querySelector('[data-state="progress"]').hidden;
            const inStandalone = !modal.querySelector('[data-state="standalone"]').hidden;
            if (!inProgress && !inStandalone) closeModal();
        }
    });
}());

/* =========================================
   Generische UI-Mechanik, von allen Modulen geteilt.
   Data-Attribut-gesteuert, kein modul-spezifischer Code:
     [data-rhbp-modal-open="ID"]  oeffnet  #ID (.rhbp-modal-backdrop)
     [data-rhbp-modal-close]      schliesst das umgebende Modal
     Backdrop-Klick + ESC         schliessen ebenfalls
     [data-rhbp-subtab="KEY"]     schaltet auf [data-rhbp-pane="KEY"] im selben Modal
     .rhbp-option input           toggelt .is-checked auf der Kachel
     [data-rhbp-copy="SEL"]       kopiert den Text aus SEL in die Zwischenablage
   ========================================= */
(function () {
    'use strict';

    const settings = document.querySelector('.rhbp-settings');
    if (!settings) {
        return;
    }

    function openBackdrop(backdrop) {
        if (!backdrop) return;
        backdrop.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    }

    function closeBackdrop(backdrop) {
        if (!backdrop) return;
        backdrop.classList.remove('is-open');
        if (!document.querySelector('.rhbp-modal-backdrop.is-open')) {
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('click', (e) => {
        // Oeffnen
        const opener = e.target.closest('[data-rhbp-modal-open]');
        if (opener) {
            e.preventDefault();
            openBackdrop(document.getElementById(opener.dataset.rhbpModalOpen));
            return;
        }

        // Schliessen ueber Button
        const closer = e.target.closest('[data-rhbp-modal-close]');
        if (closer) {
            e.preventDefault();
            closeBackdrop(closer.closest('.rhbp-modal-backdrop'));
            return;
        }

        // Schliessen ueber Backdrop-Klick (nur direkt auf den Hintergrund)
        if (e.target.classList && e.target.classList.contains('rhbp-modal-backdrop')) {
            closeBackdrop(e.target);
            return;
        }

        // Sub-Tabs im Modal
        const subtab = e.target.closest('[data-rhbp-subtab]');
        if (subtab) {
            const modal = subtab.closest('.rhbp-modal');
            if (modal) {
                const key = subtab.dataset.rhbpSubtab;
                modal.querySelectorAll('[data-rhbp-subtab]').forEach((t) => t.classList.toggle('is-active', t === subtab));
                modal.querySelectorAll('[data-rhbp-pane]').forEach((p) => p.classList.toggle('is-active', p.dataset.rhbpPane === key));
            }
            return;
        }

        // Copy-Button
        const copyBtn = e.target.closest('[data-rhbp-copy]');
        if (copyBtn) {
            e.preventDefault();
            const target = document.querySelector(copyBtn.dataset.rhbpCopy);
            if (target && navigator.clipboard) {
                navigator.clipboard.writeText(target.textContent.trim()).then(() => {
                    copyBtn.classList.add('is-copied');
                    setTimeout(() => copyBtn.classList.remove('is-copied'), 1500);
                }).catch(() => {});
            }
            return;
        }
    });

    // ESC schliesst das oberste offene Modal
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        const open = Array.from(document.querySelectorAll('.rhbp-modal-backdrop.is-open')).pop();
        if (open) closeBackdrop(open);
    });

    // Option-Kacheln: is-checked spiegeln (Checkbox-Mehrfachauswahl + Radio-Gruppen)
    settings.addEventListener('change', (e) => {
        const input = e.target;
        if (!input.matches) return;
        if (input.matches('.rhbp-option input[type="checkbox"]')) {
            const option = input.closest('.rhbp-option');
            if (option) option.classList.toggle('is-checked', input.checked);
        } else if (input.matches('.rhbp-option input[type="radio"]')) {
            // Ganze Gruppe spiegeln, die abgewählten Radios feuern kein Event.
            const scope = input.closest('.rhbp-option-grid') || settings;
            Array.from(scope.querySelectorAll('.rhbp-option input[type="radio"]'))
                .filter((r) => r.name === input.name)
                .forEach((r) => {
                    const opt = r.closest('.rhbp-option');
                    if (opt) opt.classList.toggle('is-checked', r.checked);
                });
        }
    });
}());
