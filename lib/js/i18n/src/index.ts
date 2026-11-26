/**
 * The languages of the Phrasea clients. Free of i18next and React: the root
 * layout reads them on the server to pick the language of the page.
 */
export const languages = [
    {code: 'en', label: 'English', flag: 'gb'},
    {code: 'fr', label: 'Français', flag: 'fr'},
    {code: 'de', label: 'Deutsch', flag: 'de'},
    {code: 'es', label: 'Español', flag: 'es'},
] as const;

export type Language = (typeof languages)[number];

export type LanguageCode = Language['code'];

export const defaultLanguage: LanguageCode = 'en';

export type LanguageSet = {
    supportedLanguages: Language[];
    /** The cookie remembering the language chosen by the user */
    cookie: string;
    isSupportedLanguage: (lang: string | undefined) => lang is LanguageCode;
    /** The language of a page from the value of the cookie */
    resolveLanguage: (cookieValue: string | undefined) => LanguageCode;
};

/** The languages a client is translated into */
export function defineLanguages(
    codes: LanguageCode[],
    cookie: string
): LanguageSet {
    const supportedLanguages = languages.filter(l => codes.includes(l.code));
    const isSupportedLanguage = (
        lang: string | undefined
    ): lang is LanguageCode =>
        !!lang && supportedLanguages.some(l => l.code === lang);

    return {
        supportedLanguages,
        cookie,
        isSupportedLanguage,
        resolveLanguage: cookieValue =>
            isSupportedLanguage(cookieValue) ? cookieValue : defaultLanguage,
    };
}
