/**
 * Forms of the Uploader form builder: what a form schema of the "builder"
 * format holds (validated by `App\Form\Builder\FormDefinition` on the API).
 */

/** Text by locale; `_` holds the text of every other language */
export type Localized = Record<string, string>;

export const DEFAULT_LOCALE = '_';

export type FieldType =
    | 'text'
    | 'textarea'
    | 'number'
    | 'date'
    | 'date_time'
    | 'boolean'
    | 'color'
    | 'choice'
    | 'entity'
    | 'tag';

export type FieldWidget =
    'autocomplete' | 'select' | 'checkboxes' | 'radios' | 'switch' | 'checkbox';

export type BuiltinKey =
    | 'databox_name'
    | 'databox_tags'
    | 'databox_is_story'
    | 'databox_story_name'
    | '__notify';

/** The Databox attribute a field fills, as it was when added to the form */
export type AttributeSnapshot = {
    id: string;
    slug: string;
    databoxType: string;
    translatable?: boolean;
    entityListId?: string | null;
    entityListName?: string | null;
};

export type Choice = {
    value: string;
    label: Localized;
};

export type FieldConstraints = {
    minLength?: number | null;
    maxLength?: number | null;
    min?: number | null;
    max?: number | null;
};

export type InputField = {
    id: string;
    source: 'attribute' | 'builtin' | 'custom';
    /** What the field is sent as: the attribute slug for Databox */
    key: string;
    type: FieldType;
    multiple?: boolean;
    required?: boolean;
    /** Not shown: the API always sends its default value */
    hidden?: boolean;
    defaultValue?: FormValue;
    widget?: FieldWidget | null;
    label: Localized;
    help?: Localized;
    placeholder?: Localized;
    constraints?: FieldConstraints;
    choices?: Choice[];
    attribute?: AttributeSnapshot;
};

export type LayoutField = {
    id: string;
    source: 'layout';
    kind: 'section' | 'text';
    label: Localized;
};

export type BuilderField = InputField | LayoutField;

export type FormDefinition = {
    version: 1;
    workspaceId?: string | null;
    fields: BuilderField[];
};

export type FormValue = string | number | boolean | string[] | null;

export type FormValues = Record<string, unknown>;

/** Field key (`''` for the whole form) => messages */
export type FormFieldErrors = Record<string, string[]>;

/** A value of an entity or tag field, as the API lists them */
export type FieldOption = {
    value: string;
    label: string;
    color?: string | null;
    emoji?: string | null;
};

export type FieldOptionsPage = {
    member: FieldOption[];
    hasMore: boolean;
};

export type FieldOptionsQuery = {
    query?: string;
    ids?: string[];
    page?: number;
};

/** Lists the values an entity or tag field offers */
export type FieldOptionsLoader = (
    field: InputField,
    query: FieldOptionsQuery
) => Promise<FieldOptionsPage>;

/** `GET /databox/workspaces` */
export type DataboxWorkspace = {
    id: string;
    name: string;
    enabledLocales: string[];
};

/** `GET /databox/workspaces/{id}/fields`: an attribute an uploader can fill */
export type DataboxField = {
    id: string;
    slug: string;
    name: string;
    displayName: string;
    /** Translations of the name */
    label: Localized;
    databoxType: string;
    type: FieldType;
    multiple: boolean;
    translatable: boolean;
    required: boolean;
    minLength?: number | null;
    maxLength?: number | null;
    entityList: {id: string; name: string} | null;
};
