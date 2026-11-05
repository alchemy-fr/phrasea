import {vi} from 'vitest';
import {consumeStateNonce, createStateNonce} from './stateNonce';

function createStorage(): Pick<Storage, 'getItem' | 'setItem'> {
    const items = new Map<string, string>();

    return {
        getItem: (key: string) => items.get(key) ?? null,
        setItem: (key: string, value: string) => {
            items.set(key, value);
        },
    };
}

beforeEach(() => {
    vi.stubGlobal('window', {
        localStorage: createStorage(),
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

test('a nonce created by this browser is valid once', () => {
    const nonce = createStateNonce();

    expect(consumeStateNonce(nonce)).toBe(true);
    expect(consumeStateNonce(nonce)).toBe(false);
});

test('the login links of a page share one nonce, whatever the renders', () => {
    const otherTabNonce = 'other-tab-nonce';
    window.localStorage.setItem(
        'auth.state_nonces',
        JSON.stringify([otherTabNonce])
    );

    const nonce = createStateNonce();
    for (let i = 0; i < 50; i++) {
        expect(createStateNonce()).toBe(nonce);
    }

    expect(consumeStateNonce(otherTabNonce)).toBe(true);
    expect(consumeStateNonce(nonce)).toBe(true);
});

test('a new nonce replaces the consumed one of the page', () => {
    const nonce = createStateNonce();
    expect(consumeStateNonce(nonce)).toBe(true);

    const next = createStateNonce();
    expect(next).not.toBe(nonce);
    expect(consumeStateNonce(next)).toBe(true);
});

test('a nonce of the window that opened the login popup is accepted', () => {
    const nonce = createStateNonce();
    const openerStorage = window.localStorage;
    vi.stubGlobal('window', {
        localStorage: createStorage(),
        opener: {localStorage: openerStorage},
    });

    expect(consumeStateNonce(nonce)).toBe(true);
    expect(consumeStateNonce(nonce)).toBe(false);
});

test('a valid nonce is accepted when the storage cannot be written', () => {
    const nonce = createStateNonce();
    window.localStorage.setItem = () => {
        throw new Error('QuotaExceededError');
    };

    expect(consumeStateNonce(nonce)).toBe(true);
});

test('a foreign or missing nonce is refused', () => {
    createStateNonce();

    expect(consumeStateNonce('attacker-nonce')).toBe(false);
    expect(consumeStateNonce(undefined)).toBe(false);
});
