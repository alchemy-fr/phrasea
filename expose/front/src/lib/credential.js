import Cookies from 'universal-cookie';
const passwordCookieName = 'passwds';
const tokenCookieName = 'access_token';

const cookies = new Cookies();

export function getPasswords() {
    return cookies.get(passwordCookieName);
}

export function setPassword(securityContainerId, password) {
    const passwords = decodePassword();

    passwords[securityContainerId] = password;
    cookies.set(passwordCookieName, btoa(JSON.stringify(passwords)), {path: '/'});
}

function decodePassword() {
    const cData = cookies.get(passwordCookieName);
    return cData ? (typeof cData === 'string' ? JSON.parse(atob(cData)) : cData) : {};
}

export function getAccessToken() {
    return cookies.get(tokenCookieName);
}

export function setAccessToken(accessToken) {
    cookies.set(tokenCookieName, accessToken, {path: '/'});
}
