import {CookieStorage} from '@alchemy/storage';

const passwordCookieName = 'passwds';
const cookies = new CookieStorage({
    cookiesOptions: {
        sameSite: 'none',
    },
});

export function getPasswords(): string | undefined {
    return cookies.getItem(passwordCookieName) || undefined;
}

export function storePassword(
    securityContainerId: string,
    password: string
): void {
    const passwords = decodePassword();

    passwords[securityContainerId] = password;

    cookies.setItem(passwordCookieName, btoa(JSON.stringify(passwords)), {});
}

function decodePassword() {
    const cData = cookies.getItem(passwordCookieName);
    return cData ? JSON.parse(atob(cData)) : {};
}
