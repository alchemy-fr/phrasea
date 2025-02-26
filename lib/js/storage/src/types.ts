import Cookies from 'js-cookie';

export type IStorage = {
    getItem(key: string): string | null;

    removeItem(key: string): void;

    setItem(key: string, value: string, options?: StorageSetOptions): void;

    clear(): void;
};

export type StorageSetOptions = {
    expires?: number;
};

export type CookieStorageOptions = {
    cookiesOptions?: Cookies.CookieAttributes | undefined;
    fallback?: boolean;
};
