/* Anmeldeseite DOC Bern */
(() => {
  'use strict';

  const cfg = JSON.parse(document.getElementById('app-config').textContent);
  const f = cfg.features;
  const form = document.getElementById('reg-form');
  let status = cfg.status;
  let childSeq = 0;

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const chf = (n) => `${cfg.prices.currency} ${Number(n).toFixed(2)}`;
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // ---------------------------------------------------------------------
  // Bausteine
  // ---------------------------------------------------------------------

  function menuChoice(key) {
    if (!f.alt_menu) return '';
    return `
      <div class="segmented" role="radiogroup" aria-label="Menüwahl">
        <label><input type="radio" name="${key}.menu" value="standard" checked><span>${esc(cfg.menu.standard)}</span></label>
        <label><input type="radio" name="${key}.menu" value="alternative"><span>${esc(cfg.menu.alternative)}</span></label>
      </div>`;
  }

  function personBlock(key, title, removeLabel) {
    const el = document.createElement('fieldset');
    el.className = 'person-block appear';
    el.dataset.key = key;
    el.innerHTML = `
      <legend>${esc(title)}</legend>
      <button type="button" class="icon-btn remove" aria-label="${esc(removeLabel)}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
      </button>
      <div class="grid-2">
        <label class="field"><span>Vorname</span><input type="text" name="${key}.first" required maxlength="60" autocomplete="off"></label>
        <label class="field"><span>Nachname</span><input type="text" name="${key}.last" required maxlength="60" autocomplete="off"></label>
      </div>
      ${menuChoice(key)}`;
    return el;
  }

  const mainMenu = $('[data-menu-for="main"]');
  if (mainMenu) mainMenu.innerHTML = menuChoice('main');

  // Weitere Person (max. 1)
  const addCompanion = $('#add-companion');
  const companionWrap = $('#companion-wrap');
  addCompanion?.addEventListener('click', () => {
    const block = personBlock('companion', 'Weitere Person', 'Weitere Person entfernen');
    block.querySelector('.remove').addEventListener('click', () => {
      block.remove();
      addCompanion.hidden = false;
      update();
    });
    companionWrap.appendChild(block);
    addCompanion.hidden = true;
    block.querySelector('input').focus();
    update();
  });

  // Kinder
  const addChild = $('#add-child');
  const childrenWrap = $('#children-wrap');
  addChild?.addEventListener('click', () => {
    const key = `child${childSeq++}`;
    const block = personBlock(key, 'Kind', 'Kind entfernen');
    block.classList.add('child-block');
    block.querySelector('.remove').addEventListener('click', () => {
      block.remove();
      renumberChildren();
      update();
    });
    childrenWrap.appendChild(block);
    renumberChildren();
    block.querySelector('input').focus();
    update();
  });

  function renumberChildren() {
    const blocks = $$('.child-block', childrenWrap || document);
    blocks.forEach((b, i) => { b.querySelector('legend').textContent = `Kind ${i + 1}`; });
    if (addChild) addChild.hidden = blocks.length >= (f.max_children || 8);
  }

  // ---------------------------------------------------------------------
  // Daten sammeln, Zusammenfassung und Platzprüfung
  // ---------------------------------------------------------------------

  const val = (name) => (form.elements[name]?.value || '').trim();
  const menuVal = (key) => (form.querySelector(`input[name="${key}.menu"]:checked`)?.value || 'standard');

  function collect() {
    const data = {
      email: val('email'),
      main: { first: val('main.first'), last: val('main.last'), menu: menuVal('main') },
      children: [],
      consent: form.elements.consent.checked,
      website: val('website'),
    };
    if ($('[data-key="companion"]')) {
      data.companion = { first: val('companion.first'), last: val('companion.last'), menu: menuVal('companion') };
    }
    $$('.child-block').forEach((b) => {
      const k = b.dataset.key;
      data.children.push({ first: val(`${k}.first`), last: val(`${k}.last`), menu: menuVal(k), _key: k });
    });
    return data;
  }

  function update() {
    if (!form) return;
    const d = collect();
    const adults = 1 + (d.companion ? 1 : 0);
    const kids = d.children.length;
    const total = adults + kids;

    let html = `<span><strong>${total}</strong> ${total === 1 ? 'Person' : 'Personen'}</span>`;
    if (kids) html += `<span>${adults} Erw. und ${kids} ${kids === 1 ? 'Kind' : 'Kinder'}</span>`;
    if (f.payment) html += `<span>Total <strong>${chf(adults * cfg.prices.adult + kids * cfg.prices.child)}</strong></span>`;
    $('#summary').innerHTML = html;

    const err = $('#capacity-error');
    const over = status.status === 'open' && total > status.remaining;
    if (over) {
      err.textContent = status.remaining <= 0
        ? 'Die maximale Anzahl an Anmeldungen ist erreicht. Es sind keine Plätze mehr frei.'
        : `Die maximale Anzahl an Anmeldungen ist überschritten. Es ${status.remaining === 1 ? 'ist nur noch 1 Platz' : `sind nur noch ${status.remaining} Plätze`} frei. Bitte reduziere die Anzahl Personen.`;
    }
    err.hidden = !over;
    $('#submit-btn').disabled = over;
  }

  form?.addEventListener('input', update);
  form?.addEventListener('change', update);

  // ---------------------------------------------------------------------
  // Status (Belegungsbalken) laufend aktualisieren
  // ---------------------------------------------------------------------

  function renderStatus(s) {
    status = s;
    $('[data-taken]').textContent = s.taken;
    $('[data-capacity]').textContent = s.capacity;
    $('[data-remaining]').textContent = s.remaining;
    const pct = s.capacity > 0 ? Math.min(100, Math.round((s.taken / s.capacity) * 100)) : 100;
    $('.meter-fill').style.width = `${pct}%`;
    const meter = $('.meter');
    meter.setAttribute('aria-valuenow', s.taken);
    meter.setAttribute('aria-valuemax', s.capacity);
    meter.classList.toggle('is-high', pct >= 85);

    const success = $('#success');
    if (success && !success.hidden) return; // nach erfolgreicher Anmeldung nichts mehr umschalten

    const open = s.status === 'open';
    $('#closed-box').hidden = open;
    if (form) form.hidden = !open;
    if (!open) {
      $('[data-closed-title]').textContent = s.status === 'paused' ? 'Gleich zurück' : 'Anmeldung geschlossen';
      $('[data-closed-message]').textContent = s.message;
      $('#closed-box .state-icon').textContent = s.status === 'paused' ? '⏳' : '🏁';
    }
    update();
  }

  async function refreshStatus() {
    try {
      const res = await fetch(`${cfg.base}api.php?action=status`, { cache: 'no-store' });
      if (res.ok) renderStatus(await res.json());
    } catch (_) { /* offline: nächster Versuch */ }
  }
  renderStatus(status);
  setInterval(refreshStatus, 30000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshStatus(); });

  // ---------------------------------------------------------------------
  // Absenden
  // ---------------------------------------------------------------------

  function clearErrors() {
    $$('.field.invalid, .check.invalid').forEach((el) => el.classList.remove('invalid'));
    $$('.field-error').forEach((el) => el.remove());
    $('#form-error').hidden = true;
  }

  function markField(name, message) {
    const input = form.elements[name];
    if (!input) return;
    const wrap = input.closest('.field, .check');
    wrap.classList.add('invalid');
    if (wrap.classList.contains('field')) {
      const m = document.createElement('small');
      m.className = 'field-error';
      m.textContent = message;
      wrap.appendChild(m);
    }
  }

  function validateLocal() {
    let ok = true;
    $$('input[required]', form).forEach((input) => {
      const v = input.type === 'checkbox' ? input.checked : input.value.trim();
      if (!v) { markField(input.name, 'Pflichtfeld'); ok = false; }
    });
    const email = form.elements.email;
    if (email.value.trim() && !email.checkValidity()) { markField('email', 'Ungültige E-Mail Adresse'); ok = false; }
    return ok;
  }

  // Serverfehler (z.B. "children.0.first") auf Formularfelder abbilden
  function mapServerErrors(fields, data) {
    Object.entries(fields || {}).forEach(([key, msg]) => {
      const m = key.match(/^children\.(\d+)\.(first|last)$/);
      if (m) {
        const child = data.children[Number(m[1])];
        if (child) markField(`${child._key}.${m[2]}`, msg);
      } else {
        markField(key, msg);
      }
    });
  }

  form?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    clearErrors();
    if (!validateLocal()) {
      const errEl = $('#form-error');
      errEl.textContent = 'Bitte fülle alle markierten Felder aus.';
      errEl.hidden = false;
      $('.invalid input')?.focus();
      return;
    }

    const data = collect();
    const btn = $('#submit-btn');
    btn.disabled = true;
    btn.classList.add('loading');
    btn.textContent = 'Wird gesendet …';

    try {
      const res = await fetch(`${cfg.base}api.php?action=register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });
      const out = await res.json().catch(() => ({ ok: false, error: 'Unerwartete Antwort vom Server.' }));

      if (out.status) renderStatus(out.status);

      if (!out.ok) {
        mapServerErrors(out.fields, data);
        const errEl = $('#form-error');
        errEl.textContent = out.error || 'Die Anmeldung ist fehlgeschlagen.';
        errEl.hidden = false;
        return;
      }
      showSuccess(out.summary, out.mailSent);
    } catch (_) {
      const errEl = $('#form-error');
      errEl.textContent = 'Keine Verbindung zum Server. Bitte versuche es erneut.';
      errEl.hidden = false;
    } finally {
      btn.classList.remove('loading');
      btn.textContent = 'Verbindlich anmelden';
      update();
    }
  });

  function showSuccess(summary, mailSent) {
    const info = $('#mail-info');
    if (mailSent) {
      info.textContent = `Eine Bestätigung wurde an ${summary.email} gesendet. Bitte prüfe allenfalls auch den Spamordner.`;
      info.hidden = false;
    }
    const list = $('#success-list');
    list.innerHTML = summary.persons.map((p) => `
      <li>
        <span>${esc(p.name)}${p.type === 'child' ? ' <em>(Kind)</em>' : ''}</span>
        ${f.alt_menu ? `<span class="tag">${esc(cfg.menu[p.menu] || p.menu)}</span>` : ''}
        ${f.payment ? `<strong>${chf(p.price)}</strong>` : ''}
      </li>`).join('');
    const total = $('#success-total');
    if (total) total.textContent = chf(summary.total);

    form.hidden = true;
    $('#closed-box').hidden = true;
    const box = $('#success');
    box.hidden = false;
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    box.focus({ preventScroll: true });
  }

  update();
})();
