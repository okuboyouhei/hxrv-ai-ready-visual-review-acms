// =============================================================================
// a-blog cms CSRF トークン取得
// <meta name="csrf-token"> から取得して formToken フィールドとして送信する
// =============================================================================

function hxrvGetCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

/**
 * hxrv-acms.js
 * HXRV AI-Ready Visual Review — a-blog cms 版
 *
 * WP版 hxrv-overlay.js からの主な変更点:
 *   - ajaxurl → data-api-base 属性から取得
 *   - nonce → 不要（ログインセッションCookieで認証）
 *   - POST body: action= → ACMS_POST_Hxrv* トリガー名
 *   - WP固有クラスフィルター除外 → a-blog cms固有クラス除外に変更
 *   - hxrv-overlay.js の outerHTML swap 問題はここでは発生しない（DOMは触らない）
 *
 * 依存: Alpine.js 3.x（バンドル済みまたはCDN）
 */

// =============================================================================
// セレクタ生成 (CSS Selector Generator)
// WP版: -is-layout, wp-block-* を除外 → ここでは acms-*, js-acms-* を除外
// =============================================================================

function hxrvGenerateSelector(el) {
    if (!el || el === document.body) return 'body';

    // 1. ID優先（a-blog cms動的IDは除外）
    if (el.id && el.id.trim() !== '') {
        const id = el.id.trim();
        const isAcmsDynamic = /^acms-\d/.test(id) || /^js-acms/.test(id);
        if (!isAcmsDynamic) {
            return '#' + CSS.escape(id);
        }
    }

    // 2. 安定したクラスの組み合わせ
    const stableClasses = [...el.classList].filter(cls =>
        !cls.startsWith('js-')      &&  // JSフック用クラス（a-blog cms / 一般）
        !cls.startsWith('is-')      &&  // 状態クラス
        !cls.startsWith('acms-')    &&  // a-blog cms固有
        !cls.startsWith('p-acms')   &&  // テーマ固有（UTSUWA等）
        !/^\d/.test(cls)            &&  // 数字始まり（不安定）
        !/\d{3,}/.test(cls)            // 3桁以上の数字を含む（IDっぽい）
    );

    if (stableClasses.length > 0) {
        // 最大3クラスで絞り込み
        const selector = stableClasses.slice(0, 3).map(c => '.' + CSS.escape(c)).join('');

        try {
            const matches = document.querySelectorAll(selector);
            if (matches.length === 1) return selector;

            // 親要素内での順番を追加
            const siblings = [...el.parentElement.querySelectorAll(selector)];
            const nth = siblings.indexOf(el) + 1;
            if (nth > 0) return `${selector}:nth-of-type(${nth})`;
        } catch (e) { /* セレクタ構文エラー回避 */ }
    }

    // 3. タグ + nth-of-type でフォールバック
    const tag = el.tagName.toLowerCase();
    const siblings = el.parentElement
        ? [...el.parentElement.children].filter(c => c.tagName === el.tagName)
        : [];
    const nth = siblings.indexOf(el) + 1;
    const parentSel = hxrvGenerateSelector(el.parentElement);

    return parentSel
        ? `${parentSel} > ${tag}:nth-of-type(${nth})`
        : `${tag}:nth-of-type(${nth})`;
}

// =============================================================================
// テキスト抜粋 (120文字: 3段再アンカー用)
// WP版と同一
// =============================================================================

function hxrvGetTextExcerpt(el, maxLen = 120) {
    return (el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, maxLen);
}

// =============================================================================
// 3段再アンカー
// Stage 1: CSSセレクタ
// Stage 2: テキスト抜粋でDOM検索
// Stage 3: orphan（配置不可）
// WP版と同一ロジック
// =============================================================================

function hxrvResolveAnchor(pin) {
    // Stage 1: セレクタ
    if (pin.pin_selector) {
        try {
            const el = document.querySelector(pin.pin_selector);
            if (el) return { el, stage: 'selector' };
        } catch (e) { /* 無効なセレクタ */ }
    }

    // Stage 2: テキスト抜粋マッチ
    if (pin.pin_text_excerpt) {
        const excerpt = pin.pin_text_excerpt.trim();
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_ELEMENT);
        let node;
        while ((node = walker.nextNode())) {
            const text = (node.textContent || '').replace(/\s+/g, ' ').trim();
            if (text.startsWith(excerpt.slice(0, 30))) {
                return { el: node, stage: 'text' };
            }
        }
    }

    // Stage 3: orphan
    return { el: null, stage: 'orphan' };
}

