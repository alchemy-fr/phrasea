export function escapeSlashes(path: string): string {
    return path.replace(/\//g, '\\/');
}

export function stripSlashes(path: string): string {
    return path.replace(/\\\//g, '/');
}

export function splitPath(path: string): string[] {
    return path
        .replace(/^\//, '')
        .split(/(?<!\\)\//)
        .map(stripSlashes);
}
