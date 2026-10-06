/* قیمت‌گذاری تک‌تک صندلی‌ها برای یک رویداد */
(function () {
    const NS = 'http://www.w3.org/2000/svg', $ = id => document.getElementById(id);
    const fa = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    const num = n => fa(Number(n).toLocaleString('en-US'));
    const el = (t, a = {}, x) => { const e = document.createElementNS(NS, t); for (const k in a) e.setAttribute(k, a[k]); if (x != null) e.textContent = x; return e; };
    const D = window.__SP__, L = D.layout;
    const cats = Object.fromEntries(L.categories.map(c => [c.id, c]));
    const ov = {};                       // قیمت‌های اختصاصی فعلی {id: price}
    for (const k in D.overrides) ov[k] = D.overrides[k];
    const changes = {};                  // تغییرات ذخیره‌نشده {id: price|null}
    let li = 0, sel = new Set(), k = 1, tx = 0, ty = 0, svg, vp, band, seatEls = {}, drag = null;
    const cv = $('canvas');
    const price = s => (s.id in ov) ? ov[s.id] : (cats[s.cat] ? cats[s.cat].price : null);
    const lv = () => L.levels[li];
    const allSeats = () => L.levels.flatMap(l => l.seats);

    function render(fitView) {
        cv.innerHTML = ''; svg = el('svg'); vp = el('g'); svg.appendChild(vp); cv.appendChild(svg);
        const l = lv(); vp.appendChild(el('rect', { x: 0, y: 0, width: l.width, height: l.height, fill: '#171a36', rx: 8 }));
        (l.shapes || []).forEach(s => vp.appendChild(window.drawShape(s)));
        seatEls = {};
        l.seats.forEach(s => {
            const g = el('g', { class: 'seat', transform: `translate(${s.x},${s.y})`, 'data-id': s.id });
            g.appendChild(el('circle', { r: 11 })); g.appendChild(el('text', { fill: '#fff', 'font-size': 9, 'text-anchor': 'middle', 'dominant-baseline': 'central' }, s.label));
            g.appendChild(el('title', {}, (s.row ? 'ردیف ' + s.row + ' - ' : '') + 'صندلی ' + s.label));
            vp.appendChild(g); seatEls[s.id] = g;
        });
        band = el('rect', { fill: 'rgba(59,130,246,.15)', stroke: '#3b82f6', display: 'none' }); vp.appendChild(band);
        if (fitView) { const w = cv.clientWidth, h = cv.clientHeight; k = Math.min(w / l.width, h / l.height) * .95; tx = (w - l.width * k) / 2; ty = (h - l.height * k) / 2; }
        apply(); paint();
    }
    function apply() { vp.setAttribute('transform', `translate(${tx},${ty}) scale(${k})`); svg.setAttribute('viewBox', `0 0 ${cv.clientWidth} ${cv.clientHeight}`); }
    function paint() {
        lv().seats.forEach(s => {
            const g = seatEls[s.id], c = g.firstChild, cat = cats[s.cat], p = price(s), custom = (s.id in ov);
            c.setAttribute('fill', cat && p != null ? cat.color : '#444');
            g.setAttribute('opacity', p != null ? 1 : .4);
            c.setAttribute('stroke', sel.has(s.id) ? '#fbbf24' : (custom ? '#fff' : 'none')); c.setAttribute('stroke-width', sel.has(s.id) ? 4 : 2.5);
            g.lastChild.textContent = (s.row ? 'ردیف ' + s.row + ' - ' : '') + 'صندلی ' + s.label + (p != null ? ' — ' + num(p) + ' ' + D.currency : ' (غیرقابل فروش)') + (custom ? ' [قیمت اختصاصی]' : '');
            g.children[1].textContent = custom ? '★' : s.label;
        });
        $('selCount').textContent = fa(sel.size);
        const sums = {};
        allSeats().forEach(s => { const p = price(s); if (p == null) return; const key = p + '|' + ((s.id in ov) ? 'c' : 'b' + s.cat); (sums[key] = sums[key] || { p, custom: s.id in ov, cat: s.cat, n: 0 }).n++; });
        $('summary').innerHTML = Object.values(sums).sort((a, b) => a.p - b.p).map(x => `<div class="d-flex justify-content-between border-bottom py-1"><span>${x.custom ? '★ اختصاصی' : (cats[x.cat] ? cats[x.cat].name : '')}</span><span>${num(x.p)} × ${fa(x.n)}</span></div>`).join('') || 'قیمتی تعریف نشده';
        const tabs = $('levelList'); tabs.innerHTML = '';
        L.levels.forEach((l, i) => { const b = document.createElement('button'); b.className = 'btn btn-sm ' + (i === li ? 'btn-primary' : 'btn-outline-primary'); b.textContent = l.name; b.onclick = () => { li = i; render(true); }; tabs.appendChild(b); });
    }
    const pt = e => { const r = cv.getBoundingClientRect(); return { x: (e.clientX - r.left - tx) / k, y: (e.clientY - r.top - ty) / k }; };
    cv.addEventListener('wheel', e => { e.preventDefault(); const r = cv.getBoundingClientRect(), f = e.deltaY < 0 ? 1.12 : .89, cx = e.clientX - r.left, cy = e.clientY - r.top, nk = Math.max(.1, Math.min(6, k * f)), q = nk / k; tx = cx - (cx - tx) * q; ty = cy - (cy - ty) * q; k = nk; apply(); }, { passive: false });
    cv.addEventListener('pointerdown', e => {
        cv.setPointerCapture(e.pointerId);
        if (e.altKey || e.button === 1) { drag = { t: 'pan', x: e.clientX, y: e.clientY }; return; }
        const g = e.target.closest('.seat');
        if (g) { const id = +g.dataset.id; if (sel.has(id)) sel.delete(id); else { if (!e.shiftKey && false) sel.clear(); sel.add(id); } paint(); drag = { t: 'click' }; return; }
        if (!e.shiftKey) sel.clear(); drag = { t: 'band', p: pt(e) }; paint();
    });
    cv.addEventListener('pointermove', e => {
        if (!drag) return;
        if (drag.t === 'pan') { tx += e.clientX - drag.x; ty += e.clientY - drag.y; drag.x = e.clientX; drag.y = e.clientY; return apply(); }
        if (drag.t === 'band') { const p = pt(e); band.setAttribute('display', ''); band.setAttribute('x', Math.min(p.x, drag.p.x)); band.setAttribute('y', Math.min(p.y, drag.p.y)); band.setAttribute('width', Math.abs(p.x - drag.p.x)); band.setAttribute('height', Math.abs(p.y - drag.p.y)); }
    });
    cv.addEventListener('pointerup', e => {
        if (drag && drag.t === 'band') {
            const p = pt(e), x1 = Math.min(p.x, drag.p.x), x2 = Math.max(p.x, drag.p.x), y1 = Math.min(p.y, drag.p.y), y2 = Math.max(p.y, drag.p.y);
            lv().seats.forEach(s => { if (s.x >= x1 && s.x <= x2 && s.y >= y1 && s.y <= y2) sel.add(s.id); });
            band.setAttribute('display', 'none'); paint();
        }
        drag = null;
    });

    $('apply').onclick = () => {
        const v = $('price').value.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[^\d]/g, '');
        if (!sel.size) return alert('ابتدا صندلی‌ها را انتخاب کنید.');
        if (v === '') return alert('قیمت را وارد کنید.');
        sel.forEach(id => { ov[id] = +v; changes[id] = +v; }); paint();
    };
    $('clear').onclick = () => { sel.forEach(id => { delete ov[id]; changes[id] = null; }); paint(); };
    $('catSel').innerHTML = L.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    $('selCat').onclick = () => { lv().seats.forEach(s => { if (String(s.cat) === $('catSel').value) sel.add(s.id); }); paint(); };
    $('selRow').onclick = () => { const rows = new Set(lv().seats.filter(s => sel.has(s.id)).map(s => s.row)); lv().seats.forEach(s => { if (rows.has(s.row)) sel.add(s.id); }); paint(); };
    $('selAll').onclick = () => { lv().seats.forEach(s => sel.add(s.id)); paint(); };
    $('selNone').onclick = () => { sel.clear(); paint(); };
    $('save').onclick = async () => {
        $('status').textContent = 'در حال ذخیره...';
        try {
            const r = await fetch(D.save, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRFToken': D.csrf }, body: JSON.stringify({ prices: changes }) });
            const j = await r.json(); if (!r.ok) throw new Error(j.message || 'خطا');
            for (const id in changes) delete changes[id]; $('status').textContent = 'ذخیره شد ✓';
        } catch (e) { $('status').textContent = ''; alert(e.message); }
    };
    window.addEventListener('beforeunload', e => { if (Object.keys(changes).length) { e.preventDefault(); e.returnValue = ''; } });
    window.addEventListener('resize', apply);
    render(true);
})();
