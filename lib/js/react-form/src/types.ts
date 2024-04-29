import type {Translations} from '@alchemy/i18n'

export type Translation = {
    locale: string;
    value: string;
}

export type WithTranslations = {
    id: string;
    translations?: Translations | undefined;
};
