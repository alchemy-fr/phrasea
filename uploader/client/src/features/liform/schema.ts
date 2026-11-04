import type {LiFormErrors, LiFormField, LiFormValues} from './types';

function isPlainObject(v: unknown): v is Record<string, unknown> {
    return typeof v === 'object' && v !== null && !Array.isArray(v);
}

/** Deep merge returning a new object: neither argument is modified */
export function mergeDeep<T extends Record<string, unknown>>(
    target: T,
    ...sources: Record<string, unknown>[]
): T {
    const result: Record<string, unknown> = {...target};
    for (const source of sources) {
        for (const [key, value] of Object.entries(source)) {
            const current = result[key];
            result[key] =
                isPlainObject(current) && isPlainObject(value)
                    ? mergeDeep(current, value)
                    : value;
        }
    }

    return result as T;
}

function resolveRef(uri: string, root: unknown): unknown {
    if (!uri.startsWith('#')) {
        throw new Error(`Unsupported $ref "${uri}": only local references`);
    }
    const tokens = uri
        .replace(/^#\/?/, '')
        .split('/')
        .filter(Boolean)
        .map(t => t.replace(/~1/g, '/').replace(/~0/g, '~'));

    return tokens.reduce<unknown>((node, token) => {
        if (!isPlainObject(node) && !Array.isArray(node)) {
            throw new Error(`Unresolvable $ref "${uri}"`);
        }

        return (node as Record<string, unknown>)[token];
    }, root);
}

/**
 * Resolves the `$ref` (against the root of the schema) and merges the
 * `allOf` of every field. Returns a new schema: the given one, which may be
 * rendered again (live preview of the editor), is left untouched.
 */
export function compileSchema(
    schema: LiFormField,
    root: unknown = schema,
    depth = 0
): LiFormField {
    if (depth > 32) {
        throw new Error('Schema too deep (circular $ref?)');
    }
    const compile = (node: unknown): unknown => {
        if (Array.isArray(node)) {
            return node.map(compile);
        }
        if (isPlainObject(node)) {
            return compileSchema(node as LiFormField, root, depth + 1);
        }

        return node;
    };

    if (typeof schema.$ref === 'string') {
        const {$ref, ...rest} = schema;
        const target = resolveRef($ref, root);

        // The target may itself be a reference
        return compileSchema(
            mergeDeep(
                isPlainObject(target) ? target : {},
                rest as Record<string, unknown>
            ),
            root,
            depth + 1
        );
    }

    const node: Record<string, unknown> = schema;
    const result: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(node)) {
        if (key !== 'allOf') {
            result[key] = compile(value);
        }
    }
    if (Array.isArray(node.allOf)) {
        return mergeDeep(
            result,
            ...(node.allOf.map(compile) as Record<string, unknown>[])
        ) as LiFormField;
    }

    return result as LiFormField;
}

/** The widgets the renderer knows, keyed as in the schema */
export const WIDGETS = [
    'object',
    'string',
    'text',
    'textarea',
    'email',
    'password',
    'search',
    'url',
    'color',
    'time',
    'number',
    'integer',
    'money',
    'percent',
    'boolean',
    'checkbox',
    'date',
    'datetime',
    'date-time',
    'compatible-date',
    'compatible-datetime',
    'choice',
    'choice-expanded',
    'choice-multiple-expanded',
    'array',
    'oneOf',
] as const;

export type Widget = (typeof WIDGETS)[number];

/**
 * The widget of a field, in this order: the explicit `widget`, `choice` for
 * an enum, `oneOf`, the `format` when it names a widget, then the `type`.
 */
export function guessWidget(field: LiFormField): string {
    if (field.widget) {
        return field.widget;
    }
    if (Array.isArray(field.enum)) {
        return 'choice';
    }
    if (Array.isArray(field.oneOf)) {
        return 'oneOf';
    }
    if (field.format && (WIDGETS as readonly string[]).includes(field.format)) {
        return field.format;
    }
    if (field.type === 'array' && field.items?.enum && field.uniqueItems) {
        return 'choice-multiple';
    }

    return field.type ?? 'object';
}

export function isNumericWidget(widget: string): boolean {
    return ['number', 'integer', 'money', 'percent'].includes(widget);
}

/** The properties of an object field, in `propertyOrder` order */
export function sortedProperties(
    field: LiFormField
): [name: string, field: LiFormField][] {
    return Object.entries(field.properties ?? {})
        .map((entry, index) => ({entry, index}))
        .sort(
            (a, b) =>
                (a.entry[1].propertyOrder ?? 0) -
                    (b.entry[1].propertyOrder ?? 0) || a.index - b.index
        )
        .map(({entry}) => entry);
}

export function fieldLabel(name: string, field: LiFormField): string {
    if (field.showLabel === false) {
        return '';
    }

    return field.title ?? name;
}

function defaultOf(field: LiFormField): unknown {
    return field.defaultValue !== undefined
        ? field.defaultValue
        : field.default;
}

/**
 * Key of the value of a `oneOf` field holding the chosen option: the fields
 * of an option may all be empty, or share their names with another one.
 * Never sent: only the properties of the option are.
 */
export const ONE_OF_OPTION = '__option';

/**
 * The option chosen for a `oneOf` field: the one recorded in its value, else
 * the one whose properties it holds, else the first one.
 */
