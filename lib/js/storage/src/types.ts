
export type IStorage = {
    getItem(key: string): string | null;

    removeItem(key: string): void;

    setItem(key: string, value: string, options?: StorageSetOptions): void;

    clear(): void;
}

export type StorageSetOptions = {
    expires?: number;
}
