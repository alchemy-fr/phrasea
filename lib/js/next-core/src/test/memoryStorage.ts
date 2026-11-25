// Node ships its own (broken, file-backed) localStorage that shadows jsdom's:
// importing this module gives the tests an in-memory one
const storage = new Map<string, string>();

Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {
        getItem: (key: string) => storage.get(key) ?? null,
        setItem: (key: string, value: string) => {
            storage.set(key, String(value));
        },
        removeItem: (key: string) => {
            storage.delete(key);
        },
        clear: () => storage.clear(),
    },
});
