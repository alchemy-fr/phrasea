export function extractField(slug: string): string {
    if (slug.startsWith('@')) {
        return slug;
    }

    const matches = slug.match(/^(.+)_([^_]+)_([sm])$/);

    if (matches) {
        return matches[1];
    }

    throw new Error(`Cannot parse field "${slug}"`);
}
