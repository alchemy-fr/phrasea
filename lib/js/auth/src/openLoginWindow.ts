import {PendingAuthWindow} from "./types";

export function openLoginWindow(loginUrl: string): void {
    (window as PendingAuthWindow).pendingAuth = true;
    window.open(loginUrl, 'auth',`width=500,height=600`);
}
