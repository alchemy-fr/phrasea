import {
    compileSchema,
    guessWidget,
    sortedProperties,
} from '@/features/liform/schema';
import type {LiFormField, LiFormSchema} from '@/features/liform/types';
import {
    emptyDefinition,
    fieldFromAttribute,
    getBuiltin,
    newFieldId,
} from '../definition';
import {
    DEFAULT_LOCALE,
    type BuiltinKey,
    type Choice,
    type DataboxField,
    type FieldType,
    type FieldWidget,
    type FormDefinition,
    type FormValue,
    type InputField,
    type Localized,
} from '../types';

export type ConversionWarningCode =
    /** An enum offered some values of an entity list: the field offers them all */
    | 'restricted_entities'
    /** The LiForm type does not fit the Databox attribute: kept as a custom field */
    | 'type_mismatch'
    /** No attribute of the workspace has this key: kept as a custom field */
    | 'unknown_attribute'
    /** oneOf, nested objects, arrays of objects: the field is left out */
    | 'unsupported';

export type ConversionWarning = {
    key: string;
    code: ConversionWarningCode;
};

export type LiFormSource = {
    /** Locale of the schema, null for every language */
    locale: string | null;
    schema: LiFormSchema;
};

export type ConversionResult = {
    definition: FormDefinition;
    warnings: ConversionWarning[];
};

const DATE_TIME_WIDGETS = [
    'datetime',
    'date-time',
    'compatible-datetime',
    'compatible-date-time',
];
const DATE_WIDGETS = ['date', 'compatible-date'];

type LiFormKind = {
    type: FieldType;
    multiple: boolean;
    widget?: FieldWidget;
    enumValues?: unknown[];
    enumTitles?: string[];
};

/** What a LiForm property is, in builder terms; null when it has no equivalent */
function kindOf(field: LiFormField): LiFormKind | null {
    const widget = guessWidget(field);

    if (field.type === 'array') {
        const items = field.items ?? {};
        if (Array.isArray(items.enum)) {
            return {
                type: 'choice',
                multiple: true,
                widget:
                    widget === 'choice-multiple-expanded'
                        ? 'checkboxes'
                        : 'autocomplete',
                enumValues: items.enum,
                enumTitles: items.enum_titles,
            };
        }
        const item = items.type === undefined ? null : kindOf(items);

        return item && !item.multiple ? {...item, multiple: true} : null;
    }

    if (Array.isArray(field.enum)) {
        return {
            type: 'choice',
            multiple: false,
            widget: widget === 'choice-expanded' ? 'radios' : 'select',
            enumValues: field.enum,
            enumTitles: field.enum_titles,
        };
    }
    if (field.oneOf || field.type === 'object') {
        return null;
    }
    if (field.type === 'boolean' || widget === 'checkbox') {
        return {type: 'boolean', multiple: false};
    }
    if (
        field.type === 'number' ||
        field.type === 'integer' ||
        ['number', 'integer', 'money', 'percent'].includes(widget)
    ) {
        return {type: 'number', multiple: false};
    }
    if (DATE_TIME_WIDGETS.includes(widget) || field.format === 'date-time') {
        return {type: 'date_time', multiple: false};
    }
    if (DATE_WIDGETS.includes(widget) || field.format === 'date') {
        return {type: 'date', multiple: false};
    }
    if (widget === 'color') {
        return {type: 'color', multiple: false};
    }
    if (widget === 'textarea') {
        return {type: 'textarea', multiple: false};
    }

    return {type: 'text', multiple: false};
}

/** Whether a field of this LiForm kind can fill an attribute of this type */
function fitsAttribute(kind: LiFormKind, attribute: DataboxField): boolean {
    if (kind.multiple !== attribute.multiple) {
        return false;
    }
    if (attribute.type === 'entity') {
        return kind.type === 'choice' || kind.type === 'text';
    }
    if (attribute.type === 'text' || attribute.type === 'textarea') {
        return kind.type === 'text' || kind.type === 'textarea';
    }

    return kind.type === attribute.type;
}

function localizedText(text: string | undefined, locale: string): Localized {
    return text ? {[locale]: text} : {};
}

function defaultValueOf(field: LiFormField): FormValue | undefined {
    const value = field.defaultValue ?? field.default;
    if (
        value === null ||
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
    ) {
        return value;
    }
    if (Array.isArray(value)) {
        return value.map(String);
    }

    return undefined;
}

function choicesOf(kind: LiFormKind, locale: string): Choice[] {
    return (kind.enumValues ?? []).map((value, i) => ({
        value: String(value),
        label: localizedText(kind.enumTitles?.[i] ?? String(value), locale),
    }));
}

