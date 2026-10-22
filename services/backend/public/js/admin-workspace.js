/* Operator workspace: no action replay, no automatic reload, encrypted tab drafts. */
(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else if (root.adminWorkspaceConfig && !root.__adminWorkspace) {
        root.__adminWorkspace = true;
        api.start(root, root.adminWorkspaceConfig);
    }
})(typeof window === 'undefined' ? globalThis : window, function () {
    'use strict';
    const secret = /password|passwd|secret|token|api_key|private_key|credit_card|card_number|cvv/i;
    function clean(value, key = '') {
        if (secret.test(key)) return undefined;
        if (typeof value === 'string' && (value.startsWith('livewire-file:') || value.startsWith('livewire-files:'))) return undefined;
        if (Array.isArray(value)) return value.map(item => clean(item)).filter(item => item !== undefined);
        if (value && typeof value === 'object') return Object.fromEntries(Object.entries(value)
            .filter(([name]) => !secret.test(name) && !['__proto__', 'constructor', 'prototype'].includes(name))
            .map(([name, item]) => [name, clean(item, name)]).filter(([, item]) => item !== undefined));
        return value;
    }
    function stable(value) {
        if (Array.isArray(value)) return '[' + value.map(stable).join(',') + ']';
        if (value && typeof value === 'object') return '{' + Object.keys(value).sort().map(key => JSON.stringify(key) + ':' + stable(value[key])).join(',') + '}';
        return JSON.stringify(value);
    }
    function mergePresent(current, draft) {
        if (!current || !draft || Array.isArray(current) || Array.isArray(draft) || typeof current !== 'object' || typeof draft !== 'object') return draft;
        const merged = { ...draft };
        // Preserve excluded fields, but do not resurrect deleted repeater rows/values.
        for (const key of Object.keys(current)) {
            if (clean(current[key], key) === undefined) merged[key] = current[key];
            else if (key in draft) merged[key] = mergePresent(current[key], draft[key]);
        }
        return merged;
    }
    class DraftStore {
        constructor(storage, crypto, encodedKey, scope, encode = btoa, decode = atob) {
            this.storage = storage;
            this.crypto = crypto;
            this.prefix = 'admin-draft:v1:' + scope + ':';
            this.encode = encode; this.decode = decode;
            this.key = crypto.subtle.importKey('raw', Uint8Array.from(decode(encodedKey), c => c.charCodeAt(0)), 'AES-GCM', false, ['encrypt', 'decrypt']);
        }
        async save(id, draft) {
            const text = JSON.stringify(draft);
            if (text.length > 1500000) throw new Error('Draft too large');
            const iv = this.crypto.getRandomValues(new Uint8Array(12));
            const cipher = await this.crypto.subtle.encrypt({ name: 'AES-GCM', iv, additionalData: new TextEncoder().encode(this.prefix + id) }, await this.key, new TextEncoder().encode(text));
            const base64 = bytes => this.encode(Array.from(bytes, byte => String.fromCharCode(byte)).join(''));
            this.storage.setItem(this.prefix + id, JSON.stringify({ iv: base64(iv), cipher: base64(new Uint8Array(cipher)) }));
        }
        async load(id) {
            const raw = this.storage.getItem(this.prefix + id);
            if (!raw) return null;
            try {
                const saved = JSON.parse(raw);
                const bytes = text => Uint8Array.from(this.decode(text), c => c.charCodeAt(0));
                const plain = await this.crypto.subtle.decrypt({ name: 'AES-GCM', iv: bytes(saved.iv), additionalData: new TextEncoder().encode(this.prefix + id) }, await this.key, bytes(saved.cipher));
                const draft = JSON.parse(new TextDecoder().decode(plain));
                if (!draft.time || Date.now() - draft.time > 86400000) { this.remove(id); return null; }
                return draft;
            } catch (_) { return null; }
        }
        remove(id) { this.storage.removeItem(this.prefix + id); }
        clear() {
            for (let i = this.storage.length - 1; i >= 0; i--) {
                const key = this.storage.key(i);
                if (key?.startsWith(this.prefix)) this.storage.removeItem(key);
            }
        }
    }
    function start(win, config) {
        const doc = win.document;
        const originalFetch = win.fetch.bind(win);
        let probe = null;
        let loginNotice = null;
        const tracked = new Map();
        let store = null;
        try { store = new DraftStore(win.sessionStorage, win.crypto, config.draftKey, config.userId); } catch (_) { /* beforeunload still protects edits */ }
        function notice(text, actions = []) {
            const box = doc.createElement('section'); box.setAttribute('role', 'status');
            box.style.cssText = 'position:fixed;bottom:16px;right:16px;max-width:620px;z-index:2147483647;background:#fff;color:#18181b;border:1px solid #aaa;border-radius:10px;padding:16px;box-shadow:0 8px 30px #0003;font:15px/1.5 system-ui';
            const message = doc.createElement('p'); message.textContent = text; box.appendChild(message);
            for (const [label, action] of actions) {
                const button = doc.createElement('button'); button.type = 'button'; button.textContent = label;
                button.style.cssText = 'margin:8px;padding:6px 12px;border:1px solid #aaa;border-radius:6px';
                button.addEventListener('click', action); box.appendChild(button);
            }
            doc.body.appendChild(box); return box;
        }
        function needLogin() {
            if (!loginNotice) loginNotice = notice('Нужно подтвердить вход. Форма остаётся на месте; после входа можно продолжить работу.', [
                ['Войти в новой вкладке', () => win.open(config.loginUrl, '_blank', 'noopener')],
            ]);
        }
        function updateToken(token) {
            const meta = doc.querySelector('meta[name="csrf-token"]'); if (meta) meta.content = token;
            doc.querySelectorAll('input[name="_token"]').forEach(input => { input.value = token; });
            if (win.livewireScriptConfig) win.livewireScriptConfig.csrf = token;
            doc.querySelectorAll('script[data-csrf]').forEach(script => { script.dataset.csrf = token; });
        }
        async function session() {
            if (probe) return probe;
            probe = (async () => {
                try {
                    const response = await originalFetch(config.statusUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
                    if ([401, 403].includes(response.status)) { needLogin(); return null; }
                    if (!response.ok) return null;
                    const data = await response.json();
                    if (data.user_id !== config.userId) { needLogin(); return null; }
                    if (typeof data.csrf_token !== 'string' || !data.csrf_token) return null;
                    updateToken(data.csrf_token);
                    loginNotice?.remove(); loginNotice = null;
                    return data.csrf_token;
                } catch (_) { return null; } finally { probe = null; }
            })();
            return probe;
        }
        // Refresh CSRF BEFORE a Livewire action, including after sleep. Never replay mutations.
        win.fetch = async function (input, options) {
            const headers = new win.Headers(options?.headers);
            if (headers.has('X-Livewire') && typeof options?.body === 'string') {
                const url = new win.URL(typeof input === 'string' ? input : input.url, win.location.href);
                if (url.origin === win.location.origin) {
                    const token = await session();
                    if (!token) return new win.Response('{"message":"Подтвердите вход или проверьте соединение"}', { status: loginNotice ? 401 : 503, headers: { 'Content-Type': 'application/json' } });
                    const body = JSON.parse(options.body); body._token = token;
                    options = { ...options, body: JSON.stringify(body) };
                }
            }
            return originalFetch(input, options);
        };
        win.setInterval(session, config.interval); // Intentionally while idle AND hidden.
        win.addEventListener('focus', session); win.addEventListener('online', session);
        doc.addEventListener('visibilitychange', () => { if (!doc.hidden) session(); });
        function track(component) {
            // Resource forms only. Never restore component snapshots or action queues.
            if (!component.$wire || !component.name || tracked.has(component.id)) return;
            const state = component.$wire.$get('data');
            if (!state || typeof state !== 'object') return;
            const id = win.location.pathname + ':' + component.name;
            let base = clean(JSON.parse(JSON.stringify(state)));
            let current = stable(base);
            let dirty = false, ready = false, protectedDraft = false, editingStarted = false;
            let pending = Promise.resolve(), generation = 0, storageFailed = !store;
            const entry = {
                unsafe: () => dirty && (storageFailed || !ready),
                saved() {
                    generation++; dirty = false;
                    base = clean(JSON.parse(JSON.stringify(component.$wire.$get('data')))); current = stable(base);
                    store?.remove(id);
                    pending = pending.catch(() => {}).then(() => store?.remove(id));
                },
                clear() { generation++; pending = pending.catch(() => {}).then(() => store?.remove(id)); },
            };
            tracked.set(component.id, entry);
            function changed(value) {
                const data = clean(JSON.parse(JSON.stringify(value))), serialized = stable(data);
                if (serialized === current) return;
                editingStarted = true; current = serialized; dirty = serialized !== stable(base);
                if (!store || protectedDraft) return;
                ready = false; const version = ++generation;
                pending = pending.catch(() => {}).then(async () => {
                    if (version !== generation) return;
                    if (dirty) await store.save(id, { base, data, time: Date.now() }); else store.remove(id);
                    if (version === generation) { ready = true; storageFailed = false; }
                }).catch(() => { storageFailed = true; });
            }
            component.$wire.$watch('data', changed);
            if (!store) return;
            store.load(id).then(draft => {
                if (!draft || editingStarted) return;
                const restore = () => {
                    component.$wire.$set('data', mergePresent(component.$wire.$get('data'), draft.data), false);
                    current = stable(draft.data); dirty = true; ready = true; protectedDraft = false;
                    const info = notice('Несохранённый черновик восстановлен. Нажмите «Сохранить», когда закончите.', [['Закрыть', () => info.remove()]]);
                };
                if (stable(draft.base) === stable(base)) restore();
                else {
                    protectedDraft = true;
                    const conflict = notice('Есть черновик, но данные на сервере уже изменились. Не заменяем их автоматически.', [
                        ['Восстановить мой черновик', () => { restore(); conflict.remove(); }],
                        ['Оставить серверные данные', () => { store.remove(id); protectedDraft = false; conflict.remove();
                            current = ''; changed(component.$wire.$get('data')); }],
                    ]);
                }
            }).catch(() => { storageFailed = true; });
        }
        function install() {
            win.Livewire.hook('component.init', ({ component, cleanup }) => {
                win.queueMicrotask(() => track(component)); cleanup(() => tracked.delete(component.id));
            });
            win.Livewire.all().forEach(component => track(component.$wire ? component : component.__instance));
            win.Livewire.hook('request', ({ fail }) => fail(({ status, preventDefault }) => {
                if (status === 419 || status === 401) { preventDefault(); session(); }
            }));
            doc.addEventListener('admin-form-saved', event => tracked.get(event.detail.componentId)?.saved());
        }
        if (win.Livewire) install(); else doc.addEventListener('livewire:init', install, { once: true });
        win.addEventListener('beforeunload', event => {
            if ([...tracked.values()].some(entry => entry.unsafe())) { event.preventDefault(); event.returnValue = ''; }
        });
        doc.addEventListener('submit', event => {
            if (event.target?.action?.includes('/logout')) { store?.clear(); tracked.forEach(entry => entry.clear()); }
        }, true);
    }
    return { clean, stable, mergePresent, DraftStore, start };
});
