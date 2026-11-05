import {vi} from 'vitest';
import {consumeStateNonce, createStateNonce} from './stateNonce';

beforeEach(() => {
    const items = new Map<string, string>();
    vi.stubGlobal('window', {
        localStorage: {
            getItem: (key: string) => items.get(key) ?? null,
            setItem: (key: string, value: string) => items.set(key, value),
        },
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
