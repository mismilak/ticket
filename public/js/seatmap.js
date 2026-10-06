/* نمایشگر نقشه سالن با زوم و جابجایی (بدون وابستگی) */
(function () {
    const NS = 'http://www.w3.org/2000/svg';
    const fa = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    const el = (tag, attrs = {}, text) => {
        const e = document.createElementNS(NS, tag);
        for (const k in attrs) e.setAttribute(k, attrs[k]);
        if (text != null) e.textContent = text;
        return e;
    };

    /** رسم شکل‌های تزئینی (صحنه، متن، مستطیل) — مشترک با دیزاینر */
    window.drawShape = function (s) {
        const g = el('g');
        if (s.type === 'text') {
            g.appendChild(el('text', { x: s.x, y: s.y, fill: '#cbd0ea', 'font-size': s.size || 18, 'text-anchor': 'middle', 'dominant-baseline': 'central', 'font-family': 'Vazirmatn, Tahoma' }, s.text));
        } else {
            g.appendChild(el('rect', { x: s.x, y: s.y, width: s.w, height: s.h, rx: s.type === 'stage' ? 10 : 4, fill: s.type === 'stage' ? '#3b3f66' : '#23264a', stroke: '#5b608f', 'stroke-width': 1.5 }));
            if (s.text) g.appendChild(el('text', { x: s.x + s.w / 2, y: s.y + s.h / 2, fill: '#fff', 'font-size': 18, 'text-anchor': 'middle', 'dominant-baseline': 'central', 'font-family': 'Vazirmatn, Tahoma' }, s.text));
        }
        return g;
    };

    class SeatMap {
        constructor(o) {
            this.o = o; this.picked = new Map(); this.level = 0; this.k = 1; this.tx = 0; this.ty = 0;
            this.load().then(() => setInterval(() => this.refreshStatus(), 15000));
        }
        async load() {
            const r = await fetch(this.o.url, { headers: { Accept: 'application/json' } });
            this.data = await r.json();
            this.cats = Object.fromEntries(this.data.categories.map(c => [c.id, c]));
            this.status = this.data.status || {};
            this.buildTabs(); this.buildLegend(); this.render(true);
        }
        async refreshStatus() {
            try {
                const r = await fetch(this.o.url, { headers: { Accept: 'application/json' } });
                this.status = (await r.json()).status || {};
                // اگر صندلی انتخاب‌شده توسط دیگری گرفته شد، از انتخاب حذف شود
                let lost = false;
                for (const id of [...this.picked.keys()]) if (this.status[id]) { this.picked.delete(id); lost = true; }
                if (lost) { this.emit(); alert('برخی صندلی‌های انتخابی شما توسط دیگران رزرو شد.'); }
                this.paint();
            } catch (e) {}
        }
        buildTabs() {
            const t = this.o.tabs; t.innerHTML = '';
            if (this.data.levels.length < 2) return;
            this.data.levels.forEach((l, i) => {
                const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-outline-primary'; b.dataset.i = i;
                b.onclick = () => { this.level = i; this.buildTabs(); this.render(true); };
                const n = [...this.picked.values()].filter(p => p.levelIdx === i).length;
                b.innerHTML = l.name + (n ? ' <span class="badge bg-primary">' + fa(n) + '</span>' : '');
                if (i === this.level) b.classList.add('active');
                t.appendChild(b);
            });
        }
        buildLegend() {
            const L = this.o.legend; L.innerHTML = '';
            const add = (color, text, extra = '') => L.insertAdjacentHTML('beforeend', '<span><i style="background:' + color + ';' + extra + '"></i>' + text + '</span>');
            this.data.categories.filter(c => c.price != null).forEach(c => add(c.color, c.name + ' — ' + fa(Number(c.price).toLocaleString('en-US')) + ' ' + this.o.currency));
            add('#22c55e', 'انتخاب شما'); add('#6b7280', 'فروخته‌شده'); add('#f59e0b', 'در حال خرید دیگران');
        }
        render(reset) {
            const lv = this.data.levels[this.level]; if (!lv) return;
            const st = this.o.stage; st.innerHTML = '';
            this.svg = el('svg', { viewBox: `0 0 ${st.clientWidth} ${st.clientHeight}` });
            this.vp = el('g'); this.svg.appendChild(this.vp);
            this.vp.appendChild(el('rect', { x: 0, y: 0, width: lv.width, height: lv.height, fill: '#171a36', rx: 12 }));
            (lv.shapes || []).forEach(s => this.vp.appendChild(window.drawShape(s)));
            this.seatEls = {};
            lv.seats.forEach(s => {
                const g = el('g', { class: 'seat', transform: `translate(${s.x},${s.y})` });
                g.appendChild(el('circle', { r: 11 }));
                g.appendChild(el('text', {}, s.label));
                g.dataset.id = s.id;
                const title = el('title', {}, (s.row ? 'ردیف ' + s.row + ' - ' : '') + 'صندلی ' + s.label + (this.cats[s.cat] ? ' (' + this.cats[s.cat].name + ')' : ''));
                g.appendChild(title);
                this.vp.appendChild(g); this.seatEls[s.id] = { g, s };
            });
            st.appendChild(this.svg);
            if (reset) this.fit();
            this.paint(); this.bind();
        }
        paint() {
            for (const id in this.seatEls) {
                const { g, s } = this.seatEls[id]; const c = g.firstChild;
                const cat = this.cats[s.cat]; const stt = this.status[id];
                let fill = cat ? cat.color : '#444', cls = 'seat', op = 1, stroke = 'none';
                if (!cat || cat.price == null) { fill = '#444'; op = .35; cls += ' na'; }
                else if (this.picked.has(+id)) { fill = '#22c55e'; stroke = '#fff'; }
                else if (stt === 'sold') { fill = '#6b7280'; op = .6; cls += ' sold'; }
                else if (stt) { fill = '#f59e0b'; op = .7; cls += ' held'; }
                c.setAttribute('fill', fill); c.setAttribute('stroke', stroke); c.setAttribute('stroke-width', 2.5);
                g.setAttribute('class', cls); g.setAttribute('opacity', op);
                g.lastChild.previousSibling.style.display = this.k > .55 ? '' : 'none';
            }
        }
        fit() {
            const lv = this.data.levels[this.level]; const st = this.o.stage;
            const w = st.clientWidth, h = st.clientHeight;
            this.k = Math.min(w / lv.width, h / lv.height) * .95;
            this.tx = (w - lv.width * this.k) / 2; this.ty = (h - lv.height * this.k) / 2; this.apply();
        }
        apply() { this.vp.setAttribute('transform', `translate(${this.tx},${this.ty}) scale(${this.k})`); }
        zoom(f, cx, cy) {
            if (f === 0) { this.fit(); this.paint(); return; }
            const st = this.o.stage; cx = cx ?? st.clientWidth / 2; cy = cy ?? st.clientHeight / 2;
            const nk = Math.max(.15, Math.min(6, this.k * f)); const r = nk / this.k;
            this.tx = cx - (cx - this.tx) * r; this.ty = cy - (cy - this.ty) * r; this.k = nk; this.apply(); this.paint();
        }
        bind() {
            const st = this.o.stage; const pts = new Map(); let moved = 0, last = null, pinch = 0;
            st.onwheel = e => { e.preventDefault(); const r = st.getBoundingClientRect(); this.zoom(e.deltaY < 0 ? 1.15 : .87, e.clientX - r.left, e.clientY - r.top); };
            st.onpointerdown = e => { st.setPointerCapture(e.pointerId); pts.set(e.pointerId, e); moved = 0; last = { x: e.clientX, y: e.clientY }; pinch = 0; };
            st.onpointermove = e => {
                if (!pts.has(e.pointerId)) return; pts.set(e.pointerId, e);
                if (pts.size === 2) {
                    const [a, b] = [...pts.values()]; const d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
                    if (pinch) { const r = st.getBoundingClientRect(); this.zoom(d / pinch, (a.clientX + b.clientX) / 2 - r.left, (a.clientY + b.clientY) / 2 - r.top); }
                    pinch = d; moved = 99; return;
                }
                const dx = e.clientX - last.x, dy = e.clientY - last.y; moved += Math.abs(dx) + Math.abs(dy);
                if (moved > 6) { this.tx += dx; this.ty += dy; this.apply(); }
                last = { x: e.clientX, y: e.clientY };
            };
            st.onpointerup = st.onpointercancel = e => {
                pts.delete(e.pointerId);
                if (moved <= 6 && pts.size === 0) { const g = document.elementFromPoint(e.clientX, e.clientY)?.closest('.seat'); if (g) this.toggle(+g.dataset.id); }
            };
        }
        toggle(id) {
            const { s } = this.seatEls[id]; const cat = this.cats[s.cat];
            if (!cat || cat.price == null || this.status[id]) return;
            if (this.picked.has(id)) this.picked.delete(id);
            else {
                if (!this.o.canPick()) return this.o.onLimit && this.o.onLimit();
                this.picked.set(id, { id, label: s.label, row: s.row, price: cat.price, level: this.data.levels[this.level].name, levelIdx: this.level });
            }
            this.paint(); this.buildTabs(); this.emit();
        }
        emit() { this.o.onChange([...this.picked.values()]); }
    }
    window.SeatMap = SeatMap;
})();
