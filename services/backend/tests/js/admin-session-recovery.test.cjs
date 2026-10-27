const { test } = require('node:test');
const assert = require('node:assert/strict');
const { webcrypto } = require('node:crypto');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const source = readFileSync(resolve(__dirname, '../../public/js/admin-workspace.js'), 'utf8');
const scriptModule = { exports: {} };
new Function('module', source)(scriptModule);
const { clean, stable, mergePresent, DraftStore, start } = scriptModule.exports;
const key = Buffer.alloc(32, 7).toString('base64');
function memoryStorage() {
    const map = new Map();
    return { getItem: k => map.get(k) || null, setItem: (k, v) => map.set(k, v), removeItem: k => map.delete(k),
        key: n => [...map.keys()][n], get length() { return map.size; }, map };
}
const settle = async () => { for (let i = 0; i < 12; i++) await new Promise(resolve => setTimeout(resolve, 5)); };
function boot({ status = 200, userId = '1', storage = memoryStorage(), data = { name: 'Original' }, actions = [], diagnostics = false, xhr } = {}) {
    const events = {}, hooks = {}, notices = [], requests = [];
    const listeners = {};
    let tick, watch, state = structuredClone(data);
    const component = { id: 'component-1', name: 'edit-product', $wire: {
        $get: field => field === 'mountedActions' ? actions : state, $watch: (_, callback) => { watch = callback; },
        $set: (field, value, live) => { assert.equal(live, false); state = value; queueMicrotask(() => watch?.(state)); },
    } };
    const element = () => ({ style: {}, setAttribute() {}, children: [], addEventListener(name, fn) { this[name] = fn; },
        appendChild(child) { this.children.push(child); }, remove() { this.removed = true; } });
    const doc = { hidden: false, addEventListener: (name, fn) => { events[name] = fn; },
        querySelector: () => ({ content: 'old-token' }), querySelectorAll: () => [],
        createElement: element, body: { appendChild: node => notices.push(node) } };
    const win = { document: doc, sessionStorage: storage, crypto: webcrypto,
        Headers, URL, Response, XMLHttpRequest: xhr, location: { href: 'https://shop.test/admin_sv/products/8/edit', origin: 'https://shop.test', pathname: '/admin_sv/products/8/edit' },
        setInterval: fn => { tick = fn; }, addEventListener: (name, fn) => { listeners[name] = fn; },
        queueMicrotask, open() {}, Livewire: { all: () => [component], hook: (name, fn) => { hooks[name] = fn; } },
        fetch: async (url, options) => {
            requests.push({ url, options });
            if (url === '/admin_sv/session-status') return new Response(JSON.stringify({ user_id: userId, csrf_token: 'fresh-token' }), { status: typeof status === 'function' ? status() : status });
            return new Response('{}', { status: 200 });
        },
    };
    start(win, { userId: '1', draftKey: key, statusUrl: '/admin_sv/session-status', interval: 60000, loginUrl: '/admin_sv/login', diagnosticsUrl: diagnostics ? '/admin_sv/operator-diagnostics' : null });
    return { win, doc, events, listeners, hooks, notices, requests, storage, tick: () => tick(),
        change: value => { state = value; watch(value); }, state: () => state };
}

test('encrypted draft survives reload; user/page and authenticated encryption are isolated', async () => {
    const storage = memoryStorage();
    const store = new DraftStore(storage, webcrypto, key, '1');
    const draft = { base: {}, data: { name: 'Private draft' }, time: Date.now() };
    await store.save('/product/8', draft);
    assert.ok(![...storage.map.values()][0].includes('Private draft'));
    assert.deepEqual(await new DraftStore(storage, webcrypto, key, '1').load('/product/8'), draft);
    assert.equal(await store.load('/product/9'), null);
    assert.equal(await new DraftStore(storage, webcrypto, key, '2').load('/product/8'), null);
    const wrong = Buffer.alloc(32, 8).toString('base64');
    assert.equal(await new DraftStore(storage, webcrypto, wrong, '1').load('/product/8'), null);
});

test('expired drafts are removed', async () => {
    const storage = memoryStorage(), store = new DraftStore(storage, webcrypto, key, '1');
    await store.save('page', { time: Date.now() - 86400001, data: {} });
    assert.equal(await store.load('page'), null); assert.equal(storage.length, 0);
});

