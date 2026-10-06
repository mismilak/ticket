/* طراح پلان سالن — بدون وابستگی */
(function () {
    const NS = 'http://www.w3.org/2000/svg';
    const $ = id => document.getElementById(id);
    const fa = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    const el = (tag, a = {}, t) => { const e = document.createElementNS(NS, tag); for (const k in a) e.setAttribute(k, a[k]); if (t != null) e.textContent = t; return e; };
    const H = window.__HALL__;
    const FA_ROWS = ['الف', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی'];
    const R = 11;

    let uid = 0, cn = 0;
    const load = L => ({
        categories: L.categories.map(c => ({ ...c })),
        levels: L.levels.map(l => ({ ...l, shapes: (l.shapes || []).map(s => ({ ...s })), seats: l.seats.map(s => ({ ...s, uid: ++uid })) })),
    });
    let S = load(H.layout), li = 0, tool = 'select', sel = new Set(), selShape = null, cat = S.categories[0]?.id;
    let k = 1, tx = 0, ty = 0, hist = [], dirty = false;
    const lv = () => S.levels[li];

    /* ---------- history ---------- */
    const snap = () => { hist.push(JSON.stringify({ S, li })); if (hist.length > 60) hist.shift(); dirty = true; };
    $('undo').onclick = undo;
    function undo() { const h = hist.pop(); if (!h) return; const o = JSON.parse(h); S = o.S; li = Math.min(o.li, S.levels.length - 1); sel.clear(); selShape = null; all(); }

    /* ---------- helpers ---------- */
    function nextRow(s) {
        s = String(s ?? '').trim();
        if (/^\d+$/.test(s)) return String(+s + 1);
        const i = FA_ROWS.indexOf(s); if (i >= 0) return FA_ROWS[i + 1] ?? s + '2';
        if (/^[A-Za-z]$/.test(s)) return s === 'Z' ? 'AA' : s === 'z' ? 'aa' : String.fromCharCode(s.charCodeAt(0) + 1);
        if (/^[A-Z]{2}$/.test(s)) return s[0] + String.fromCharCode(s.charCodeAt(1) + 1);
        return s;
    }
    const pt = e => { const r = $('canvas').getBoundingClientRect(); return { x: (e.clientX - r.left - tx) / k, y: (e.clientY - r.top - ty) / k }; };

    /* ---------- rendering ---------- */
    let svg, vp, seatLayer, shapeLayer, band;
    function render() {
        const c = $('canvas'); c.innerHTML = '';
        svg = el('svg'); vp = el('g'); svg.appendChild(vp); c.appendChild(svg);
        const l = lv();
        vp.appendChild(el('rect', { x: 0, y: 0, width: l.width, height: l.height, fill: '#171a36', stroke: '#2f3566', 'stroke-dasharray': '6 6', rx: 8 }));
        shapeLayer = el('g'); seatLayer = el('g'); vp.appendChild(shapeLayer); vp.appendChild(seatLayer);
        l.shapes.forEach((s, i) => {
            const g = window.drawShape(s); g.setAttribute('data-shape', i); g.style.cursor = 'move';
            if (selShape === i) {
                const b = s.type === 'text' ? { x: s.x - 60, y: s.y - 14, w: 120, h: 28 } : { x: s.x, y: s.y, w: s.w, h: s.h };
                g.appendChild(el('rect', { x: b.x - 3, y: b.y - 3, width: b.w + 6, height: b.h + 6, fill: 'none', stroke: '#fbbf24', 'stroke-width': 2, 'stroke-dasharray': '5 3' }));
            }
            shapeLayer.appendChild(g);
        });
        const cats = Object.fromEntries(S.categories.map(c => [c.id, c]));
        l.seats.forEach(s => {
            const g = el('g', { class: 'seat', transform: `translate(${s.x},${s.y})`, 'data-uid': s.uid });
            const on = sel.has(s.uid);
            g.appendChild(el('circle', { r: R, fill: cats[s.cat]?.color || '#666', stroke: on ? '#fff' : 'none', 'stroke-width': on ? 3 : 0 }));
            if (on) g.appendChild(el('circle', { r: R + 4, fill: 'none', stroke: '#fbbf24', 'stroke-width': 2 }));
            g.appendChild(el('text', { fill: '#fff', 'font-size': 9, 'text-anchor': 'middle', 'dominant-baseline': 'central' }, s.label));
            const t = el('title', {}, (s.row ? 'ردیف ' + s.row + ' - ' : '') + 'صندلی ' + s.label); g.appendChild(t);
            seatLayer.appendChild(g);
        });
        band = el('rect', { fill: 'rgba(59,130,246,.15)', stroke: '#3b82f6', display: 'none' }); vp.appendChild(band);
        applyT();
    }
    function applyT() { vp.setAttribute('transform', `translate(${tx},${ty}) scale(${k})`); svg.setAttribute('viewBox', `0 0 ${$('canvas').clientWidth} ${$('canvas').clientHeight}`); }
    function fit() { const c = $('canvas'), l = lv(); k = Math.min(c.clientWidth / l.width, c.clientHeight / l.height) * .95; tx = (c.clientWidth - l.width * k) / 2; ty = (c.clientHeight - l.height * k) / 2; applyT(); }
    function zoom(f, cx, cy) {
        if (!f) return fit();
        const c = $('canvas'); cx = cx ?? c.clientWidth / 2; cy = cy ?? c.clientHeight / 2;
        const nk = Math.max(.1, Math.min(6, k * f)), r = nk / k; tx = cx - (cx - tx) * r; ty = cy - (cy - ty) * r; k = nk; applyT();
    }

    /* ---------- side panels ---------- */
    function panels() {
        const ll = $('levelList'); ll.innerHTML = '';
        S.levels.forEach((l, i) => { const b = document.createElement('button'); b.className = 'btn btn-sm ' + (i === li ? 'btn-primary' : 'btn-outline-primary'); b.textContent = l.name + ' (' + fa(l.seats.length) + ')'; b.onclick = () => { li = i; sel.clear(); selShape = null; all(true); }; ll.appendChild(b); });
        $('lvW').value = lv().width; $('lvH').value = lv().height;

        const cl = $('catList'); cl.innerHTML = '';
        S.categories.forEach(c => {
            const d = document.createElement('div'); d.className = 'cat-row';
            d.innerHTML = `<input type="radio" name="activeCat" ${c.id === cat ? 'checked' : ''} title="دسته فعال برای ردیف‌های جدید"><input type="color" value="${c.color}"><input class="form-control form-control-sm" value="${c.name}"><button class="btn btn-sm btn-outline-danger">×</button>`;
            const [r, col, name, del] = d.children;
            r.onchange = () => { cat = c.id; };
            col.oninput = () => { c.color = col.value; dirty = true; render(); };
            name.oninput = () => { c.name = name.value; dirty = true; fillSelCat(); };
            del.onclick = () => { if (S.categories.length < 2) return alert('حداقل یک دسته لازم است.'); if (!confirm('دسته حذف شود؟ صندلی‌های این دسته بدون دسته می‌شوند.')) return; snap(); S.categories = S.categories.filter(x => x !== c); S.levels.forEach(l => l.seats.forEach(s => { if (s.cat === c.id) s.cat = null; })); if (cat === c.id) cat = S.categories[0].id; all(); };
            cl.appendChild(d);
        });
        fillSelCat();
        const total = S.levels.reduce((a, l) => a + l.seats.length, 0);
        $('count').textContent = 'مجموع صندلی‌ها: ' + fa(total);
        const n = sel.size;
        $('selCount').textContent = fa(selShape !== null ? 1 : n);
        $('seatTools').classList.toggle('d-none', selShape !== null);
        $('shapeTools').classList.toggle('d-none', selShape === null);
        $('selPanel').style.opacity = (n || selShape !== null) ? 1 : .5;
        if (selShape !== null) { const s = lv().shapes[selShape]; $('shText').value = s.text || ''; $('shW').value = s.w || ''; $('shH').value = s.h || ''; }
    }
    function fillSelCat() { const s = $('selCat'); const v = s.value; s.innerHTML = S.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join(''); if (v) s.value = v; }
    function all(f) { render(); if (f) fit(); panels(); }

    /* ---------- generators ---------- */
    $('genRows').onclick = () => {
        snap();
        const rows = +$('gRows').value, cols = +$('gCols').value, dx = +$('gDx').value, dy = +$('gDy').value, arc = +$('gArc').value * Math.PI / 180;
        const start = +$('gStart').value || 1, rtl = $('gDir').value === 'rtl'; let row = $('gRow').value;
        const c = $('canvas'); const W = (cols - 1) * dx, ox = (c.clientWidth / 2 - tx) / k - W / 2, oy = (c.clientHeight / 2 - ty) / k - rows * dy / 2;
        sel.clear(); selShape = null;
        const Rad = arc ? W / arc : 0;
        for (let r = 0; r < rows; r++) {
            for (let i = 0; i < cols; i++) {
                let x, y;
                if (arc) { const Rr = Rad + r * dy, a = -arc / 2 + arc * (cols > 1 ? i / (cols - 1) : .5); x = ox + W / 2 + Rr * Math.sin(a); y = oy + Rr * Math.cos(a) - Rad; }
                else { x = ox + i * dx; y = oy + r * dy; }
                const n = rtl ? start + (cols - 1 - i) : start + i;
                const s = { uid: ++uid, x: Math.round(x * 10) / 10, y: Math.round(y * 10) / 10, label: String(n), row, cat }; lv().seats.push(s); sel.add(s.uid);
            }
            row = nextRow(row);
        }
        $('gRow').value = row; all();
    };
    document.querySelectorAll('[data-shape]').forEach(b => b.onclick = () => {
        snap(); const t = b.dataset.shape; const cx = (($('canvas').clientWidth / 2) - tx) / k, cy = (($('canvas').clientHeight / 2) - ty) / k;
        const s = t === 'text' ? { type: 'text', x: cx, y: cy, text: 'متن', size: 18 } : t === 'stage' ? { type: 'stage', x: cx - 200, y: cy - 30, w: 400, h: 60, text: 'صحنه' } : { type: 'rect', x: cx - 40, y: cy - 20, w: 80, h: 40, text: '' };
        lv().shapes.push(s); sel.clear(); selShape = lv().shapes.length - 1; all();
    });

    /* ---------- selection tools ---------- */
    const selSeats = () => lv().seats.filter(s => sel.has(s.uid));
    $('applyCat').onclick = () => { if (!sel.size) return; snap(); selSeats().forEach(s => s.cat = $('selCat').value && isNaN($('selCat').value) ? $('selCat').value : +$('selCat').value); all(); };
    $('applyRow').onclick = () => { if (!sel.size) return; snap(); selSeats().forEach(s => s.row = $('selRow').value); all(); };
    $('renum').onclick = () => {
        if (!sel.size) return; snap(); const arr = selSeats(); const rtl = $('selDir').value === 'rtl'; let n = +$('selStart').value || 1;
        // به ترتیب ردیف (y) سپس x
        const rows = []; arr.sort((a, b) => a.y - b.y).forEach(s => { const last = rows[rows.length - 1]; if (last && Math.abs(last[0].y - s.y) < R * 1.2) last.push(s); else rows.push([s]); });
        rows.forEach(r => { r.sort((a, b) => rtl ? b.x - a.x : a.x - b.x).forEach((s, i) => s.label = String(n + i)); });
        all();
    };
    const avg = (a, f) => a.reduce((s, x) => s + x[f], 0) / a.length;
    $('alignY').onclick = () => { if (sel.size < 2) return; snap(); const a = selSeats(), y = avg(a, 'y'); a.forEach(s => s.y = Math.round(y * 10) / 10); all(); };
    $('alignX').onclick = () => { if (sel.size < 2) return; snap(); const a = selSeats(), x = avg(a, 'x'); a.forEach(s => s.x = Math.round(x * 10) / 10); all(); };
    $('distX').onclick = () => { if (sel.size < 3) return; snap(); const a = selSeats().sort((p, q) => p.x - q.x); const d = (a[a.length - 1].x - a[0].x) / (a.length - 1); a.forEach((s, i) => s.x = Math.round((a[0].x + d * i) * 10) / 10); all(); };
    $('dup').onclick = () => { if (!sel.size) return; snap(); const n = new Set(); selSeats().forEach(s => { const c = { ...s, uid: ++uid, id: undefined, x: s.x + 40, y: s.y + 40 }; lv().seats.push(c); n.add(c.uid); }); sel = n; all(); };
    $('delSel').onclick = del;
    function del() {
        if (selShape !== null) { snap(); lv().shapes.splice(selShape, 1); selShape = null; return all(); }
        if (!sel.size) return; snap(); lv().seats = lv().seats.filter(s => !sel.has(s.uid)); sel.clear(); all();
    }
    ['shText', 'shW', 'shH'].forEach(id => $(id).oninput = () => {
        if (selShape === null) return; const s = lv().shapes[selShape]; dirty = true;
        if (id === 'shText') s.text = $('shText').value; else if (id === 'shW') s.w = +$('shW').value || s.w; else s.h = +$('shH').value || s.h; render();
    });

    /* ---------- levels ---------- */
    $('addLevel').onclick = () => { const n = prompt('نام طبقه جدید (مثلاً بالکن):'); if (!n) return; snap(); S.levels.push({ id: 'n' + (++cn), name: n, width: 1200, height: 800, shapes: [], seats: [] }); li = S.levels.length - 1; sel.clear(); selShape = null; all(true); };
    $('renLevel').onclick = () => { const n = prompt('نام طبقه:', lv().name); if (n) { snap(); lv().name = n; panels(); } };
    $('delLevel').onclick = () => { if (S.levels.length < 2) return alert('حداقل یک طبقه لازم است.'); if (!confirm('طبقه «' + lv().name + '» با تمام صندلی‌هایش حذف شود؟')) return; snap(); S.levels.splice(li, 1); li = 0; sel.clear(); selShape = null; all(true); };
    $('lvW').onchange = () => { snap(); lv().width = Math.max(200, +$('lvW').value); all(true); };
    $('lvH').onchange = () => { snap(); lv().height = Math.max(200, +$('lvH').value); all(true); };
    $('addCat').onclick = () => { snap(); const id = 'n' + (++cn); const colors = ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6']; S.categories.push({ id, name: 'دسته جدید', color: colors[S.categories.length % colors.length] }); cat = id; all(); };

    /* ---------- tools & canvas interaction ---------- */
    document.querySelectorAll('.tool-btn').forEach(b => b.onclick = () => { tool = b.dataset.tool; document.querySelectorAll('.tool-btn').forEach(x => x.classList.toggle('active', x === b)); });
    document.querySelectorAll('[data-z]').forEach(b => b.onclick = () => zoom(+b.dataset.z));

    const cv = $('canvas'); let drag = null;
    cv.addEventListener('wheel', e => { e.preventDefault(); const r = cv.getBoundingClientRect(); zoom(e.deltaY < 0 ? 1.12 : .89, e.clientX - r.left, e.clientY - r.top); }, { passive: false });
    cv.addEventListener('pointerdown', e => {
        cv.setPointerCapture(e.pointerId);
        const p = pt(e), seatEl = e.target.closest('.seat'), shEl = e.target.closest('[data-shape]');
        if (tool === 'pan' || e.button === 1) { drag = { t: 'pan', x: e.clientX, y: e.clientY }; return; }
        if (seatEl) {
            const u = +seatEl.dataset.uid; selShape = null;
            if (e.shiftKey) { sel.has(u) ? sel.delete(u) : sel.add(u); } else if (!sel.has(u)) { sel = new Set([u]); }
            snap(); drag = { t: 'move', p, moved: false }; render(); panels(); return;
        }
        if (shEl) { selShape = +shEl.dataset.shape; sel.clear(); snap(); drag = { t: 'shape', p, i: selShape }; render(); panels(); return; }
        if (!e.shiftKey) { sel.clear(); }
        selShape = null; drag = { t: 'band', p, add: e.shiftKey }; render(); panels();
    });
    cv.addEventListener('pointermove', e => {
        if (!drag) return;
        if (drag.t === 'pan') { tx += e.clientX - drag.x; ty += e.clientY - drag.y; drag.x = e.clientX; drag.y = e.clientY; return applyT(); }
        const p = pt(e);
        if (drag.t === 'move') { const dx = p.x - drag.p.x, dy = p.y - drag.p.y; selSeats().forEach(s => { s.x += dx; s.y += dy; }); drag.p = p; drag.moved = true; selSeats().forEach(s => { const g = seatLayer.querySelector(`[data-uid="${s.uid}"]`); g && g.setAttribute('transform', `translate(${s.x},${s.y})`); }); }
        else if (drag.t === 'shape') { const s = lv().shapes[drag.i]; s.x += p.x - drag.p.x; s.y += p.y - drag.p.y; drag.p = p; render(); }
        else if (drag.t === 'band') { const x = Math.min(p.x, drag.p.x), y = Math.min(p.y, drag.p.y); band.setAttribute('display', ''); band.setAttribute('x', x); band.setAttribute('y', y); band.setAttribute('width', Math.abs(p.x - drag.p.x)); band.setAttribute('height', Math.abs(p.y - drag.p.y)); }
    });
    cv.addEventListener('pointerup', e => {
        if (!drag) return;
        if (drag.t === 'band') {
            const p = pt(e), x1 = Math.min(p.x, drag.p.x), x2 = Math.max(p.x, drag.p.x), y1 = Math.min(p.y, drag.p.y), y2 = Math.max(p.y, drag.p.y);
            if (x2 - x1 > 3 || y2 - y1 > 3) lv().seats.forEach(s => { if (s.x >= x1 && s.x <= x2 && s.y >= y1 && s.y <= y2) sel.add(s.uid); });
        }
        if (drag.t === 'move' && !drag.moved) hist.pop();
        drag = null; render(); panels();
    });
    cv.addEventListener('dblclick', e => {
        const sh = e.target.closest('[data-shape]'); if (!sh) return; const s = lv().shapes[+sh.dataset.shape]; const t = prompt('متن:', s.text || ''); if (t !== null) { snap(); s.text = t; all(); }
    });
    document.addEventListener('keydown', e => {
        if (/INPUT|SELECT|TEXTAREA/.test(e.target.tagName)) return;
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); del(); }
        else if ((e.ctrlKey || e.metaKey) && e.key === 'z') { e.preventDefault(); undo(); }
        else if ((e.ctrlKey || e.metaKey) && e.key === 'a') { e.preventDefault(); sel = new Set(lv().seats.map(s => s.uid)); all(); }
        else if (e.key.startsWith('Arrow') && sel.size) {
            e.preventDefault(); snap(); const st = e.shiftKey ? 10 : 1;
            selSeats().forEach(s => { if (e.key === 'ArrowLeft') s.x -= st; if (e.key === 'ArrowRight') s.x += st; if (e.key === 'ArrowUp') s.y -= st; if (e.key === 'ArrowDown') s.y += st; }); render();
        }
    });
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    /* ---------- save ---------- */
    $('save').onclick = async () => {
        const btn = $('save'); btn.disabled = true; $('status').textContent = 'در حال ذخیره...';
        const body = {
            name: $('hallName').value,
            categories: S.categories.map(c => ({ id: c.id, name: c.name, color: c.color })),
            levels: S.levels.map(l => ({ id: l.id, name: l.name, width: l.width, height: l.height, shapes: l.shapes, seats: l.seats.map(s => ({ id: s.id, x: s.x, y: s.y, label: s.label, row: s.row || null, cat: s.cat })) })),
        };
        try {
            const r = await fetch(H.save, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': H.csrf }, body: JSON.stringify(body) });
            const j = await r.json();
            if (!r.ok) throw new Error(j.message || Object.values(j.errors || {}).flat()[0] || 'خطا در ذخیره');
            const cur = lv().name; S = load(j.layout); li = Math.max(0, S.levels.findIndex(l => l.name === cur)); cat = S.categories[0]?.id; sel.clear(); hist = []; dirty = false;
            all(); $('status').textContent = 'ذخیره شد ✓';
        } catch (e) { $('status').textContent = ''; alert(e.message); }
        btn.disabled = false;
    };

    window.addEventListener('resize', applyT);
    all(true);
})();
