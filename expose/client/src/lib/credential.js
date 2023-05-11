import Cookies from 'universal-cookie';
const passwordCookieName = 'passwds';
const termsCookieName = 'terms';

const cookies = new Cookies();

export function getPasswords() {
    return cookies.get(passwordCookieName);
}

export function isTermsAccepted(key) {
    const terms = getTerms();

    return true === terms[key];
}

export function setAcceptedTerms(key) {
    const terms = getTerms();
    terms[key] = true;
    cookies.set(termsCookieName, terms, {path: '/'});
}

function getTerms() {
    const termsCookie = cookies.get(termsCookieName);
    return termsCookie || {};
}

export function setPassword(securityContainerId, password) {
    const passwords = decodePassword();

    passwords[securityContainerId] = password;

    cookies.set(passwordCookieName, btoa(JSON.stringify(passwords)), {
        path: '/',
        sameSite: 'none',
        secure: true,
    });
}

function decodePassword() {
    const cData = cookies.get(passwordCookieName);
    return cData ? (typeof cData === 'string' ? JSON.parse(atob(cData)) : cData) : {};
}
