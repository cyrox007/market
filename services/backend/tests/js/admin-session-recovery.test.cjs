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
function boot({ status = 200, userId = '1', storage = memoryStorage(), data = { name: 'Original' } } = {}) {
    const events = {}, hooks = {}, notices = [], requests = [];
    const listeners = {};
    let tick, watch, state = structuredClone(data);
    const component = { id: 'component-1', name: 'edit-product', $wire: {
        $get: () => state, $watch: (_, callback) => { watch = callback; },
        $set: (field, value, live) => { assert.equal(live, false); state = value; queueMicrotask(() => watch?.(state)); },
    } };
    const element = () => ({ style: {}, setAttribute() {}, children: [], addEventListener(name, fn) { this[name] = fn; },
        appendChild(child) { this.children.push(child); }, remove() { this.removed = true; } });
    const doc = { hidden: false, addEventListener: (name, fn) => { events[name] = fn; },
        querySelector: () => ({ content: 'old-token' }), querySelectorAll: () => [],
        createElement: element, body: { appendChild: node => notices.push(node) } };
    const win = { document: doc, sessionStorage: storage, crypto: webcrypto,
        Headers, URL, Response, location: { href: 'https://shop.test/admin_sv/products/8/edit', origin: 'https://shop.test', pathname: '/admin_sv/products/8/edit' },
        setInterval: fn => { tick = fn; }, addEventListener: (name, fn) => { listeners[name] = fn; },
        queueMicrotask, open() {}, Livewire: { all: () => [component], hook: (name, fn) => { hooks[name] = fn; } },
        fetch: async (url, options) => {
            requests.push({ url, options });
            if (url === '/admin_sv/session-status') return new Response(JSON.stringify({ user_id: userId, csrf_token: 'fresh-token' }), { status });
            return new Response('{}', { status: 200 });
        },
    };
    start(win, { userId: '1', draftKey: key, statusUrl: '/admin_sv/session-status', interval: 60000, loginUrl: '/admin_sv/login' });
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
        const response = await app.win.fetch('/livewire/update', { method: 'POST', headers: { 'X-Livewire': '' }, body: '{}' });
        assert.equal(response.status, 401); assert.equal(app.requests.length, 1);
        assert.equal(app.notices.length, 1);
    }
});

test('network/server errors are not reported as expiration', async () => {
    const app = boot({ status: 503 }); await app.tick();
    assert.equal(app.notices.length, 0);
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
