import {IStorage, StorageSetOptions} from './types';

type CacheRecord = {
    value: string;
    expire?: number;
    timeout?: ReturnType<typeof setTimeout>;
};

export default class MemoryStorage implements IStorage {
    private data: Record<string, CacheRecord> = {};
    getItem(key: string): string | null {
        const record = this.data[key];

        if (record?.expire && record.expire <= Date.now()) {
            this.removeItem(key);
            return null;
        }

        return this.data[key]?.value ?? null;
    }

    removeItem(key: string): void {
        const record = this.data[key];
        if (record?.timeout) {
            clearTimeout(record.timeout);
        }

        delete this.data[key];
    }

    setItem(key: string, value: string, options?: StorageSetOptions): void {
        const expires = options?.expires;
        let time: number | undefined;
        if (typeof expires === 'number') {
            if (expires <= 0) {
                throw new Error('Expiration time must be a positive number');
            }

            time = expires;
        } else if (expires instanceof Date) {
            if (expires.getTime() <= Date.now()) {
                throw new Error('Expiration date must be in the future');
            }

            time = expires.getTime() - Date.now();
        }

        const oldRecord = this.data[key];
        if (oldRecord?.timeout) {
            clearTimeout(oldRecord.timeout);
        }

        const record: CacheRecord = {
            value: value,
            expire: time ? time + Date.now() : undefined,
        };

        if (record.expire != undefined) {
            record.timeout = setTimeout(() => {
                    this.removeItem(key);
                }, time);
        }

        this.data[key] = record;
    }

    clear(): void {
        for (const key in this.data) {
            if (this.data[key].timeout) {
                clearTimeout(this.data[key].timeout);
            }
        }
        this.data = {};
    }
}
