import {CookieStorage} from '@alchemy/storage';

const termsCookiePrefix = 'terms_';
const trueValue = '1';
const cookies = new CookieStorage();

export function isTermsAccepted(key: string): boolean {
    return trueValue === cookies.getItem(termsCookiePrefix + key);
}

export function setAcceptedTerms(key: string): void {
    cookies.setItem(termsCookiePrefix + key, trueValue);
}
