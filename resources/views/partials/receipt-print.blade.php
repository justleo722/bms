{{-- Shared 80mm thermal receipt printing (POS + Sales dockets and bills). --}}
<script>
window.BmsPrint = {
    esc: function (value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },

    money: function (v) {
        return Math.round(v || 0).toLocaleString();
    },

    // The currency symbol configured in Settings > Receipt.
    cur: function () {
        return (window.BmsMoney && window.BmsMoney.symbol) || 'TSh';
    },

    // The bar profile configured in Settings > Bar Information.
    bar: function () {
        return (window.BmsMoney && window.BmsMoney.bar) || {};
    },

    dateTime: function (dt) {
        var d = new Date(dt);
        if (isNaN(d.getTime())) return '';
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
            + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    },

    row: function (label, value, extra) {
        if (value === null || value === undefined || value === '') return '';
        return '<div style="display:flex; justify-content:space-between; margin-bottom: 2px;">'
            + '<span>' + label + '</span>'
            + '<span style="font-weight: bold;' + (extra ? ' ' + extra : '') + '">' + this.esc(value) + '</span>'
            + '</div>';
    },

    header: function () {
        var bar = this.bar();
        var name = bar.receipt_header || bar.name || 'THE BAREFOOT LOUNGE';
        var html = '<div style="text-align:center; border-bottom: 2px dashed #000; padding-bottom: 8px; margin-bottom: 8px;">'
            + '<div style="font-size: 18px; font-weight: bold; letter-spacing: 1px;">' + this.esc(name) + '</div>';
        if (bar.address) html += '<div style="font-size: 12px;">' + this.esc(bar.address) + '</div>';
        if (bar.phone) html += '<div style="font-size: 12px;">Tel: ' + this.esc(bar.phone) + '</div>';
        html += '</div>';
        return html;
    },

    footer: function () {
        var bar = this.bar();
        var message = bar.receipt_footer || 'Thank you for your purchase!';
        return '<div style="border-top: 1px dashed #000; margin: 8px 0;"></div>'
            + '<div style="text-align:center; font-size: 12px; color: #333;">'
            + '<div style="margin-top: 8px;">' + this.esc(message) + '</div></div>';
    },

    itemsHtml: function (items) {
        var self = this;
        return (items || []).map(function (item) {
            return '<div style="display:flex; justify-content:space-between; margin-bottom: 3px;">'
                + '<span>' + self.esc(item.name) + ' x' + item.qty + '</span>'
                + '<span>' + self.cur() + ' ' + self.money(item.price * item.qty) + '</span>'
                + '</div>';
        }).join('');
    },

    // Returns '' when there is no note, so the docket stays exactly as before.
    notesHtml: function (notes) {
        var text = String(notes === null || notes === undefined ? '' : notes).replace(/\s+/g, ' ').trim();
        if (!text) return '';
        return '<div style="border: 1px solid #000; padding: 5px 6px; margin-top: 8px; page-break-inside: avoid;">'
            + '<div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; margin-bottom: 3px;">NOTE</div>'
            + '<div style="font-size: 13px; white-space: pre-wrap; word-break: break-word;">' + this.esc(text) + '</div>'
            + '</div>';
    },

    docketHtml: function (sale) {
        if (!sale) return '';
        var rows = ''
            + this.row('Counter:', sale.counter)
            + this.row('Table:', sale.table)
            + this.row('Served by:', sale.staff, 'font-size: 14px;')
            + this.row('Payment:', sale.paymentMethod)
            + this.row('Ref:', sale.paymentRef);

        return this.header()
            + '<div style="text-align:center; margin-bottom: 8px;">'
            + '<div style="font-size: 16px; font-weight: bold; letter-spacing: 2px;">DOCKET</div>'
            + '<div style="font-size: 14px; font-weight: bold;">' + this.esc(sale.id) + '</div>'
            + '<div style="font-size: 12px;">' + this.esc(this.dateTime(sale.date)) + '</div>'
            + '</div>'
            + '<div style="border-top: 1px dashed #000; margin: 8px 0;"></div>'
            + '<div style="font-size: 13px;">' + rows + '</div>'
            + '<div style="border-top: 1px dashed #000; margin: 8px 0;"></div>'
            + '<div style="font-size: 12px;">' + this.itemsHtml(sale.items) + '</div>'
            + '<div style="border-top: 1px dashed #000; margin: 8px 0;"></div>'
            + '<div style="font-size: 13px;">'
            + '<div style="display:flex; justify-content:space-between; margin-bottom: 2px;"><span>Subtotal:</span><span>' + this.cur() + ' ' + this.money(sale.subtotal) + '</span></div>'
            + '<div style="display:flex; justify-content:space-between; font-weight: bold; font-size: 15px; border-top: 1px solid #000; padding-top: 4px; margin-top: 4px;"><span>TOTAL:</span><span>' + this.cur() + ' ' + this.money(sale.total) + '</span></div>'
            + '</div>'
            + this.notesHtml(sale.notes)
            + this.footer();
    },

    billHtml: function (sales) {
        var self = this;
        var grand = 0;
        var blocks = (sales || []).map(function (sale) {
            grand += sale.total || 0;
            return '<div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px dashed #ccc;">'
                + '<div style="font-size: 12px; font-weight: bold; margin-bottom: 4px;">'
                + self.esc(sale.id) + ' - ' + self.esc(sale.counter) + ' (' + self.esc(sale.table) + ')'
                + '</div>'
                + '<div style="font-size: 12px;">' + self.itemsHtml(sale.items) + '</div>'
                + '<div style="display:flex; justify-content:space-between; font-size: 12px; font-weight: bold; margin-top: 4px;">'
                + '<span>Subtotal:</span><span>' + self.cur() + ' ' + self.money(sale.subtotal) + '</span></div>'
                + '<div style="font-size: 12px; color: #333;">Served by: ' + self.esc(sale.staff) + '</div>'
                + '</div>';
        }).join('');

        return this.header()
            + '<div style="text-align:center; margin-bottom: 8px;">'
            + '<div style="font-size: 16px; font-weight: bold; letter-spacing: 2px;">BILL</div>'
            + '<div style="font-size: 12px;">' + this.esc(this.dateTime(new Date())) + '</div>'
            + '</div>'
            + '<div style="border-top: 1px dashed #000; margin: 8px 0;"></div>'
            + blocks
            + '<div style="border-top: 2px solid #000; margin: 8px 0; padding-top: 8px;">'
            + '<div style="display:flex; justify-content:space-between; font-size: 15px; font-weight: bold;">'
            + '<span>GRAND TOTAL:</span><span>' + this.cur() + ' ' + this.money(grand) + '</span></div>'
            + '</div>'
            + this.footer();
    },

    // =====================================================================
    // Offline PDF generation (pure JS, no external libraries). Receipts are
    // monospaced text, so we build a valid PDF by hand using the Core
    // "Courier" / "Courier-Bold" fonts. This makes the "Save PDF" buttons
    // work even when the html2pdf.js CDN is unreachable.
    // =====================================================================
    pdfW: 226.77, // 80mm = 80/25.4*72 pt
    pdfLM: 14,
    pdfRM: 14,

    pdfCharW: function (sz) { return sz * 0.6; },

    pdfTextW: function (t, sz) { return String(t).length * sz * 0.6; },

    pdfEscape: function (s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/\\/g, '\\\\')
            .replace(/\(/g, '\\(')
            .replace(/\)/g, '\\)');
    },

    pdfWrap: function (text, maxChars) {
        var t = String(text === null || text === undefined ? '' : text).replace(/\s+/g, ' ').trim();
        if (!t) return [];
        var words = t.split(' ');
        var lines = [], cur = '';
        words.forEach(function (w) {
            var candidate = cur ? cur + ' ' + w : w;
            if (candidate.length > maxChars) {
                if (cur) lines.push(cur);
                cur = w;
            } else {
                cur = candidate;
            }
        });
        if (cur) lines.push(cur);
        return lines;
    },

    receiptPdf: function (lines) {
        var self = this;
        var W = this.pdfW, LM = this.pdfLM, RM = this.pdfRM;
        var TOP = 14, BOTTOM = 12;

        // ---- Pass 1: layout (top-down) ----
        var y = TOP;
        lines.forEach(function (l) {
            if (!l) return;
            if (l.gap) y += l.gap;
            if (l.rule) {
                l._y = y;
                y += 8;
                return;
            }
            if (l.note) {
                var nsz = l.sz || 9;
                var maxChars = Math.floor((W - LM - RM - 14) / self.pdfCharW(nsz));
                l._lines = self.pdfWrap(l.note, maxChars);
                l._h = 6 + (nsz + 4) + l._lines.length * (nsz * 1.5) + 6;
                l._y = y;
                y += l._h + 8;
                return;
            }
            var sz = l.sz || 9;
            l._y = y;
            y += sz * 1.5;
        });
        var pageH = Math.max(Math.ceil(y + BOTTOM), 100);

        var by = function (topY, sz) { return pageH - topY - Math.round(sz * 0.85); };
        var cx = function (t, sz) { return (W - self.pdfTextW(t, sz)) / 2; };
        var rx = function (t, sz) { return W - RM - self.pdfTextW(t, sz); };

        // ---- Pass 2: content stream ----
        var out = [];
        var cmd = function (s) { out.push(s); };

        lines.forEach(function (l) {
            if (!l) return;
            if (l.rule) {
                var yy = pageH - (l._y + 4);
                if (l.rule === 'dash') {
                    cmd('[2 2] 0 d');
                    cmd('1 w');
                } else {
                    cmd('[] 0 d');
                    cmd(l.rule === 'thick' ? '2.5 w' : '0.8 w');
                }
                cmd(LM + ' ' + yy + ' m ' + (W - RM) + ' ' + yy + ' l S');
                return;
            }
            if (l.note) {
                var nsz = l.sz || 9;
                var topPt = pageH - l._y;
                var botPt = topPt - l._h;
                cmd('[] 0 d');
                cmd('0.9 w');
                cmd(LM + ' ' + botPt + ' ' + (W - LM - RM) + ' ' + l._h + ' re S');
                var cy = l._y + 5;
                cmd('BT /F2 ' + nsz + ' Tf 1 0 0 1 ' + cx('NOTE', nsz) + ' ' + by(cy, nsz) + ' Tm (NOTE) Tj ET');
                cy += nsz + 5;
                l._lines.forEach(function (t) {
                    cmd('BT /F1 ' + nsz + ' Tf 1 0 0 1 ' + (LM + 7) + ' ' + by(cy, nsz) + ' Tm (' + self.pdfEscape(t) + ') Tj ET');
                    cy += nsz * 1.5;
                });
                return;
            }
            var sz = l.sz || 9;
            var f = l.b ? 'F2' : 'F1';
            if (l.c) {
                cmd('BT /' + f + ' ' + sz + ' Tf 1 0 0 1 ' + cx(l.t, sz) + ' ' + by(l._y, sz) + ' Tm (' + self.pdfEscape(l.t) + ') Tj ET');
                return;
            }
            var left = l.t, right = l.r;
            var charW = self.pdfCharW(sz);
            if (right !== undefined && right !== null && right !== '') {
                var rw = self.pdfTextW(right, sz);
                var maxLeft = Math.floor((W - LM - RM - 6 - rw) / charW);
                if (String(left).length > maxLeft) {
                    var keep = Math.max(maxLeft - 3, 0);
                    left = String(left).slice(0, keep) + '...';
                }
                cmd('BT /' + f + ' ' + sz + ' Tf 1 0 0 1 ' + LM + ' ' + by(l._y, sz) + ' Tm (' + self.pdfEscape(left) + ') Tj ET');
                cmd('BT /F2 ' + sz + ' Tf 1 0 0 1 ' + rx(right, sz) + ' ' + by(l._y, sz) + ' Tm (' + self.pdfEscape(right) + ') Tj ET');
                return;
            }
            cmd('BT /' + f + ' ' + sz + ' Tf 1 0 0 1 ' + LM + ' ' + by(l._y, sz) + ' Tm (' + self.pdfEscape(left) + ') Tj ET');
        });

        var stream = out.join('\n');

        // ---- Assemble PDF document ----
        var objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + W + ' ' + pageH + '] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>',
            '<< /Length ' + stream.length + ' >>\nstream\n' + stream + '\nendstream'
        ];
        var pdf = '%PDF-1.4\n';
        var offsets = [];
        objects.forEach(function (o, i) {
            offsets.push(pdf.length);
            pdf += (i + 1) + ' 0 obj\n' + o + '\nendobj\n';
        });
        var xrefPos = pdf.length;
        var n = objects.length + 1;
        pdf += 'xref\n0 ' + n + '\n0000000000 65535 f \n';
        for (var i = 0; i < objects.length; i++) {
            pdf += ('0000000000' + offsets[i]).slice(-10) + ' 00000 n \n';
        }
        pdf += 'trailer\n<< /Size ' + n + ' /Root 1 0 R >>\nstartxref\n' + xrefPos + '\n%%EOF';

        var bytes = new Uint8Array(pdf.length);
        for (var j = 0; j < pdf.length; j++) bytes[j] = pdf.charCodeAt(j) & 0xff;
        return bytes;
    },

    saleToLines: function (sale) {
        var self = this;
        var lines = [];
        function cen(t, o) { lines.push(Object.assign({ c: 1, t: t }, o || {})); }
        function row(l, r, o) {
            var x = { t: l };
            if (r !== undefined && r !== null && r !== '') x.r = r;
            lines.push(Object.assign(x, o || {}));
        }
        var bar = this.bar();
        cen(bar.receipt_header || bar.name || 'THE BAREFOOT LOUNGE', { sz: 16, b: 1 });
        if (bar.address) cen(bar.address);
        if (bar.phone) cen('Tel: ' + bar.phone);
        lines.push({ rule: 'dash', gap: 7 });
        cen('DOCKET', { sz: 13, b: 1 });
        cen(sale.id || '', { sz: 11, b: 1 });
        cen(self.dateTime(sale.date));
        lines.push({ rule: 'dash' });
        row('Counter:', sale.counter);
        row('Table:', sale.table);
        row('Served by:', sale.staff);
        row('Payment:', sale.paymentMethod);
        if (sale.paymentRef) row('Ref:', sale.paymentRef);
        lines.push({ rule: 'dash' });
        (sale.items || []).forEach(function (it) {
            row(it.name + ' x' + it.qty, self.cur() + ' ' + self.money(it.price * it.qty));
        });
        lines.push({ rule: 'dash' });
        row('Subtotal:', self.cur() + ' ' + self.money(sale.subtotal));
        lines.push({ rule: 'thick', gap: 6 });
        row('TOTAL:', self.cur() + ' ' + self.money(sale.total || sale.subtotal), { b: 1, sz: 11 });
        if (sale.notes && String(sale.notes).replace(/\s+/g, ' ').trim()) {
            lines.push({ note: sale.notes, gap: 6 });
        }
        lines.push({ rule: 'dash', gap: 6 });
        cen(bar.receipt_footer || 'Thank you for your purchase!');
        return lines;
    },

    billToLines: function (sales) {
        var self = this;
        var lines = [];
        function cen(t, o) { lines.push(Object.assign({ c: 1, t: t }, o || {})); }
        function row(l, r, o) {
            var x = { t: l };
            if (r !== undefined && r !== null && r !== '') x.r = r;
            lines.push(Object.assign(x, o || {}));
        }
        var grand = 0;
        var bar = this.bar();
        cen(bar.receipt_header || bar.name || 'THE BAREFOOT LOUNGE', { sz: 16, b: 1 });
        if (bar.address) cen(bar.address);
        if (bar.phone) cen('Tel: ' + bar.phone);
        lines.push({ rule: 'dash', gap: 7 });
        cen('BILL', { sz: 13, b: 1 });
        cen(self.dateTime(new Date()));
        lines.push({ rule: 'dash' });
        (sales || []).forEach(function (sale) {
            grand += sale.total || 0;
            lines.push({ t: (sale.id || '') + ' - ' + (sale.counter || '') + ' (' + (sale.table || '') + ')', b: 1, sz: 8, gap: 4 });
            (sale.items || []).forEach(function (it) {
                row(it.name + ' x' + it.qty, self.cur() + ' ' + self.money(it.price * it.qty));
            });
            row('Subtotal', self.cur() + ' ' + self.money(sale.subtotal));
            row('Served by', sale.staff);
        });
        lines.push({ rule: 'thick', gap: 6 });
        row('GRAND TOTAL', self.cur() + ' ' + self.money(grand), { b: 1, sz: 11 });
        lines.push({ rule: 'dash', gap: 6 });
        cen(bar.receipt_footer || 'Thank you for your purchase!');
        return lines;
    },

    saveBlob: function (bytes, filename) {
        var blob = new Blob([bytes], { type: 'application/pdf' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
    },

    saveDocketPdf: function (sale) {
        if (!sale) return;
        this.saveBlob(this.receiptPdf(this.saleToLines(sale)), (sale.id || 'docket') + '.pdf');
    },

    saveBillPdf: function (sales) {
        this.saveBlob(this.receiptPdf(this.billToLines(sales)), 'bill-' + new Date().toISOString().slice(0, 10) + '.pdf');
    },

    document: function (innerHtml, title) {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + this.esc(title || 'Receipt') + '</title><style>'
            + '@page { size: 80mm auto; margin: 0; }'
            + 'html, body { margin: 0; padding: 0; background: #fff; }'
            + 'body { width: 80mm; font-family: "Courier New", Courier, monospace; font-size: 14px; line-height: 1.45; color: #111111; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-weight: 600; letter-spacing: 0.01em; text-rendering: geometricPrecision; }'
            + '.receipt { width: 80mm; padding: 4mm 4mm; box-sizing: border-box; }'
            + 'div { page-break-inside: avoid; }'
            + 'strong, b { font-weight: 700; }'
            + '</style></head><body><div class="receipt">' + innerHtml + '</div></body></html>';
    },

    // Prints only the receipt (never the surrounding app) through a hidden 80mm frame.
    // callback('printed' | 'dialog' | 'blocked')
    print: function (innerHtml, title, callback) {
        var done = typeof callback === 'function' ? callback : function () {};
        if (!innerHtml) { done('blocked'); return; }

        var frame = document.getElementById('bms-print-frame');
        if (frame && frame.parentNode) frame.parentNode.removeChild(frame);

        frame = document.createElement('iframe');
        frame.id = 'bms-print-frame';
        frame.setAttribute('aria-hidden', 'true');
        frame.style.cssText = 'position:absolute;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(frame);

        var printed = false;
        var win, doc;
        try {
            win = frame.contentWindow;
            doc = win.document;
            doc.open();
            doc.write(this.document(innerHtml, title));
            doc.close();
            win.addEventListener('afterprint', function () { printed = true; });
        } catch (e) {
            done('blocked');
            return;
        }

        setTimeout(function () {
            try {
                win.focus();
                win.print();
            } catch (e) {
                done('blocked');
                return;
            }
            setTimeout(function () { done(printed ? 'printed' : 'dialog'); }, 1200);
        }, 150);
    },
};
</script>
