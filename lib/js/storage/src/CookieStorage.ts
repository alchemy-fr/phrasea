import Cookies from "js-cookie";
import {CookieStorageOptions, IStorage, StorageSetOptions} from "./types";
import {CookieMemoryDecorator} from "./CookieMemoryDecorator";

export default class CookieStorage implements IStorage {
    private readonly cookies: typeof Cookies;
    private readonly cookiesOptions: Cookies.CookieAttributes;

    constructor(options: CookieStorageOptions = {}) {
        this.cookiesOptions = {
            secure: true,
            sameSite: 'strict',
            ...(options.cookiesOptions ?? {})
        };
        this.cookies = options.fallback ? CookieMemoryDecorator : Cookies;
    }


    getItem(key: string): string | null {
        return this.cookies.get(key) || null;
    }

    removeItem(key: string): void {
        this.cookies.remove(key, {
            path: '/',
        });

        if (this.getItem(key)) {
            this.cookies.set(key, '', {
                expires: -1,
                ...this.cookiesOptions,
            });
        }
    }

    setItem(key: string, value: string, options: StorageSetOptions = {}): void {
        this.cookies.set(key, value, {
            path: '/',
            ...this.cookiesOptions,
            ...options,
        });
    }

    clear(): void {
        const cookies = document.cookie.split(';');

        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i];
            const eqPos = cookie.indexOf("=");
            const name = eqPos > -1 ? cookie.substring(0, eqPos) : cookie;
            document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT";
        }
    }
}
