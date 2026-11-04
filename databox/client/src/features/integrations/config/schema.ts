import {parse, stringify} from 'yaml';
import type {ApiError} from '@/lib/api/http';

/**
 * Description of an integration configuration tree, as dumped by the API
 * (`ConfigSchemaDumper`): inspired by JSON Schema without following it.
 */
export type ConfigWidget =
    | 'attribute'
    | 'rendition'
    | 'collection'
    | 'email'
    | 'language';

type ConfigSchemaBase = {
    label?: string;
    info?: string;
    example?: unknown;
    required?: boolean;
    default?: unknown;
    widget?: ConfigWidget;
    widgetOptions?: Record<string, unknown>;
    /** Masked in the form */
    secret?: boolean;
};

export type ConfigStringSchema = ConfigSchemaBase & {type: 'string'};
export type ConfigNumberSchema = ConfigSchemaBase & {
    type: 'integer' | 'float';
    min?: number;
    max?: number;
};
export type ConfigBooleanSchema = ConfigSchemaBase & {type: 'boolean'};
export type ConfigEnumSchema = ConfigSchemaBase & {
    type: 'enum';
    values: (string | number | boolean | null)[];
};
/** Free value (variable node, array without definition): edited as YAML */
export type ConfigAnySchema = ConfigSchemaBase & {type: 'any'};
export type ConfigObjectSchema = ConfigSchemaBase & {
    type: 'object';
    properties?: Record<string, ConfigSchema>;
    /** Boolean property switching the whole object on and off */
    toggle?: string;
    /** Polymorphic object: the property telling the variants apart */
    discriminator?: string;
    variants?: Record<string, ConfigObjectSchema>;
};
export type ConfigListSchema = ConfigSchemaBase & {
    type: 'list';
    items: ConfigSchema;
    minItems?: number;
};

export type ConfigSchema =
    | ConfigStringSchema
    | ConfigNumberSchema
    | ConfigBooleanSchema
    | ConfigEnumSchema
    | ConfigAnySchema
    | ConfigObjectSchema
    | ConfigListSchema;

export type ConfigValues = Record<string, unknown>;

/** Field errors by path (`a.b.0.c`); `_global` holds the ones bound to no field */
export type ConfigErrors = Record<string, string>;

export const globalErrorKey = '_global';

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function clone<T>(value: T): T {
    return value === undefined ? value : JSON.parse(JSON.stringify(value));
}

/**
 * "maxImageSize" → "Max image size" (the API labels its nodes the same way)
 */
export function humanize(key: string): string {
    const words = key
        .replace(/([a-z\d])([A-Z])/g, '$1 $2')
        .replace(/[_\-.]+/g, ' ')
        .trim()
        .toLowerCase();

    return words.charAt(0).toUpperCase() + words.slice(1);
}

export function labelOf(schema: ConfigSchema, key: string): string {
    return schema.label ?? humanize(key);
}

/**
 * The variant of a polymorphic object matching a value, if any.
 */
export function variantOf(
    schema: ConfigObjectSchema,
    value: unknown
): ConfigObjectSchema | undefined {
    if (!schema.variants || !schema.discriminator) {
        return undefined;
    }
    const name = isPlainObject(value) ? value[schema.discriminator] : undefined;

    return typeof name === 'string' ? schema.variants[name] : undefined;
}

/**
 * Initial value of a node: its declared default, or what the form needs to
 * start from (a switched-off toggle, an empty list).
 */
function defaultOf(schema: ConfigSchema): unknown {
    if (schema.default !== undefined && schema.default !== null) {
        return clone(schema.default);
    }
    switch (schema.type) {
        case 'object': {
            const values = defaultsOf(schema);
            if (schema.toggle) {
                values[schema.toggle] ??= false;
            }

            return Object.keys(values).length > 0 ? values : undefined;
        }
        case 'list':
            return [];
        default:
            return undefined;
    }
}

/**
 * Initial values of a configuration form.
 */
export function defaultsOf(schema: ConfigObjectSchema): ConfigValues {
    const values: ConfigValues = {};
    for (const [key, property] of Object.entries(schema.properties ?? {})) {
        const value = defaultOf(property);
        if (value !== undefined) {
            values[key] = value;
        }
    }

    return values;
}

function isEmpty(value: unknown): boolean {
    return (
        value === undefined ||
        value === null ||
        value === '' ||
        (Array.isArray(value) && value.length === 0) ||
        (isPlainObject(value) && Object.keys(value).length === 0)
    );
}

function pruneValue(value: unknown, schema: ConfigSchema | undefined): unknown {
    if (schema?.type === 'object' || (!schema && isPlainObject(value))) {
        if (!isPlainObject(value)) {
            return value;
        }
        const objectSchema = schema as ConfigObjectSchema | undefined;
        const properties =
            (objectSchema && variantOf(objectSchema, value)?.properties) ??
            objectSchema?.properties ??
            {};

        return pruneObject(value, properties);
    }
    if (schema?.type === 'list' && Array.isArray(value)) {
        // An item left blank is no item
        return value
            .map(item => pruneValue(item, schema.items))
            .filter(item => !isEmpty(item));
    }

    return value;
}

function pruneObject(
    values: ConfigValues,
    properties: Record<string, ConfigSchema>
): ConfigValues {
    const out: ConfigValues = {};
    for (const [key, raw] of Object.entries(values)) {
        const property = properties[key];
        const value = pruneValue(raw, property);
        if (value === undefined) {
            continue;
        }
        if (!property?.required && isEmpty(value)) {
            continue;
        }
        out[key] = value;
    }

    return out;
}

/**
 * Drops what the user left empty (optional empty strings, lists and objects)
 * so the configuration sent is as minimal as a hand-written YAML.
 */
export function pruneConfig(
    values: ConfigValues,
    schema: ConfigObjectSchema
): ConfigValues {
    return pruneObject(values, schema.properties ?? {});
}

export function toYaml(values: ConfigValues): string {
    if (Object.keys(values).length === 0) {
        return '';
    }

    return stringify(values);
}

/**
 * Parses a YAML configuration. Throws on invalid YAML or on a document that
 * is not a mapping.
 */
export function fromYaml(text: string): ConfigValues {
    const parsed: unknown = parse(text);
    if (parsed === null || parsed === undefined) {
        return {};
    }
    if (!isPlainObject(parsed)) {
        throw new Error('The configuration must be a YAML mapping');
    }

    return parsed;
}

/**
 * The violations of a 422 by configuration path: `config.a.b` → `a.b`; the
 * ones bound to the whole configuration (or to something else) go to `_global`.
 */
export function errorsByPath(
    error: Pick<ApiError, 'violations'>
): ConfigErrors {
    const errors: ConfigErrors = {};
    for (const violation of error.violations) {
        const path = violation.propertyPath ?? '';
        const key = path.startsWith('config.')
            ? path.slice('config.'.length)
            : globalErrorKey;
        errors[key] = errors[key]
            ? `${errors[key]}\n${violation.message}`
            : violation.message;
    }

    return errors;
}
