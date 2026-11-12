import {
    DEFAULT_LOCALE,
    type BuilderField,
    type BuiltinKey,
    type DataboxField,
    type FieldType,
    type FieldWidget,
    type FormDefinition,
    type FormFieldErrors,
    type FormValue,
    type FormValues,
    type InputField,
    type LayoutField,
    type Localized,
} from './types';

export function emptyDefinition(workspaceId?: string | null): FormDefinition {
    return {version: 1, workspaceId: workspaceId ?? null, fields: []};
}

export function newFieldId(): string {
    return `f_${Math.random().toString(36).slice(2, 10)}`;
}

export function isInputField(field: BuilderField): field is InputField {
    return field.source !== 'layout';
}

export function inputFields(definition: FormDefinition): InputField[] {
    return definition.fields.filter(isInputField);
}

/**
 * The text in the given language: its own, the one of its base language
 * (`fr` for `fr-CA`), the one for every language, English, then any.
 */
export function localized(
    map: Localized | undefined,
    lang: string,
    fallback = ''
): string {
    if (!map) {
        return fallback;
    }
    const base = lang.split(/[-_]/)[0];

    return (
        map[lang] ||
        map[base] ||
        map[DEFAULT_LOCALE] ||
        map.en ||
        Object.values(map).find(Boolean) ||
        fallback
    );
}

export function fieldLabel(field: BuilderField, lang: string): string {
    return localized(field.label, lang, isInputField(field) ? field.key : '');
}

type BuiltinDef = {
    key: BuiltinKey;
    type: FieldType;
    multiple?: boolean;
    label: Localized;
};

/** Mirrors `App\Form\Builder\Builtin` of the API */
export const BUILTINS: BuiltinDef[] = [
    {key: 'databox_name', type: 'text', label: {en: 'Name', fr: 'Nom'}},
    {
        key: 'databox_tags',
        type: 'tag',
        multiple: true,
        label: {en: 'Tags', fr: 'Tags'},
    },
    {
        key: 'databox_is_story',
        type: 'boolean',
        label: {
            en: 'Group the files in a story',
            fr: 'Regrouper les fichiers dans une story',
        },
    },
    {
        key: 'databox_story_name',
        type: 'text',
        label: {en: 'Story name', fr: 'Nom de la story'},
    },
    {
        key: '__notify',
        type: 'boolean',
        label: {
            en: 'Notify me when done',
            fr: 'Me prévenir à la fin du traitement',
        },
    },
];

export function getBuiltin(key: string): BuiltinDef | undefined {
    return BUILTINS.find(b => b.key === key);
}

/** The types a custom field can have: entities and tags come from Databox */
export const CUSTOM_TYPES: FieldType[] = [
    'text',
    'textarea',
    'number',
    'date',
    'date_time',
    'boolean',
    'color',
    'choice',
];

export function fieldFromAttribute(attribute: DataboxField): InputField {
    const label: Localized = {...attribute.label};
    if (!label[DEFAULT_LOCALE]) {
        label[DEFAULT_LOCALE] = attribute.name;
    }

    return {
        id: newFieldId(),
        source: 'attribute',
        key: attribute.slug,
        type: attribute.type,
        multiple: attribute.multiple || undefined,
        required: attribute.required || undefined,
        label,
        constraints:
            attribute.minLength || attribute.maxLength
                ? {
                      minLength: attribute.minLength ?? null,
                      maxLength: attribute.maxLength ?? null,
                  }
                : undefined,
        widget: attribute.type === 'entity' ? 'autocomplete' : undefined,
        attribute: {
            id: attribute.id,
            slug: attribute.slug,
            databoxType: attribute.databoxType,
            translatable: attribute.translatable,
            entityListId: attribute.entityList?.id ?? null,
            entityListName: attribute.entityList?.name ?? null,
        },
    };
}

export function fieldFromBuiltin(key: BuiltinKey): InputField {
    const builtin = getBuiltin(key)!;

    return {
        id: newFieldId(),
        source: 'builtin',
        key,
        type: builtin.type,
        multiple: builtin.multiple,
        label: {...builtin.label},
        widget: builtin.type === 'tag' ? 'autocomplete' : undefined,
    };
}