export function selectedOneOf(
    field: LiFormField,
    value: LiFormValues | undefined
): number {
    const options = field.oneOf ?? [];
    const recorded = value?.[ONE_OF_OPTION];
    if (
        typeof recorded === 'number' &&
        recorded >= 0 &&
        recorded < options.length
    ) {
        return recorded;
    }
    const keys = Object.keys(value ?? {});
    const index = options.findIndex(o =>
        keys.some(k => k in (o.properties ?? {}))
    );

    return index >= 0 ? index : 0;
}

/** Value of a `oneOf` field once `index` is chosen: its defaults */
export function oneOfValue(field: LiFormField, index: number): LiFormValues {
    const option = field.oneOf?.[index];

    return {
        ...(option ? (initialValues(option, 'object') as LiFormValues) : {}),
        [ONE_OF_OPTION]: index,
    };
}

/** Initial values of the form: the default value of every field */
export function initialValues(
    field: LiFormField,
    widget = guessWidget(field)
): unknown {
    const fallback = defaultOf(field);
    if (widget === 'object') {
        const values: LiFormValues = isPlainObject(fallback)
            ? {...fallback}
            : {};
        for (const [name, child] of sortedProperties(field)) {
            if (values[name] === undefined) {
                const v = initialValues(child);
                if (v !== undefined) {
                    values[name] = v;
                }
            }
        }

        return values;
    }
    if (widget === 'oneOf') {
        return oneOfValue(field, 0);
    }
    if (
        widget === 'array' ||
        widget === 'choice-multiple' ||
        widget === 'choice-multiple-expanded'
    ) {
        return Array.isArray(fallback) ? [...fallback] : [];
    }
    if (widget === 'boolean' || widget === 'checkbox') {
        return typeof fallback === 'boolean' ? fallback : false;
    }
    if (widget === 'choice' && fallback === false) {
        // Not a value: the list has no empty option, its first one is selected
        return field.enum?.[0];
    }

    return fallback;
}

/**
 * The values sent to the API: numeric fields as numbers, empty values left
 * out (the API sees them as not filled), the fields of the chosen `oneOf`
 * option flattened into their parent like LiForm does.
 */
export function normalizeValues(
    field: LiFormField,
    value: unknown,
    widget = guessWidget(field)
): unknown {
    if (widget === 'object') {
        if (!isPlainObject(value)) {
            return undefined;
        }
        const result: LiFormValues = {};
        for (const [name, child] of sortedProperties(field)) {
            const childWidget = guessWidget(child);
            const v = normalizeValues(child, value[name], childWidget);
            if (childWidget === 'oneOf' && isPlainObject(v)) {
                Object.assign(result, v);
            } else if (v !== undefined) {
                result[name] = v;
            }
        }

        return result;
    }
    if (widget === 'oneOf') {
        const option = field.oneOf?.[selectedOneOf(field, value as never)];

        return option ? normalizeValues(option, value, 'object') : undefined;
    }
    if (isNumericWidget(widget)) {
        if (value === '' || value === null || value === undefined) {
            return undefined;
        }
        const n = typeof value === 'number' ? value : Number(value);

        return Number.isNaN(n) ? value : n;
    }
    if (widget === 'array') {
        if (!Array.isArray(value)) {
            return [];
        }
        const items = field.items ?? {type: 'string'};

        return value
            .map(v => normalizeValues(items, v))
            .filter(v => v !== undefined);
    }
    if (value === '' || value === null) {
        return undefined;
    }

    return value;
}

function isEmpty(value: unknown): boolean {
    return (
        value === undefined ||
        value === null ||
        value === '' ||
        (Array.isArray(value) && value.length === 0)
    );
}

export type ValidationMessages = {
    required: string;
    number: string;
    integer: string;
};

const defaultMessages: ValidationMessages = {
    required: 'This value should not be blank.',
    number: 'This value should be a number.',
    integer: 'This value should be an integer.',
};

/**
 * Checks what the browser does not: required fields (a checkbox included)
 * and numbers. The API validates the form again on submit.
 */
export function validateValues(
    field: LiFormField,
    value: unknown,
    messages: ValidationMessages = defaultMessages,
    path = '',
    errors: LiFormErrors = {}
): LiFormErrors {
    const widget = guessWidget(field);
    const add = (p: string, message: string) => {
        (errors[p] ??= []).push(message);
    };
    if (widget === 'object' || widget === 'oneOf') {
        const target =
            widget === 'oneOf'
                ? field.oneOf?.[selectedOneOf(field, value as never)]
                : field;
        if (!target) {
            return errors;
        }
        const values = isPlainObject(value) ? value : {};
        const required = new Set(target.required ?? []);
        for (const [name, child] of sortedProperties(target)) {
            const childPath = path ? `${path}.${name}` : name;
            const childWidget = guessWidget(child);
            const v = values[name];
            if (
                required.has(name) &&
                (isEmpty(v) ||
                    ((childWidget === 'boolean' ||
                        childWidget === 'checkbox') &&
                        v !== true))
            ) {
                add(childPath, messages.required);
                continue;
            }
            validateValues(child, v, messages, childPath, errors);
        }

        return errors;
    }
    if (isNumericWidget(widget) && !isEmpty(value)) {
        const n = Number(value);
        if (Number.isNaN(n)) {
            add(path, messages.number);
        } else if (widget === 'integer' && !Number.isInteger(n)) {
            add(path, messages.integer);
        }
    }
    if (widget === 'array' && Array.isArray(value) && field.items) {
        value.forEach((v, i) =>
            validateValues(field.items!, v, messages, `${path}.${i}`, errors)
        );
    }

    return errors;
}