test('secrets and temporary uploads are excluded; deleted repeater rows stay deleted', () => {
    const current = { name: 'Old', password: 'private', photo: 'livewire-file:tmp.png', rows: { a: { name: 'A' }, b: { name: 'B' } } };
    const data = clean(current);
    assert.ok(!('password' in data)); assert.ok(!('photo' in data));
    const restored = mergePresent(current, { name: 'New', rows: { a: { name: 'Edited' } } });
    assert.equal(restored.password, 'private'); assert.equal(restored.photo, current.photo);
    assert.ok(!('b' in restored.rows));
    assert.equal(stable({ a: 1, b: 2 }), stable({ b: 2, a: 1 }));
});

test('hidden/idle workspace still renews the session', async () => {
    const app = boot(); app.doc.hidden = true;
    await app.tick(); await app.tick();
    assert.equal(app.requests.length, 2); assert.equal(app.notices.length, 0);
});

test('a fresh token is fetched BEFORE an action, without replaying mutations', async () => {
    const app = boot();
    await app.win.fetch('/livewire/update', { method: 'POST', headers: { 'X-Livewire': '' }, body: JSON.stringify({ _token: 'expired', components: [] }) });
    assert.equal(app.requests.length, 2);
    assert.equal(app.requests[0].url, '/admin_sv/session-status');
    assert.equal(JSON.parse(app.requests[1].options.body)._token, 'fresh-token');
});

test('expired/revoked or changed-user session never submits the old form', async () => {
    for (const options of [{ status: 401 }, { status: 403 }, { userId: '2' }]) {
        const app = boot(options);
        await assert.rejects(app.win.fetch('/livewire/update', { method: 'POST', headers: { 'X-Livewire': '' }, body: '{}' }), /preflight failed/);
        assert.equal(app.requests.length, 1);
        assert.equal(app.notices.length, 1);
    }
});

test('network/server errors are not reported as expiration', async () => {
    const app = boot({ status: 503 }); await app.tick();
    assert.equal(app.notices.length, 0);
});

test('diagnostics retain the failed preflight across refresh and deliver only technical fields after reconnection', async () => {
    const storage = memoryStorage();
    const app = boot({ status: 503, storage, diagnostics: true });
    await assert.rejects(app.win.fetch('/livewire/update', { method: 'POST', headers: { 'X-Livewire': '' },
        body: JSON.stringify({ components: [{ calls: [{ method: '_startUpload', params: ['private-photo.jpg'] }], updates: { description: 'private-text' } }] }) }));
    const saved = storage.getItem('admin-diagnostics:v1:1');
    assert.ok(saved.includes('heartbeat_http'));
    assert.ok(!saved.includes('private-'));
    assert.equal(app.requests[0].options.headers['X-Operator-Action'], 'upload');
    const refreshed = boot({ storage, diagnostics: true });
    await refreshed.tick(); await settle();
    const report = refreshed.requests.find(request => request.url === '/admin_sv/operator-diagnostics');
    assert.ok(report);
    const body = JSON.parse(report.options.body);
    assert.equal(body.events[0].status, 503);
    assert.equal(body.events[0].event, 'heartbeat_http');
    assert.equal(storage.getItem('admin-diagnostics:v1:1'), '[]');
});

test('diagnostics capture an upload rejected by the proxy without inspecting its body or signed URL', async () => {
    class Xhr {
        open(method, url) { this.nativeUrl = url; }
        send(body) { this.nativeBody = body; this.status = 413; this.done?.(); }
        addEventListener(event, fn) { if (event === 'loadend') this.done = fn; }
        getResponseHeader() { return null; }
    }
    const app = boot({ diagnostics: true, xhr: Xhr });
    const xhr = new app.win.XMLHttpRequest();
    xhr.open('POST', '/livewire/upload-file?signature=private-signature');
    xhr.send('private-file-content');
    const saved = app.storage.getItem('admin-diagnostics:v1:1');
    assert.equal(JSON.parse(saved)[0].status, 413);
    assert.equal(JSON.parse(saved)[0].event, 'upload_http');
    assert.ok(!saved.includes('private-'));
    assert.equal(xhr.nativeBody, 'private-file-content');
    await app.tick(); await settle();
    assert.ok(app.requests.some(request => request.url === '/admin_sv/operator-diagnostics'));
});