export function newCustomField(
    type: FieldType,
    existingKeys: string[]
): InputField {
    let key: string = type;
    for (let i = 2; existingKeys.includes(key); i++) {
        key = `${type}_${i}`;
    }

    return {
        id: newFieldId(),
        source: 'custom',
        key,
        type,
        label: {},
        choices:
            type === 'choice'
                ? [
                      {
                          value: 'option_1',
                          label: {[DEFAULT_LOCALE]: 'Option 1'},
                      },
                      {
                          value: 'option_2',
                          label: {[DEFAULT_LOCALE]: 'Option 2'},
                      },
                  ]
                : undefined,
    };
}

export function newLayout(kind: LayoutField['kind']): LayoutField {
    return {id: newFieldId(), source: 'layout', kind, label: {}};
}

/** The field as the palette adds it, or undefined when it cannot tell */
function pristineField(
    field: BuilderField,
    databoxFields: DataboxField[] | undefined
): BuilderField | undefined {
    switch (field.source) {
        case 'layout':
            return newLayout(field.kind);
        case 'builtin':
            return getBuiltin(field.key)
                ? fieldFromBuiltin(field.key as BuiltinKey)
                : undefined;
        case 'custom': {
            const pristine = newCustomField(field.type, []);
            // The palette numbers the key when the type is already used
            const numbered = new RegExp(`^${field.type}(_\\d+)?$`);

            return numbered.test(field.key)
                ? {...pristine, key: field.key}
                : pristine;
        }
        case 'attribute': {
            const attribute = databoxFields?.find(
                f => f.id === field.attribute?.id
            );

            return attribute ? fieldFromAttribute(attribute) : undefined;
        }
    }
}

/**
 * Comparable form of a field setting: an empty text, list or object, and an
 * unchecked option, are the same as none (e.g. a translation typed then
 * erased).
 */
function canonical(value: unknown): unknown {
    if (Array.isArray(value)) {
        const items = value.map(canonical);

        return items.length > 0 ? items : undefined;
    }
    if (value && typeof value === 'object') {
        const entries = Object.entries(value)
            .map(([k, v]) => [k, canonical(v)] as const)
            .filter(([, v]) => v !== undefined)
            .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));

        return entries.length > 0 ? Object.fromEntries(entries) : undefined;
    }

    return value === '' || value === null || value === false
        ? undefined
        : value;
}

/**
 * Whether the field holds settings of its own, lost when it is removed: it
 * differs from the field the palette adds. A field whose origin is unknown
 * (attribute not loaded or gone) counts as customized.
 */
export function isFieldCustomized(
    field: BuilderField,
    databoxFields?: DataboxField[]
): boolean {
    const pristine = pristineField(field, databoxFields);
    if (!pristine) {
        return true;
    }

    return (
        JSON.stringify(canonical({...field, id: undefined})) !==
        JSON.stringify(canonical({...pristine, id: undefined}))
    );
}

export function hasOptions(field: InputField): boolean {
    return (
        field.type === 'choice' ||
        field.type === 'entity' ||
        field.type === 'tag'
    );
}

/** The widgets a field can be rendered with, its default one first */
export function widgetsFor(field: InputField): FieldWidget[] {
    if (field.type === 'boolean') {
        return ['checkbox', 'switch'];
    }
    if (!hasOptions(field)) {
        return [];
    }
    const list: FieldWidget[] =
        field.type === 'choice' ? ['select', 'autocomplete'] : ['autocomplete'];
    list.push(field.multiple ? 'checkboxes' : 'radios');

    return list;
}

export function widgetOf(field: InputField): FieldWidget | undefined {
    const widgets = widgetsFor(field);

    return field.widget && widgets.includes(field.widget)
        ? field.widget
        : widgets[0];
}

export function isEmptyValue(value: unknown): boolean {
    return (
        value === undefined ||
        value === null ||
        value === '' ||
        (Array.isArray(value) && value.length === 0)
    );
}

export function initialValues(
    definition: FormDefinition,
    initial?: FormValues
): FormValues {
    const values: FormValues = {};
    for (const field of inputFields(definition)) {
        if (field.hidden) {
            continue;
        }
        if (initial && field.key in initial) {
            values[field.key] = initial[field.key];
        } else if (!isEmptyValue(field.defaultValue)) {
            values[field.key] = field.defaultValue;
        } else {
            values[field.key] = emptyValue(field);
        }
    }

    return values;
}

