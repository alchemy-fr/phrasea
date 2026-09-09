export function uniqueId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return Math.random().toString(36).slice(2);
}

export function shortId(): string {
    return Math.random().toString(36).slice(2, 9);
}

export function deepEquals(a: unknown, b: unknown): boolean {
    if (a === b) {
        return true;
    }
    if (
        typeof a !== 'object' ||
        typeof b !== 'object' ||
        a === null ||
        b === null
    ) {
        return false;
    }
    if (Array.isArray(a) !== Array.isArray(b)) {
        return false;
    }
    const ka = Object.keys(a as object);
    const kb = Object.keys(b as object);
    if (ka.length !== kb.length) {
        return false;
    }

    return ka.every(k => deepEquals((a as any)[k], (b as any)[k]));
}

export function isDefined<T>(v: T | null | undefined): v is T {
    return v !== null && v !== undefined;
}

export function stopPropagation(e: {stopPropagation(): void}): void {
    e.stopPropagation();
}

export function clamp(v: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, v));
}

export async function copyToClipboard(text: string): Promise<void> {
    await navigator.clipboard.writeText(text);
}

export function downloadUrl(url: string, filename?: string): void {
    const a = document.createElement('a');
    a.href = url;
    if (filename) {
        a.download = filename;
    }
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    document.body.appendChild(a);
    a.click();
    a.remove();
}

/**
 * Runs async tasks with a maximum concurrency, preserving result order.
 */
export async function runWithConcurrency<T>(
    tasks: (() => Promise<T>)[],
    concurrency: number
): Promise<T[]> {
    const results: T[] = new Array(tasks.length);
    let cursor = 0;

    async function worker(): Promise<void> {
        while (cursor < tasks.length) {
            const index = cursor++;
            results[index] = await tasks[index]();
        }
    }

    await Promise.all(
        Array.from({length: Math.min(concurrency, tasks.length)}, worker)
    );

    return results;
}

export function debounce<A extends unknown[]>(
    fn: (...args: A) => void,
    wait: number
): ((...args: A) => void) & {cancel: () => void} {
    let timer: ReturnType<typeof setTimeout> | undefined;
    const debounced = (...args: A) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
    debounced.cancel = () => clearTimeout(timer);

    return debounced;
}
