import {
    getBestLocale,
    getBestLocaleOfTranslations,
    getBestTranslatedValue,
} from './localeHelper';

it('get best locale', () => {
    expect(getBestLocale([], ['fr'])).toEqual(undefined);
    expect(getBestLocale([], ['fr_FR'])).toEqual(undefined);

    const locales = ['fr', 'fr_FR', 'en_US', 'en_UK'];

    expect(getBestLocale(locales, ['fr_FR'])).toEqual('fr_FR');
    expect(getBestLocale(locales, ['fr_CA'])).toEqual('fr');
    expect(getBestLocale(locales, ['en'])).toEqual('en_US');
    expect(getBestLocale(locales, ['en_UK'])).toEqual('en_UK');
    expect(getBestLocale(locales, ['en_US'])).toEqual('en_US');
    expect(getBestLocale(locales, ['en_DD'])).toEqual('en_US');
    expect(getBestLocale(locales, [''])).toEqual(undefined);
});

it('get best locale of translations', () => {
    expect(getBestLocaleOfTranslations({}, ['fr'])).toEqual(undefined);
    expect(getBestLocaleOfTranslations({}, ['fr_FR'])).toEqual(undefined);

    const locales = {
        fr: true,
        fr_FR: true,
        en_US: true,
        en_UK: true,
    };

    expect(getBestLocaleOfTranslations(locales, ['fr_FR'])).toEqual('fr_FR');
    expect(getBestLocaleOfTranslations(locales, ['fr_CA'])).toEqual('fr');
    expect(getBestLocaleOfTranslations(locales, ['en'])).toEqual('en_US');
    expect(getBestLocaleOfTranslations(locales, ['en_UK'])).toEqual('en_UK');
    expect(getBestLocaleOfTranslations(locales, ['en_US'])).toEqual('en_US');
    expect(getBestLocaleOfTranslations(locales, ['en_DD'])).toEqual('en_US');
    expect(getBestLocaleOfTranslations(locales, [''])).toEqual(undefined);
});

it('get best translated value', () => {
    const fieldName = 'foo';

    expect(
        getBestTranslatedValue({}, fieldName, undefined, undefined, ['fr'])
    ).toEqual(undefined);
    expect(
        getBestTranslatedValue({}, fieldName, undefined, 'fr', ['fr'])
    ).toEqual(undefined);
    expect(getBestTranslatedValue({}, fieldName, 'bar', 'fr', ['fr'])).toEqual(
        'bar'
    );
    expect(getBestTranslatedValue({}, fieldName, 'bar', 'fr', [])).toEqual(
        'bar'
    );
    expect(
        getBestTranslatedValue({}, fieldName, 'bar', 'fr', ['fr', 'en'])
    ).toEqual('bar');
    expect(
        getBestTranslatedValue({}, fieldName, 'bar', 'fr', ['en', 'fr'])
    ).toEqual('bar');
    expect(
        getBestTranslatedValue({}, fieldName, 'bar', 'fr', ['en', 'fr'])
    ).toEqual('bar');

    const translations = {
        [fieldName]: {
            fr: 'fr',
            fr_FR: 'fr_FR',
            en_US: 'en_US',
            en_UK: 'en_UK',
        },
    };

    expect(
        getBestTranslatedValue(translations, fieldName, 'bar', 'fr', [
            'en',
            'fr',
        ])
    ).toEqual('en_US');
    expect(
        getBestTranslatedValue(translations, fieldName, 'bar', 'zz', [])
    ).toEqual('bar');
    expect(
        getBestTranslatedValue(translations, fieldName, 'bar', 'zz', [
            'en',
            'fr',
        ])
    ).toEqual('en_US');
    expect(
        getBestTranslatedValue(translations, fieldName, 'bar', 'zz', [
            'en',
            'zz',
        ])
    ).toEqual('en_US');
    expect(
        getBestTranslatedValue(translations, fieldName, 'bar', 'zz', [
            'zz',
            'en',
        ])
    ).toEqual('bar');
});