// =============================================================================
// Alpine.js アプリ本体
// =============================================================================

function hxrvApp() {
    return {
        // ----- 状態 -----
        panelOpen: false,
        isPlacing: false,
        pins: [],
        activePinId: null,
        showForm: false,
        formComment: '',
        pendingPin: null,   // { selector, offsetX, offsetY, textExcerpt }
        isLoading: false,
        exportDone: false,

        // ----- 設定（DOMから読み取り） -----
        get root()    { return document.getElementById('hxrv-root'); },
        get apiBase() { return this.root?.dataset.apiBase ?? ''; },
        get pageUrl() { return window.location.pathname; },

        // ----- 初期化 -----
        async init() {
            await this.loadPins();

            // リサイズ時にマーカー再描画
            window.addEventListener('resize', () => {
                requestAnimationFrame(() => this.renderMarkers());
            });
        },

        // ----- ピン一覧取得 -----
        // WP版: fetch(ajaxurl, { body: formData }) → fetch(apiBase + '?action=list&...')
        async loadPins() {
            if (!this.apiBase) return;
            this.isLoading = true;
            try {
                const url = `${this.apiBase}?action=list&page_url=${encodeURIComponent(this.pageUrl)}`;
                const res = await fetch(url, { credentials: 'same-origin' });
                const data = await res.json();
                if (data.success) {
                    this.pins = data.pins || [];
                    this.$nextTick(() => this.renderMarkers());
                }
            } catch (e) {
                console.error('[HXRV] loadPins error:', e);
            } finally {
                this.isLoading = false;
            }
        },

        // ----- ピン配置モード開始 -----
        startPlacing() {
            if (this.isPlacing) {
                this.cancelPlacing();
                return;
            }
            this.isPlacing = true;
            this.panelOpen = false;
            document.body.style.cursor = 'crosshair';

            // ハンドラをここで生成（thisを確実にキャプチャ）
            this._clickHandler = (e) => this._handlePageClick(e);
            this._escHandler   = (e) => { if (e.key === 'Escape') this.cancelPlacing(); };

            document.addEventListener('click',   this._clickHandler, { capture: true, once: true });
            document.addEventListener('keydown', this._escHandler);  // once不要・手動解除
        },

        cancelPlacing() {
            this.isPlacing = false;
            document.body.style.cursor = '';
            if (this._clickHandler) {
                document.removeEventListener('click', this._clickHandler, { capture: true });
                this._clickHandler = null;
            }
            if (this._escHandler) {
                document.removeEventListener('keydown', this._escHandler);
                this._escHandler = null;
            }
        },

        _clickHandler: null,
        _escHandler:   null,

        // ----- ページクリック処理 -----
        _handlePageClick(e) {
            // Escハンドラを解除（クリックで確定したので不要）
            if (this._escHandler) {
                document.removeEventListener('keydown', this._escHandler);
                this._escHandler = null;
            }

            // HXRVパネル内クリックは無視
            const root = document.getElementById('hxrv-root');
            if (root && root.contains(e.target)) {
                this.isPlacing = false;
                document.body.style.cursor = '';
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            this.isPlacing = false;
            document.body.style.cursor = '';

            const target = e.target;
            const rect   = target.getBoundingClientRect();

            const offsetX = rect.width  > 0 ? ((e.clientX - rect.left) / rect.width  * 100).toFixed(2) : 50;
            const offsetY = rect.height > 0 ? ((e.clientY - rect.top)  / rect.height * 100).toFixed(2) : 50;

            this.pendingPin = {
                selector:    hxrvGenerateSelector(target),
                offsetX:     parseFloat(offsetX),
                offsetY:     parseFloat(offsetY),
                textExcerpt: hxrvGetTextExcerpt(target),
            };

            this.formComment = '';
            this.showForm    = true;
            this.panelOpen   = true;
        },

        // ----- ピン保存 -----
        // WP版との差分: action=hxrv_save_pin + nonce → ACMS_POST_HxrvPinSave
        async savePin() {
            if (!this.pendingPin || !this.formComment.trim()) return;

            const fd = new FormData();
            fd.append('ACMS_POST_HxrvPinSave', 'submit');
            fd.append('formToken', hxrvGetCsrfToken());
            fd.append('page_url',     this.pageUrl);
            fd.append('selector',     this.pendingPin.selector);
            fd.append('offset_x',     this.pendingPin.offsetX);
            fd.append('offset_y',     this.pendingPin.offsetY);
            fd.append('comment',      this.formComment.trim());
            fd.append('text_excerpt', this.pendingPin.textExcerpt);

            try {
                const res  = await fetch(this.apiBase, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.success) {
                    this.showForm    = false;
                    this.pendingPin  = null;
                    this.formComment = '';
                    await this.loadPins();
                } else {
                    alert('保存に失敗しました: ' + (data.error || ''));
                }
            } catch (e) {
                console.error('[HXRV] savePin error:', e);
            }
        },

        // ----- ピン削除 -----
        async deletePin(pinId) {
            if (!confirm('このピンを削除しますか？')) return;

            const fd = new FormData();
            fd.append('ACMS_POST_HxrvPinDelete', 'submit');
            fd.append('formToken', hxrvGetCsrfToken());
            fd.append('pin_id', pinId);

            try {
                const res  = await fetch(this.apiBase, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.success) {
                    this.activePinId = null;
                    await this.loadPins();
                }
            } catch (e) {
                console.error('[HXRV] deletePin error:', e);
            }
        },

        // ----- ステータス更新 -----
        async toggleStatus(pin) {
            const newStatus = pin.pin_status === 'open' ? 'resolved' : 'open';

            const fd = new FormData();
            fd.append('ACMS_POST_HxrvPinStatus', 'submit');
            fd.append('formToken', hxrvGetCsrfToken());
            fd.append('pin_id', pin.pin_id);
            fd.append('status', newStatus);

            try {
                const res  = await fetch(this.apiBase, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (data.success) {
                    await this.loadPins();
                }
            } catch (e) {
                console.error('[HXRV] toggleStatus error:', e);
            }
        },

        // ----- Markdown エクスポート -----
        async exportMarkdown() {
            const url = `${this.apiBase}?action=export&page_url=${encodeURIComponent(this.pageUrl)}`;
            try {
                const res  = await fetch(url, { credentials: 'same-origin' });
                const data = await res.json();
                if (data.success) {
                    await navigator.clipboard.writeText(data.markdown);
                    this.exportDone = true;
                    setTimeout(() => { this.exportDone = false; }, 2000);
                }
            } catch (e) {
                console.error('[HXRV] export error:', e);
            }
        },

        // ----- ピンマーカー描画 -----
        // WP版: outerHTML swap と相性最悪 → innerHTML による再描画の一本道
        renderMarkers() {
            // 既存マーカーを全削除
            document.querySelectorAll('.hxrv-pin-marker').forEach(m => m.remove());

            const scrollTop  = window.pageYOffset  || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

            this.pins.forEach((pin) => {
                const { el, stage } = hxrvResolveAnchor(pin);
                if (!el) return; // orphan: マーカー非表示

                const rect = el.getBoundingClientRect();
                const left = rect.left + scrollLeft + (rect.width  * (parseFloat(pin.pin_offset_x) / 100));
                const top  = rect.top  + scrollTop  + (rect.height * (parseFloat(pin.pin_offset_y) / 100));

                const btn = document.createElement('button');
                btn.className          = 'hxrv-pin-marker';
                btn.dataset.pinId      = pin.pin_id;
                btn.dataset.stage      = stage;
                btn.dataset.status     = pin.pin_status;
                btn.setAttribute('aria-label', `ピン #${pin.pin_id}`);
                btn.style.left         = `${left}px`;
                btn.style.top          = `${top}px`;
                btn.style.position     = 'absolute';

                btn.innerHTML = `<span class="hxrv-pin-marker__num">${pin.pin_id}</span>`;

                btn.addEventListener('click', () => {
                    this.activePinId = pin.pin_id;
                    this.panelOpen   = true;
                });

                document.body.appendChild(btn);
            });
        },

        // ----- ピン位置へスクロール -----
        scrollToPin(pin) {
            const { el } = hxrvResolveAnchor(pin);
            if (!el) return;

            el.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // マーカーを一時ハイライト
            const marker = document.querySelector(`.hxrv-pin-marker[data-pin-id="${pin.pin_id}"]`);
            if (marker) {
                marker.classList.add('hxrv-pin-marker--highlight');
                setTimeout(() => marker.classList.remove('hxrv-pin-marker--highlight'), 1500);
            }
        },

        // ----- アクティブピン取得 -----
        get activePin() {
            if (!this.activePinId) return null;
            return this.pins.find(p => String(p.pin_id) === String(this.activePinId)) || null;
        },

        get openPins() {
            return this.pins.filter(p => p.pin_status === 'open');
        },

        get resolvedPins() {
            return this.pins.filter(p => p.pin_status === 'resolved');
        },
    };
}

// hxrvApp() はグローバル関数。<head>で先読みするため Alpine 起動前に定義済み。
