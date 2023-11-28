import MemoryStorage from "./MemoryStorage";
import {IStorage} from "./types";

declare global {
    interface Window {
        sessionStorageFallback?: MemoryStorage;
    }
}

export function getSessionStorage(): IStorage {
    try {
        return window.sessionStorage;
    } catch (e) {
        return window.sessionStorageFallback ?? (window.sessionStorageFallback = new MemoryStorage());
    }
}