/**
 * Turns LiForm schemas into a form of the builder. The keys stay the same,
 * so does the data sent to Databox: a key matching an attribute of the
 * workspace becomes that attribute, the built-in keys become built-in fields.
 * The schemas of the other locales (`translations`) only bring their titles.
 */
export function fromLiForm(
    source: LiFormSource,
    databoxFields: DataboxField[],
    workspaceId: string | null,
    translations: LiFormSource[] = []
): ConversionResult {
    const locale = source.locale || DEFAULT_LOCALE;
    const schema = compileSchema(source.schema);
    const required = new Set(schema.required ?? []);
    const definition = emptyDefinition(workspaceId);
    const warnings: ConversionWarning[] = [];
    const attributes = new Map(databoxFields.map(f => [f.slug, f]));

    for (const [key, property] of sortedProperties(schema)) {
        const kind = kindOf(property);
        if (!kind) {
            warnings.push({key, code: 'unsupported'});
            continue;
        }

        const base = {
            label: localizedText(property.title, locale),
            help: property.description
                ? localizedText(property.description, locale)
                : undefined,
            placeholder:
                typeof property.placeholder === 'string'
                    ? localizedText(property.placeholder, locale)
                    : undefined,
            required: required.has(key) || undefined,
            defaultValue: defaultValueOf(property),
            constraints:
                property.minLength !== undefined ||
                property.maxLength !== undefined ||
                property.minimum !== undefined ||
                property.maximum !== undefined
                    ? {
                          minLength: property.minLength ?? null,
                          maxLength: property.maxLength ?? null,
                          min: property.minimum ?? null,
                          max: property.maximum ?? null,
                      }
                    : undefined,
        };
        const builtin = getBuiltin(key);
        const attribute = attributes.get(key.replace(/:[A-Za-z_-]{2,5}$/, ''));
        let field: InputField;

        if (builtin) {
            field = {
                id: newFieldId(),
                source: 'builtin',
                key: builtin.key as BuiltinKey,
                type: builtin.type,
                multiple: builtin.multiple,
                widget:
                    builtin.type === 'tag'
                        ? kind.widget === 'checkboxes'
                            ? 'checkboxes'
                            : 'autocomplete'
                        : undefined,
                ...base,
            };
            if (Object.keys(base.label).length === 0) {
                field.label = {...builtin.label};
            }
        } else if (attribute && workspaceId && fitsAttribute(kind, attribute)) {
            const fromAttribute = fieldFromAttribute(attribute);
            field = {
                ...fromAttribute,
                key,
                ...base,
                label: {...fromAttribute.label, ...base.label},
                required: base.required ?? fromAttribute.required,
                constraints: base.constraints ?? fromAttribute.constraints,
                widget:
                    attribute.type === 'entity'
                        ? kind.widget === 'checkboxes' ||
                          kind.widget === 'radios'
                            ? kind.widget
                            : 'autocomplete'
                        : undefined,
            };
            if (attribute.type === 'entity' && kind.enumValues?.length) {
                warnings.push({key, code: 'restricted_entities'});
            }
        } else {
            if (attribute) {
                warnings.push({key, code: 'type_mismatch'});
            } else if (workspaceId && !key.startsWith('__')) {
                warnings.push({key, code: 'unknown_attribute'});
            }
            field = {
                id: newFieldId(),
                source: 'custom',
                key,
                type: kind.type,
                multiple: kind.multiple || undefined,
                widget: kind.widget,
                choices:
                    kind.type === 'choice'
                        ? choicesOf(kind, locale)
                        : undefined,
                ...base,
            };
        }

        definition.fields.push(field);
    }

    for (const translation of translations) {
        mergeTitles(definition, translation);
    }

    return {definition, warnings};
}

/** Adds the titles of a schema of another locale to the matching fields */
function mergeTitles(definition: FormDefinition, source: LiFormSource): void {
    const locale = source.locale || DEFAULT_LOCALE;
    const schema = compileSchema(source.schema);
    for (const [key, property] of sortedProperties(schema)) {
        const field = definition.fields.find(
            (f): f is InputField => f.source !== 'layout' && f.key === key
        );
        if (!field) {
            continue;
        }
        if (property.title && !field.label[locale]) {
            field.label = {...field.label, [locale]: property.title};
        }
        if (property.description && !field.help?.[locale]) {
            field.help = {...field.help, [locale]: property.description};
        }
        if (field.choices) {
            const kind = kindOf(property);
            kind?.enumValues?.forEach((value, i) => {
                const title = kind.enumTitles?.[i];
                const choice = field.choices!.find(
                    c => c.value === String(value)
                );
                if (choice && title && !choice.label[locale]) {
                    choice.label = {...choice.label, [locale]: title};
                }
            });
        }
    }
}
