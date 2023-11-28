const supportedLanguages = [
    'ar',
    'en',
    'es',
    'fr',
    'de',
    'ja',
    'ko',
    'mul',
    'pt',
    'ru',
    'zh',
]

export function getBrowserLanguage() {
    const language = navigator.languages
        ? navigator.languages[0]
        : navigator.language || navigator.userLanguage
    const parts = language.split('-')
    let languageCode = language
    if (parts.length > 1) {
        languageCode = parts[0]
    }
    if (supportedLanguages.indexOf(languageCode) > -1) {
        return languageCode
    }
    return null
}