export function emptyValue(field: InputField): FormValue {
    if (field.multiple) {
        return [];
    }

    return field.type === 'boolean' ? false : '';
}

export type ValidationMessages = {
    required: string;
    number: string;
    minLength: (n: number) => string;
    maxLength: (n: number) => string;
    min: (n: number) => string;
    max: (n: number) => string;
};

function toNumber(value: unknown): number | undefined {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : undefined;
    }
    if (typeof value === 'string' && value.trim() !== '') {
        const n = Number(value);

        return Number.isFinite(n) ? n : undefined;
    }

    return undefined;
}

/**
 * The client side of the API checks (`FieldConstraintFactory`): required
 * values, numbers, lengths and bounds. The API checks the rest.
 */
export function validateValues(
    definition: FormDefinition,
    values: FormValues,
    messages: ValidationMessages
): FormFieldErrors {
    const errors: FormFieldErrors = {};
    const add = (key: string, message: string) => {
        (errors[key] ??= []).push(message);
    };

    for (const field of inputFields(definition)) {
        if (field.hidden) {
            continue;
        }
        const value = values[field.key];
        const blank =
            isEmptyValue(value) ||
            (typeof value === 'string' && value.trim() === '') ||
            (field.type === 'boolean' && value !== true);
        if (blank) {
            if (field.required) {
                add(field.key, messages.required);
            }
            continue;
        }

        const items = Array.isArray(value) ? value : [value];
        const c = field.constraints ?? {};
        for (const item of items) {
            if (field.type === 'number') {
                const n = toNumber(item);
                if (n === undefined) {
                    add(field.key, messages.number);
                    continue;
                }
                if (c.min != null && n < c.min) {
                    add(field.key, messages.min(c.min));
                }
                if (c.max != null && n > c.max) {
                    add(field.key, messages.max(c.max));
                }
            }
            if (typeof item === 'string') {
                const length = item.trim().length;
                if (c.minLength != null && length < c.minLength) {
                    add(field.key, messages.minLength(c.minLength));
                }
                if (c.maxLength != null && length > c.maxLength) {
                    add(field.key, messages.maxLength(c.maxLength));
                }
            }
        }
    }

    return errors;
}

/** The values to send: trimmed, numbers as numbers, empty ones left out */
export function normalizeValues(
    definition: FormDefinition,
    values: FormValues
): FormValues {
    const result: FormValues = {};
    for (const field of inputFields(definition)) {
        if (field.hidden) {
            continue;
        }
        const normalizeItem = (v: unknown): unknown => {
            const trimmed = typeof v === 'string' ? v.trim() : v;
            if (field.type === 'number') {
                return toNumber(trimmed) ?? trimmed;
            }

            return trimmed;
        };
        const value = values[field.key];
        const normalized = Array.isArray(value)
            ? value.map(normalizeItem).filter(v => !isEmptyValue(v))
            : normalizeItem(value);
        if (field.type === 'boolean') {
            if (normalized === true) {
                result[field.key] = true;
            }
            continue;
        }
        if (!isEmptyValue(normalized)) {
            result[field.key] = normalized;
        }
    }

    return result;
}

/** The keys used twice, and the invalid ones: the form cannot be saved */
export function definitionProblems(
    definition: FormDefinition
): Record<string, string> {
    const problems: Record<string, string> = {};
    const seen = new Set<string>();
    for (const field of inputFields(definition)) {
        if (!/^[A-Za-z0-9_.-]+(:[A-Za-z_-]{2,5})?$/.test(field.key)) {
            problems[field.id] = 'invalid_key';
        } else if (seen.has(field.key)) {
            problems[field.id] = 'duplicate_key';
        } else if (field.type === 'choice' && !(field.choices ?? []).length) {
            problems[field.id] = 'no_choice';
        } else if (
            field.hidden &&
            field.required &&
            isEmptyValue(field.defaultValue)
        ) {
            problems[field.id] = 'hidden_required';
        }
        seen.add(field.key);
    }

    return problems;
}
