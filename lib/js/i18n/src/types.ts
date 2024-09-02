import type {TFunction} from 'i18next';

export type FieldTranslations = Record<string, string>;
export type Translations = Record<string, FieldTranslations>;

export type Translation = {
    locale: string;
    value: string;
}

export type {
    TFunction
};
