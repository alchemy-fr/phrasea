export const supportedLanguages = [
    {code: 'en', label: 'English', flag: 'gb'},
    {code: 'fr', label: 'Français', flag: 'fr'},
    {code: 'de', label: 'Deutsch', flag: 'de'},
] as const;

export type LanguageCode = (typeof supportedLanguages)[number]['code'];

export const LANG_COOKIE = 'expose_lang';
export const defaultLanguage: LanguageCode = 'en';

export function isSupportedLanguage(
    lang: string | undefined
): lang is LanguageCode {
    return !!lang && supportedLanguages.some(l => l.code === lang);
}
