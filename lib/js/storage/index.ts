import MemoryStorage from './src/MemoryStorage';
import CookieStorage from "./src/CookieStorage";
import {getSessionStorage} from "./src/SessionStorage";
import {IStorage} from "./src/types";

export {
    MemoryStorage,
    CookieStorage,
    getSessionStorage,
};

export type {
    IStorage,
};