test('an open offer editor warns before refresh even when the parent resource draft is durable', async () => {
    const actions = [{ name: 'edit', data: { name: 'Second offer', description: 'Unsaved text' } }];
    const app = boot({ actions }); await settle();
    let prevented = false;
    app.listeners.beforeunload({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    actions.length = 0; prevented = false;
    app.listeners.beforeunload({ preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
});

test('failed connection before saving shows a notice instead of the Livewire error screen and never submits', async () => {
    const app = boot({ status: 503 });
    await settle(); app.change({ name: 'Keep the sofa edits' }); await settle();
    await assert.rejects(app.win.fetch('/livewire/update', { method: 'POST', headers: { 'X-Livewire': '' }, body: '{}' }), /preflight failed/);
    let failure, prevented = 0;
    app.hooks.request({ fail: callback => { failure = callback; } });
    failure({ status: 503, preventDefault() { prevented++; } });
    failure({ status: 503, preventDefault() { prevented++; } });
    assert.equal(prevented, 2);
    assert.equal(app.notices.length, 1);
    assert.match(app.notices[0].children[0].textContent, /ещё не сохранены/);
    assert.equal(app.requests.length, 1);
    assert.equal(app.storage.length, 1);
    assert.equal(app.state().name, 'Keep the sofa edits');
});

test('real Livewire upload-start request does not show an error modal even without a request hook', async () => {
    const livewire = readFileSync(resolve(__dirname, '../../vendor/livewire/livewire/dist/livewire.js'), 'utf8');
    const sendSource = livewire.slice(livewire.indexOf('async function sendRequest(pool)'), livewire.indexOf('function handlePageExpiry()'));
    assert.match(sendSource, /catch \(e\)/);
    for (const status of [401, 503]) {
        const app = boot({ status });
        let failed = 0, modal = 0;
        const run = new Function('fetch', 'getCsrfToken', 'getUpdateUri', 'trigger2', 'showFailureModal', `${sendSource}; return sendRequest;`)(
            app.win.fetch, () => 'old', () => '/livewire/update', () => () => {}, () => modal++,
        );
        await run({ payload: () => [[{ calls: [{ method: '_startUpload' }] }], () => {}, () => failed++] });
        assert.equal(failed, 1);
        assert.equal(modal, 0);
        assert.equal(app.requests.length, 1);
        assert.equal(app.notices.length, 1);
    }
});

test('form draft auto-restores after F5 without committing a server action', async () => {
    const storage = memoryStorage(), app = boot({ storage }); await settle();
    app.change({ name: 'Two hours of work' }); await settle();
    const refreshed = boot({ storage }); await settle();
    assert.equal(refreshed.state().name, 'Two hours of work');
    assert.equal(refreshed.requests.length, 0);
    refreshed.events['admin-form-saved']({ detail: { componentId: 'component-1' } }); await settle();
    assert.equal(storage.length, 0);
});

test('newer server edits are not overwritten automatically', async () => {
    const storage = memoryStorage(), app = boot({ storage }); await settle();
    app.change({ name: 'My edits' }); await settle();
    const other = boot({ storage, data: { name: 'Other operator edits' } }); await settle();
    assert.equal(other.state().name, 'Other operator edits');
    assert.equal(other.notices.length, 1); assert.equal(storage.length, 1);
});

test('rapid typing persists the newest state, and canceling changes removes the draft', async () => {
    const app = boot(); await settle();
    app.change({ name: 'First key' }); app.change({ name: 'Last key' }); await settle();
    const refreshed = boot({ storage: app.storage }); await settle();
    assert.equal(refreshed.state().name, 'Last key');
    app.change({ name: 'Original' }); await settle();
    assert.equal(app.storage.length, 0);
});

test('explicit logout removes encrypted drafts from the current user tab', async () => {
    const app = boot(); await settle(); app.change({ name: 'Do not leak after logout' }); await settle();
    app.events.submit({ target: { action: 'https://shop.test/admin_sv/logout' } }); await settle();
    assert.equal(app.storage.length, 0);
});

test('validation/server failures preserve draft; storage failure protects against accidental reload', async () => {
    const app = boot(); await settle(); app.change({ name: 'Invalid but valuable draft' }); await settle();
    let failure;
    app.hooks.request({ fail: callback => { failure = callback; } });
    failure({ status: 422, preventDefault() {} });
    assert.equal(app.storage.length, 1);
    const blocked = memoryStorage(); blocked.setItem = () => { throw new Error('Quota'); };
    const noStorage = boot({ storage: blocked }); await settle(); noStorage.change({ name: 'Keep this' }); await settle();
    let prevented = false;
    noStorage.listeners.beforeunload({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
});
