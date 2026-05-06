import MemoryStorage from './MemoryStorage';
import {IStorage} from './types';

declare global {
    interface Window {
        localStorageFallback?: IStorage;
    }
}

export function getLocalStorage(): IStorage {
    try {
        return window.localStorage;
    } catch (_e) {
        return (
            window.localStorageFallback ??
            (window.localStorageFallback = new MemoryStorage())
        );
    }
}
