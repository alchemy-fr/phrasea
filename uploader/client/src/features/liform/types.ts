/**
 * Form schemas of the uploader: JSON Schema in the LiForm flavour
 * (https://limenius.github.io/liform-react/#/), as stored by the form
 * schemas of the API.
 */
export type LiFormField = {
    type?: string;
    widget?: string;
    format?: string;
    title?: string;
    description?: string;
    /** `false` hides the label */
    showLabel?: boolean;
    /** Initial value of the field (and placeholder of text fields) */
    defaultValue?: unknown;
    default?: unknown;
    /** Label of the empty option of choice lists */
    placeholder?: string | false;
    propertyOrder?: number;
    required?: string[];
    properties?: Record<string, LiFormField>;
    items?: LiFormField;
    uniqueItems?: boolean;
    enum?: unknown[];
    enum_titles?: string[];
    oneOf?: LiFormField[];
    allOf?: LiFormField[];
    $ref?: string;
    minimum?: number;
    maximum?: number;
    minLength?: number;
    maxLength?: number;
    [key: string]: unknown;
};

export type LiFormSchema = LiFormField;

export type LiFormValues = Record<string, unknown>;

/** Field path (`a.b.0`, `''` for the whole form) => messages */
export type LiFormErrors = Record<string, string[]>;
