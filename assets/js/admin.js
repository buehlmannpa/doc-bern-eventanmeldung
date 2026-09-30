/* CMS DOC Bern */
(() => {
  'use strict';

  const cfg = JSON.parse(document.getElementById('admin-config').textContent);
  const f = cfg.features;
  const roles = cfg.roles || {};
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const chf = (n) => `${cfg.prices.currency} ${Number(n || 0).toFixed(2)}`;
  const fmtDate = (iso) => new Date(iso).toLocaleString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  const relLabel = { main: 'Hauptperson', companion: 'Weitere Person', child: 'Kind' };

  let data = null;
  const view = { search: '', filter: 'all' };

  // ---------------------------------------------------------------------
  // Server
  // ---------------------------------------------------------------------

  async function api(action, body) {
    const opts = body === undefined
      ? { cache: 'no-store' }
      : { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf }, body: JSON.stringify(body) };
    const res = await fetch(`${cfg.api}?action=${action}`, opts);
    if (res.status === 401) { location.reload(); throw new Error('Nicht angemeldet'); }
    const out = await res.json().catch(() => ({ ok: false, error: 'Unerwartete Antwort vom Server.' }));
    out.httpStatus = res.status;
    if (out.ok) { data = out; renderAll(); }
    return out;
  }

  function toast(msg, isError = false) {
    const t = $('#toast');
    t.textContent = msg;
    t.classList.toggle('is-error', isError);
    t.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { t.hidden = true; }, 3200);
  }

  // ---------------------------------------------------------------------
  // Tabs
  // ---------------------------------------------------------------------

  $$('.tab').forEach((tab) => tab.addEventListener('click', () => {
    $$('.tab').forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
    $$('.tab-panel').forEach((p) => { p.hidden = p.id !== `tab-${tab.dataset.tab}`; });
    try { sessionStorage.setItem('docTab', tab.dataset.tab); } catch (_) { /* ignorieren */ }
  }));
  try {
    const saved = sessionStorage.getItem('docTab');
    if (saved) $(`.tab[data-tab="${saved}"]`)?.click();
  } catch (_) { /* ignorieren */ }

  // ---------------------------------------------------------------------
  // Rendering
  // ---------------------------------------------------------------------

  function renderAll() {
    renderPill();
    renderOverview();
    renderList();
    renderSettings();
  }

  const statusText = {
    open: ['Anmeldung offen', 'ok'],
    full: ['Ausgebucht (automatisch)', 'full'],
    closed: ['Geschlossen (manuell)', 'full'],
    paused: ['Pausiert', 'warn'],
    deadline: ['Anmeldeschluss vorbei', 'full'],
  };

  function renderPill() {
    const [text, cls] = statusText[data.status.status] || ['?', ''];
    const pill = $('#status-pill');
    pill.textContent = text;
    pill.className = `status-pill is-${cls}`;
  }

  function allPersons() {
    return data.registrations.flatMap((r) => r.persons.map((p) => ({ ...p, reg: r })));
  }

  // --- Übersicht --------------------------------------------------------

  function barList(rows, { money = false } = {}) {
    const max = Math.max(1, ...rows.map((r) => r.value));
    return `<ul class="bars">${rows.map((r) => `
      <li>
        <span class="bar-label">${r.swatch ? `<i class="swatch" style="background:${esc(r.swatch)}"></i>` : ''}${esc(r.label)}</span>
        <span class="bar-track"><span class="bar-fill" style="width:${(r.value / max) * 100}%;${r.swatch ? `background:${esc(r.swatch)}` : ''}"></span></span>
        <span class="bar-value">${money ? chf(r.value) : r.value}</span>
      </li>`).join('')}</ul>`;
  }

  function dailyChart(regs) {
    if (!regs.length) return '<p class="muted">Noch keine Anmeldungen.</p>';
    const byDay = new Map();
    regs.forEach((r) => {
      const d = r.created_at.slice(0, 10);
      byDay.set(d, (byDay.get(d) || 0) + r.persons.length);
    });
    // Lückenlose Tagesreihe vom ersten bis zum letzten Tag
    const days = [...byDay.keys()].sort();
    const series = [];
    for (let d = new Date(`${days[0]}T12:00:00`); d <= new Date(`${days[days.length - 1]}T12:00:00`); d.setDate(d.getDate() + 1)) {
      const key = d.toISOString().slice(0, 10);
      series.push({ key, label: d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit' }), value: byDay.get(key) || 0 });
    }

    const W = 640, H = 220, pl = 32, pr = 8, pt = 12, pb = 28;
    const max = Math.max(1, ...series.map((s) => s.value));
    const step = Math.max(1, Math.ceil(max / 4));
    const top = step * Math.ceil(max / step);
    const cw = (W - pl - pr) / series.length;
    const bw = Math.max(4, Math.min(36, cw - 2));
    const y = (v) => pt + (H - pt - pb) * (1 - v / top);
    const labelEvery = Math.ceil(series.length / 8);

    let grid = '';
    for (let v = 0; v <= top; v += step) {
      grid += `<line x1="${pl}" x2="${W - pr}" y1="${y(v)}" y2="${y(v)}" class="grid"/><text x="${pl - 6}" y="${y(v) + 4}" class="axis" text-anchor="end">${v}</text>`;
    }
    const bars = series.map((s, i) => {
      const x = pl + i * cw + (cw - bw) / 2;
      const h = Math.max(0, y(0) - y(s.value));
      const r = Math.min(4, bw / 2, h);
      const path = h > 0
        ? `M${x},${y(0)} V${y(s.value) + r} Q${x},${y(s.value)} ${x + r},${y(s.value)} H${x + bw - r} Q${x + bw},${y(s.value)} ${x + bw},${y(s.value) + r} V${y(0)} Z`
        : '';
      return `<g class="col" data-tip="${esc(s.label)}: ${s.value} ${s.value === 1 ? 'Person' : 'Personen'}">
        <rect x="${pl + i * cw}" y="${pt}" width="${cw}" height="${H - pt - pb}" class="hit"/>
        ${path ? `<path d="${path}" class="colbar"/>` : ''}
        ${i % labelEvery === 0 ? `<text x="${x + bw / 2}" y="${H - 8}" class="axis" text-anchor="middle">${esc(s.label)}</text>` : ''}
      </g>`;
    }).join('');

    return `
      <div class="chart">
        <svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Angemeldete Personen pro Tag">${grid}${bars}</svg>
        <div class="chart-tip" hidden></div>
      </div>
      <details class="table-view"><summary>Als Tabelle anzeigen</summary>
        <table><thead><tr><th>Tag</th><th>Personen</th></tr></thead><tbody>
        ${series.filter((s) => s.value).map((s) => `<tr><td>${esc(s.label)}</td><td>${s.value}</td></tr>`).join('')}
        </tbody></table>
      </details>`;
  }

  function bindChartTips(root) {
    $$('.chart', root).forEach((chart) => {
      const tip = $('.chart-tip', chart);
      const show = (g, ev) => {
        $$('.col', chart).forEach((c) => c.classList.toggle('active', c === g));
        tip.textContent = g.dataset.tip;
        tip.hidden = false;
        const box = chart.getBoundingClientRect();
        const x = Math.min(box.width - 10, Math.max(10, ev.clientX - box.left));
        tip.style.left = `${x}px`;
        tip.style.top = `${Math.max(0, ev.clientY - box.top - 44)}px`;
      };
      $$('.col', chart).forEach((g) => {
        g.addEventListener('pointerenter', (ev) => show(g, ev));
        g.addEventListener('pointermove', (ev) => show(g, ev));
        g.addEventListener('pointerdown', (ev) => show(g, ev));
      });
      chart.addEventListener('pointerleave', () => {
        tip.hidden = true;
        $$('.col', chart).forEach((c) => c.classList.remove('active'));
      });
    });
  }

  function renderOverview() {
    const s = data.status;
    const persons = allPersons();
    const adults = persons.filter((p) => p.type === 'adult').length;
    const kids = persons.length - adults;
    const total = data.registrations.reduce((a, r) => a + r.total, 0);
    const paid = data.registrations.filter((r) => r.paid).reduce((a, r) => a + r.total, 0);
    const pct = s.capacity ? Math.min(100, Math.round((s.taken / s.capacity) * 100)) : 100;

    const kpis = [
      ['Belegte Plätze', `${s.taken}<small> / ${s.capacity}</small>`, `${s.remaining} frei`],
      ['Anmeldungen', data.registrations.length, 'Gruppen'],
      ['Erwachsene', adults, ''],
    ];
    if (f.children) kpis.push(['Kinder', kids, '']);
    if (f.payment) {
      kpis.push(['Betrag total', chf(total), '']);
      kpis.push(['Bezahlt', chf(paid), `offen ${chf(total - paid)}`]);
    }

    const cards = [];
    if (f.children) {
      cards.push(['Personen nach Art', barList([{ label: 'Erwachsene', value: adults }, { label: 'Kinder', value: kids }])]);
    }
    if (f.alt_menu) {
      cards.push(['Menüwahl', barList([
        { label: cfg.menu.standard, value: persons.filter((p) => p.menu !== 'alternative').length },
        { label: cfg.menu.alternative, value: persons.filter((p) => p.menu === 'alternative').length },
      ])]);
    }
    cards.push(['Personen nach Rolle', barList([
      { label: 'Mitglieder und Gäste', value: persons.filter((p) => !p.role).length },
      ...Object.entries(roles).map(([k, r]) => ({ label: r.label, value: persons.filter((p) => p.role === k).length, swatch: r.color })),
    ])]);
    if (f.payment) {
      cards.push(['Bezahlstatus', barList([{ label: 'Bezahlt', value: paid }, { label: 'Offen', value: total - paid }], { money: true })]);
    }

    const el = $('#tab-overview');
    el.innerHTML = `
      <div class="kpis">${kpis.map(([l, v, sub]) => `
        <div class="glass kpi"><span class="kpi-label">${l}</span><strong class="kpi-value">${v}</strong>${sub ? `<span class="kpi-sub">${sub}</span>` : ''}</div>`).join('')}
      </div>
      <div class="glass">
        <div class="capacity-head"><h2>Belegung</h2><p class="capacity-numbers"><strong>${pct}%</strong> ausgelastet</p></div>
        <div class="meter"><div class="meter-fill" style="width:${pct}%"></div></div>
        <p class="capacity-foot">${esc((statusText[s.status] || [''])[0])}${s.message ? ` · ${esc(s.message)}` : ''}</p>
      </div>
      <div class="charts">
        ${cards.map(([t, body]) => `<div class="glass chart-card"><h3>${t}</h3>${body}</div>`).join('')}
        <div class="glass chart-card chart-wide"><h3>Angemeldete Personen pro Tag</h3>${dailyChart(data.registrations)}</div>
      </div>`;
    bindChartTips(el);
  }

  // --- Anmeldungen ------------------------------------------------------

  function matches(r) {
    const q = view.search.trim().toLowerCase();
    if (q) {
      const hay = [r.email, r.notes, ...r.persons.map((p) => `${p.first} ${p.last}`)].join(' ').toLowerCase();
      if (!hay.includes(q)) return false;
    }
    if (view.filter === 'unpaid') return !r.paid && r.total > 0;
    if (view.filter === 'children') return r.persons.some((p) => p.type === 'child');
    if (view.filter === 'alt') return r.persons.some((p) => p.menu === 'alternative');
    if (view.filter.startsWith('role:')) return r.persons.some((p) => p.role === view.filter.slice(5));
    return true;
  }

  function renderList() {
    const el = $('#tab-list');
    const hadFocus = document.activeElement?.id === 'search';
    const filters = [['all', 'Alle']];
    Object.entries(roles).forEach(([k, r]) => filters.push([`role:${k}`, r.label]));
    if (f.children) filters.push(['children', 'Mit Kindern']);
    if (f.alt_menu) filters.push(['alt', cfg.menu.alternative]);
    if (f.payment) filters.push(['unpaid', 'Unbezahlt']);

    const list = data.registrations.filter(matches).sort((a, b) => b.created_at.localeCompare(a.created_at));

    el.innerHTML = `
      <div class="glass toolbar">
        <label class="field search"><span class="sr-only">Suchen</span>
          <input type="search" id="search" placeholder="Name oder E-Mail suchen" value="${esc(view.search)}"></label>
        <div class="toolbar-actions">
          <button class="btn btn-primary" id="add-reg">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Anmeldung hinzufügen</button>
          <a class="btn btn-glass" href="${cfg.api}?action=export&csrf=${encodeURIComponent(cfg.csrf)}">CSV Export</a>
        </div>
        <div class="chips" role="group" aria-label="Filter">
          ${filters.map(([k, l]) => `<button class="chip" data-filter="${esc(k)}" aria-pressed="${view.filter === k}">${esc(l)}</button>`).join('')}
        </div>
        ${Object.keys(roles).length ? `<div class="legend">${Object.values(roles).map((r) => `<span><i class="swatch" style="background:${esc(r.color)}"></i>${esc(r.label)}${r.free ? ' (kostenlos)' : ''}</span>`).join('')}</div>` : ''}
      </div>
      <p class="muted small list-count">${list.length} von ${data.registrations.length} Anmeldungen, ${list.reduce((a, r) => a + r.persons.length, 0)} Personen</p>
      <div class="reg-list">
        ${list.length ? list.map(regCard).join('') : '<div class="glass empty">Keine Anmeldungen gefunden.</div>'}
      </div>`;

    const search = $('#search', el);
    search.addEventListener('input', () => {
      view.search = search.value;
      const pos = search.selectionStart;
      renderList();
      const s2 = $('#search');
      s2.focus();
      s2.setSelectionRange(pos, pos);
    });
    if (hadFocus) search.focus();
    $$('.chip', el).forEach((c) => c.addEventListener('click', () => { view.filter = c.dataset.filter; renderList(); }));
    $('#add-reg', el).addEventListener('click', () => openEditor(null));
    $$('[data-edit]', el).forEach((b) => b.addEventListener('click', () => openEditor(data.registrations.find((r) => r.id === b.dataset.edit))));
    $$('[data-delete]', el).forEach((b) => b.addEventListener('click', () => deleteReg(b.dataset.delete)));
    $$('[data-mail]', el).forEach((b) => b.addEventListener('click', async () => {
      const r = data.registrations.find((x) => x.id === b.dataset.mail);
      if (!confirm(`Bestätigung an ${r.email} ${r.mailed_at ? 'erneut ' : ''}senden?`)) return;
      b.disabled = true;
      const out = await api('mail', { id: r.id });
      b.disabled = false;
      toast(out.ok ? `Bestätigung an ${r.email} gesendet.` : out.error, !out.ok);
    }));
    $$('[data-paid]', el).forEach((c) => c.addEventListener('change', async () => {
      const out = await api('paid', { id: c.dataset.paid, paid: c.checked });
      if (!out.ok) toast(out.error, true);
    }));
  }

  function regCard(r) {
    const main = r.persons[0] || {};
    const personRow = (p) => {
      const role = roles[p.role];
      return `<li class="person ${role ? 'has-role' : ''}" ${role ? `style="--role:${esc(role.color)}"` : ''}>
        <span class="p-name">${esc(p.first)} ${esc(p.last)}</span>
        <span class="p-tags">
          <span class="tag">${relLabel[p.relation] || ''}</span>
          ${role ? `<span class="tag tag-role">${esc(role.label)}</span>` : ''}
          ${f.alt_menu ? `<span class="tag ${p.menu === 'alternative' ? 'tag-alt' : ''}">${esc(cfg.menu[p.menu] || p.menu)}</span>` : ''}
        </span>
        ${f.payment ? `<span class="p-price">${chf(priceFor(p))}</span>` : ''}
      </li>`;
    };
    return `
      <article class="glass reg">
        <header class="reg-head">
          <div>
            <h3>${esc(main.first)} ${esc(main.last)}</h3>
            <p class="muted small">${r.email ? `<a href="mailto:${esc(r.email)}">${esc(r.email)}</a> · ` : ''}${fmtDate(r.created_at)}${r.source === 'admin' ? ' · <span class="tag">manuell</span>' : ''}</p>
            ${data.mailEnabled && r.email ? `<p class="muted small mail-state">${r.mailed_at ? `✓ Bestätigung gesendet ${fmtDate(r.mailed_at)}` : 'Keine Bestätigung gesendet'}</p>` : ''}
          </div>
          <div class="reg-actions">
            ${data.mailEnabled && r.email ? `<button class="icon-btn" data-mail="${esc(r.id)}" aria-label="Bestätigung erneut senden" title="Bestätigung erneut senden">
              <svg viewBox="0 0 24 24"><path d="M3 6h18v12H3z"/><path d="m3 7 9 6 9-6"/></svg></button>` : ''}
            <button class="icon-btn" data-edit="${esc(r.id)}" aria-label="Bearbeiten" title="Bearbeiten">
              <svg viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="m14 6 4 4"/></svg></button>
            <button class="icon-btn danger" data-delete="${esc(r.id)}" aria-label="Löschen" title="Löschen">
              <svg viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/></svg></button>
          </div>
        </header>
        <ul class="persons">${r.persons.map(personRow).join('')}</ul>
        <footer class="reg-foot">
          <span>${r.persons.length} ${r.persons.length === 1 ? 'Person' : 'Personen'}</span>
          ${f.payment ? `<strong>${chf(r.total)}</strong>
            <label class="switch"><input type="checkbox" data-paid="${esc(r.id)}" ${r.paid ? 'checked' : ''}><span></span>Bezahlt</label>` : ''}
          ${r.notes ? `<p class="note">${esc(r.notes)}</p>` : ''}
        </footer>
      </article>`;
  }

  function priceFor(p) {
    if (!f.payment) return 0;
    if (p.role && roles[p.role]?.free) return 0;
    return p.type === 'child' ? cfg.prices.child : cfg.prices.adult;
  }

  async function deleteReg(id) {
    const r = data.registrations.find((x) => x.id === id);
    const name = r ? `${r.persons[0].first} ${r.persons[0].last}` : '';
    const n = r?.persons.length || 0;
    if (!confirm(`Anmeldung von ${name} (${n} ${n === 1 ? 'Person' : 'Personen'}) wirklich löschen?`)) return;
    const out = await api('delete', { id });
    toast(out.ok ? 'Anmeldung gelöscht.' : out.error, !out.ok);
  }

  // --- Editor -----------------------------------------------------------

  function openEditor(reg) {
    const dlg = $('#editor');
    const model = {
      id: reg?.id || '',
      email: reg?.email || '',
      paid: !!reg?.paid,
      notes: reg?.notes || '',
      sendMail: !reg,
      main: reg?.persons.find((p) => p.relation === 'main') || { first: '', last: '', menu: 'standard', role: '' },
      companion: reg?.persons.find((p) => p.relation === 'companion') || null,
      children: reg?.persons.filter((p) => p.relation === 'child') || [],
    };

    const roleOptions = (sel) => `<option value="">Keine Rolle</option>${Object.entries(roles).map(([k, r]) => `<option value="${esc(k)}" ${sel === k ? 'selected' : ''}>${esc(r.label)}</option>`).join('')}`;
    const personFields = (key, p, title, removable) => `
      <fieldset class="person-block" data-key="${key}">
        <legend>${title}</legend>
        ${removable ? `<button type="button" class="icon-btn remove" data-remove="${key}" aria-label="${title} entfernen"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>` : ''}
        <input type="hidden" name="${key}.id" value="${esc(p.id || '')}">
        <div class="grid-2">
          <label class="field"><span>Vorname</span><input name="${key}.first" value="${esc(p.first)}" required maxlength="60"></label>
          <label class="field"><span>Nachname</span><input name="${key}.last" value="${esc(p.last)}" required maxlength="60"></label>
        </div>
        <div class="grid-2">
          ${Object.keys(roles).length ? `<label class="field"><span>Rolle</span><select name="${key}.role">${roleOptions(p.role)}</select></label>` : ''}
          ${f.alt_menu ? `<label class="field"><span>Menü</span><select name="${key}.menu">
            <option value="standard" ${p.menu !== 'alternative' ? 'selected' : ''}>${esc(cfg.menu.standard)}</option>
            <option value="alternative" ${p.menu === 'alternative' ? 'selected' : ''}>${esc(cfg.menu.alternative)}</option></select></label>` : ''}
        </div>
      </fieldset>`;

    const readForm = () => {
      const form = $('form', dlg);
      const get = (n) => form.elements[n]?.value ?? '';
      const person = (k) => ({ id: get(`${k}.id`), first: get(`${k}.first`), last: get(`${k}.last`), role: get(`${k}.role`), menu: get(`${k}.menu`) || 'standard' });
      model.email = get('email');
      model.notes = get('notes');
      model.paid = !!form.elements.paid?.checked;
      model.sendMail = !!form.elements.sendMail?.checked;
      model.main = person('main');
      if (model.companion) model.companion = person('companion');
      model.children = model.children.map((_, i) => person(`child${i}`));
    };

    const draw = () => {
      dlg.innerHTML = `
        <form method="dialog" novalidate>
          <header class="dlg-head">
            <h2>${model.id ? 'Anmeldung bearbeiten' : 'Anmeldung hinzufügen'}</h2>
            <button type="button" class="icon-btn" data-close aria-label="Schliessen"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
          </header>
          <div class="dlg-body">
            ${personFields('main', model.main, 'Hauptperson', false)}
            <label class="field"><span>E-Mail</span><input type="email" name="email" value="${esc(model.email)}" maxlength="120"></label>
            ${model.companion ? personFields('companion', model.companion, 'Weitere Person', true) : ''}
            ${model.children.map((c, i) => personFields(`child${i}`, c, `Kind ${i + 1}`, true)).join('')}
            <div class="add-row">
              ${f.companion && !model.companion ? '<button type="button" class="btn btn-glass" data-add="companion">+ Weitere Person</button>' : ''}
              ${f.children ? '<button type="button" class="btn btn-glass" data-add="child">+ Kind</button>' : ''}
            </div>
            ${f.payment ? `<label class="switch"><input type="checkbox" name="paid" ${model.paid ? 'checked' : ''}><span></span>Bezahlt</label>` : ''}
            ${data.mailEnabled ? `<label class="switch"><input type="checkbox" name="sendMail" ${model.sendMail ? 'checked' : ''}><span></span>${model.id ? 'Bestätigung erneut senden' : 'Bestätigung per E-Mail senden'}</label>` : ''}
            <label class="field"><span>Notiz (nur intern)</span><textarea name="notes" maxlength="500">${esc(model.notes)}</textarea></label>
            <div class="alert alert-error" data-error hidden></div>
          </div>
          <footer class="dlg-foot">
            <button type="button" class="btn btn-glass" data-close>Abbrechen</button>
            <button type="submit" class="btn btn-primary">Speichern</button>
          </footer>
        </form>`;

      $$('[data-close]', dlg).forEach((b) => b.addEventListener('click', () => dlg.close()));
      $$('[data-add]', dlg).forEach((b) => b.addEventListener('click', () => {
        readForm();
        if (b.dataset.add === 'companion') model.companion = { first: '', last: '', menu: 'standard', role: '' };
        else model.children.push({ first: '', last: '', menu: 'standard', role: '' });
        draw();
      }));
      $$('[data-remove]', dlg).forEach((b) => b.addEventListener('click', () => {
        readForm();
        const k = b.dataset.remove;
        if (k === 'companion') model.companion = null;
        else model.children.splice(Number(k.replace('child', '')), 1);
        draw();
      }));
      $('form', dlg).addEventListener('submit', (ev) => { ev.preventDefault(); save(false); });
    };

    const save = async (force) => {
      readForm();
      const errEl = $('[data-error]', dlg);
      const payload = {
        id: model.id, email: model.email, paid: model.paid, notes: model.notes,
        main: model.main, companion: model.companion, children: model.children,
      };
      const out = await api('save', { registration: payload, force, sendMail: model.sendMail && !!model.email });
      if (out.ok) {
        dlg.close();
        if (out.mailSent === false) toast('Gespeichert, aber die Bestätigung konnte nicht versendet werden.', true);
        else toast(out.mailSent ? 'Gespeichert und Bestätigung gesendet.' : 'Gespeichert.');
        return;
      }
      if (out.needsForce && confirm(out.error)) {
        save(true);
        return;
      }
      $$('.field.invalid', dlg).forEach((x) => x.classList.remove('invalid'));
      Object.keys(out.fields || {}).forEach((k) => {
        const name = k.replace(/^children\.(\d+)\./, 'child$1.');
        $(`[name="${name}"]`, dlg)?.closest('.field')?.classList.add('invalid');
      });
      errEl.textContent = out.error || 'Speichern fehlgeschlagen.';
      errEl.hidden = false;
    };

    draw();
    dlg.showModal();
    $('input[name="main.first"]', dlg).focus();
  }

  // Klick auf den Hintergrund schliesst den Dialog
  $('#editor').addEventListener('click', (ev) => { if (ev.target.id === 'editor') ev.target.close(); });

  // --- Einstellungen ----------------------------------------------------

  function renderSettings() {
    const el = $('#tab-settings');
    if (el.contains(document.activeElement)) return; // Eingaben nicht überschreiben
    const st = data.state;
    const modes = [
      ['open', 'Anmeldung offen', 'Die Anmeldung ist möglich. Bei Erreichen des Limits wird sie automatisch geschlossen.'],
      ['closed', 'Ausgebucht / geschlossen', 'Zeigt die Meldung «Der Anlass ist ausgebucht» oder deinen eigenen Text.'],
      ['paused', 'Wir sind gleich zurück', 'Pausiert die Anmeldung vorübergehend, z.B. während Anpassungen.'],
    ];
    el.innerHTML = `
      <form class="glass settings" id="settings-form">
        <h2>Status der Anmeldung</h2>
        <div class="mode-list">
          ${modes.map(([k, t, d]) => `
            <label class="mode">
              <input type="radio" name="mode" value="${k}" ${st.mode === k ? 'checked' : ''}>
              <span><strong>${t}</strong><small>${d}</small></span>
            </label>`).join('')}
        </div>
        <label class="field"><span>Eigene Meldung (optional, ersetzt den Standardtext)</span>
          <textarea name="message" maxlength="300" placeholder="z.B. Das Weihnachtsessen ist ausgebucht. Wir freuen uns auf nächstes Jahr!">${esc(st.message)}</textarea></label>

        <h2>Limit</h2>
        <label class="field"><span>Maximale Anzahl Personen (leer = Wert aus der Konfiguration: ${data.defaultCap})</span>
          <input type="number" name="capacity_override" min="0" max="10000" inputmode="numeric" value="${st.capacity_override ?? ''}" placeholder="${data.defaultCap}"></label>
        <p class="muted small">Aktuell angemeldet: ${data.status.taken} Personen. Erwachsene und Kinder zählen je als ein Platz.</p>

        <button class="btn btn-primary">Einstellungen speichern</button>
      </form>`;
    $('#settings-form', el).addEventListener('submit', async (ev) => {
      ev.preventDefault();
      const fm = ev.target;
      const btn = $('button', fm);
      btn.focus();
      const out = await api('state', {
        mode: fm.elements.mode.value,
        message: fm.elements.message.value,
        capacity_override: fm.elements.capacity_override.value,
      });
      btn.blur();
      renderSettings();
      toast(out.ok ? 'Einstellungen gespeichert.' : out.error, !out.ok);
    });
  }

  // ---------------------------------------------------------------------
  // Start und automatische Aktualisierung
  // ---------------------------------------------------------------------

  api('list').then((out) => { if (!out.ok) toast(out.error, true); });
  setInterval(() => {
    if (!document.hidden && !$('#editor').open) api('list');
  }, 60000);
})();
