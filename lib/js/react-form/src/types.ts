
export type FieldTranslations = Record<string, string>;
export type Translations = Record<string, FieldTranslations>;

export type WithTranslations = {
    id: string;
    translations?: Translations | undefined;
};
