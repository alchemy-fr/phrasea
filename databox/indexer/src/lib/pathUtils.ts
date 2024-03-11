export function escapeSlashes(path: string): string {
    return path.replace(/\//g, '\\/');
}

export function stripSlashes(path: string): string {
    return path.replace(/\\\//g, '/');
}

export function splitPath(path: string): string[] {
    return path
        .split(/(?<!\\)\//)
        .filter(x => x) // remove empty segments (/a//b/c/ => [a,b,c])
        .map(stripSlashes);
}
