import {LiFormSchema} from '../../../../types.ts';

type SchemaVariant = object | object[];

function isObject(thing: SchemaVariant): boolean {
    return typeof thing === 'object' && thing !== null && !Array.isArray(thing);
}

function compileSchema(schema: SchemaVariant, root?: object): object {
    if (!root) {
        root = schema;
    }
    let newSchema: typeof schema;

    if (isObject(schema)) {
        newSchema = {};
        for (const i in schema) {
            if (Object.prototype.hasOwnProperty.call(schema, i)) {
                if (i === '$ref') {
                    newSchema = compileSchema(
                        // @ts-expect-error any
                        resolveRef(schema[i], root),
                        root
                    );
                } else {
                    // @ts-expect-error any
                    newSchema[i] = compileSchema(schema[i], root);
                }
            }
        }
        return newSchema;
    }

    if (Array.isArray(schema)) {
        newSchema = [];
        for (let i = 0; i < schema.length; i += 1) {
            // @ts-expect-error any
            newSchema[i] = compileSchema(schema[i], root);
        }
        return newSchema;
    }

    return schema;
}

function resolveRef(uri: string, schema: LiFormSchema) {
    uri = uri.replace('#/', '');
    const tokens = uri.split('/') as (keyof typeof schema)[];

    return tokens.reduce(
        (obj, token) => obj[token as keyof typeof obj] as any,
        schema
    );
}

export default compileSchema;
