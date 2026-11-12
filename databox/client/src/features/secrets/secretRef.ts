/**
 * References to secrets and variables in the configuration of integrations:
 * `${NAME}` (or the older `$NAME`), resolved by the API from the secrets, then
 * the variables.
 */

/** What the API accepts as the name of a secret */
export const secretNamePattern = /^[A-Za-z_][A-Za-z0-9_]*$/;
export const secretNameMaxLength = 100;

const bracedReference = /^\$\{([^}]+)\}$/;
const bareReference = /^\$([A-Za-z0-9_]+)$/;

/**
 * The name a value refers to when the whole value is a reference; undefined
 * for a literal value, or one mixing text and references (`https://${HOST}`).
 */
export function parseReference(value: unknown): string | undefined {
    if (typeof value !== 'string') {
        return undefined;
    }

    return (bracedReference.exec(value) ?? bareReference.exec(value))?.[1];
}

export function toReference(name: string): string {
    return `\${${name}}`;
}

export function isValidSecretName(name: string): boolean {
    return secretNamePattern.test(name) && name.length <= secretNameMaxLength;
}

/** A secret name made of a configuration key: `apiKey` → `API_KEY` */
export function suggestSecretName(key: string): string {
    const name = key
        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
        .replace(/[^A-Za-z0-9_]+/g, '_')
        .replace(/_{2,}/g, '_')
        .replace(/^_+|_+$/g, '')
        .toUpperCase()
        .slice(0, secretNameMaxLength);

    if (name === '') {
        return 'SECRET';
    }

    return /^[0-9]/.test(name)
        ? `_${name}`.slice(0, secretNameMaxLength)
        : name;
}

function escapeRegExp(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Whether a text refers to the name (`${NAME}`, or `$NAME` not followed by a name character) */
export function refersTo(text: string, name: string): boolean {
    const escaped = escapeRegExp(name);

    return new RegExp(`\\$\\{${escaped}\\}|\\$${escaped}(?![A-Za-z0-9_])`).test(
        text
    );
}

/** Whether a configuration (any depth) holds a reference to the name */
export function configRefersTo(config: unknown, name: string): boolean {
    if (typeof config === 'string') {
        return refersTo(config, name);
    }
    if (Array.isArray(config)) {
        return config.some(v => configRefersTo(v, name));
    }
    if (config && typeof config === 'object') {
        return Object.values(config).some(v => configRefersTo(v, name));
    }

    return false;
}
