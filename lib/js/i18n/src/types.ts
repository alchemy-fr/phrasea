
export type FieldTranslations = Record<string, string>;
export type Translations = Record<string, FieldTranslations>;

export type Translation = {
    locale: string;
    value: string;
}
