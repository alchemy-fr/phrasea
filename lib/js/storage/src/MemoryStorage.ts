import {IStorage} from './types';

export default class MemoryStorage implements IStorage {
    private data: Record<string, string> = {};
    getItem(key: string): string | null {
        return this.data[key] ?? null;
    }

    removeItem(key: string): void {
        delete this.data[key];
    }

    setItem(key: string, value: string): void {
        this.data[key] = value;
    }

    clear(): void {
        this.data = {};
    }
}
