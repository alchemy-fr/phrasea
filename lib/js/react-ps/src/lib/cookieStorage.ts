import Cookies from "js-cookie";
import {IStorage} from "./oauth-client";

export default class CookieStorage implements IStorage {
    getItem(key: string): string | null {
        return Cookies.get(key) || null;
    }

    removeItem(key: string): void {
        Cookies.remove(key, {
            path: '/',
        });

        if (this.getItem(key)) {
            Cookies.set(key, '', {
                sameSite: 'none',
                secure: true,
                expires: -1,
            });
        }
    }

    setItem(key: string, value: string): void {
        Cookies.set(key, value, {
            path: '/',
            sameSite: 'none',
            secure: true,
        });
    }
}
