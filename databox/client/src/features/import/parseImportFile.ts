import type {AssetImportFormat} from '@/types/api';

/** Beyond this size, only the beginning of the file is previewed */
export const IMPORT_PREVIEW_MAX_SIZE = 5 * 1024 * 1024;
/** Number of errors reported by the preview (the others are only counted) */
export const IMPORT_PREVIEW_MAX_ERRORS = 20;

export type ImportEntryErrorCode =
    'invalid_line' | 'not_object' | 'missing_source' | 'collection_and_path';

export type ImportFatalErrorCode = 'invalid_json' | 'invalid_structure';

export type ImportPreviewError = {
    /** File line (NDJSON) or position from 1 in the array (JSON) */
    line: number;
    code: ImportEntryErrorCode;
    /** Untranslated detail, e.g. the parser message */
    message: string;
};

export type ImportPreview = {
    format: AssetImportFormat;
    /**
     * Entries read. Only those of the previewed part when `truncated`, and
     * undefined when the entries could not be read at all.
     */
    count?: number;
    /** The first `IMPORT_PREVIEW_MAX_ERRORS` errors */
    errors: ImportPreviewError[];
    errorCount: number;
    /** The file cannot be imported at all */
    fatal?: {code: ImportFatalErrorCode; message: string};
    /** Only the beginning of the file was read */
    truncated: boolean;
};

const sourceFields = [
    'sourceUrl',
    'previewUrl',
    'thumbnailUrl',
    'renditions',
    'name',
] as const;

export function detectImportFormat(fileName: string): AssetImportFormat | null {
    const dot = fileName.lastIndexOf('.');
    if (dot < 0) {
        return null;
    }
    switch (fileName.slice(dot + 1).toLowerCase()) {
        case 'json':
            return 'json';
        case 'ndjson':
        case 'jsonl':
            return 'ndjson';
        default:
            return null;
    }
}

function isFilled(value: unknown): boolean {
    if (value === undefined || value === null || value === '') {
        return false;
    }
    if (typeof value === 'object' && !Array.isArray(value)) {
        return Object.keys(value).length > 0;
    }

    return true;
}

function checkEntry(
    entry: unknown
): Omit<ImportPreviewError, 'line'> | undefined {
    if (typeof entry !== 'object' || entry === null || Array.isArray(entry)) {
        return {code: 'not_object', message: 'The entry is not an object'};
    }
    const e = entry as Record<string, unknown>;
    if (!sourceFields.some(f => isFilled(e[f]))) {
        return {
            code: 'missing_source',
            message: `The entry needs one of: ${sourceFields.join(', ')}`,
        };
    }
    if (isFilled(e.collection) && isFilled(e.path)) {
        return {
            code: 'collection_and_path',
            message: '"collection" and "path" cannot be both set',
        };
    }

    return undefined;
}

function errorMessage(e: unknown): string {
    return e instanceof Error ? e.message : String(e);
}

class Collector {
    count = 0;
    errors: ImportPreviewError[] = [];
    errorCount = 0;

    add(line: number, entry: unknown) {
        this.count++;
        const error = checkEntry(entry);
        if (error) {
            this.push({line, ...error});
        }
    }

    push(error: ImportPreviewError) {
        this.errorCount++;
        if (this.errors.length < IMPORT_PREVIEW_MAX_ERRORS) {
            this.errors.push(error);
        }
    }
}

function parseNdjson(text: string, truncated: boolean, c: Collector) {
    const lines = text.split(/\r?\n/);
    // The last line of a truncated read is most likely cut
    if (truncated && lines.length > 1) {
        lines.pop();
    }
    lines.forEach((raw, i) => {
        const line = raw.trim();
        if (!line) {
            return;
        }
        let entry: unknown;
        try {
            entry = JSON.parse(line);
        } catch (e) {
            c.count++;
            c.push({
                line: i + 1,
                code: 'invalid_line',
                message: errorMessage(e),
            });

            return;
        }
        c.add(i + 1, entry);
    });
}

/**
 * Pre-validates the content of an instructions file, as far as it can be
 * without the workspace (attributes, renditions and keys are checked by the
 * import itself).
 *
 * A truncated JSON document cannot be parsed: only its size is known then.
 */
export function parseImportContent(
    content: string,
    format: AssetImportFormat,
    {truncated = false}: {truncated?: boolean} = {}
): ImportPreview {
    const text = content.replace(/^﻿/, '');
    const c = new Collector();
    const result = (extra: Partial<ImportPreview> = {}): ImportPreview => ({
        format,
        count: c.count,
        errors: c.errors,
        errorCount: c.errorCount,
        truncated,
        ...extra,
    });

    if (format === 'ndjson') {
        parseNdjson(text, truncated, c);

        return result();
    }

    if (truncated) {
        return result({count: undefined});
    }

    let doc: unknown;
    try {
        doc = JSON.parse(text);
    } catch (e) {
        return result({
            count: undefined,
            fatal: {code: 'invalid_json', message: errorMessage(e)},
        });
    }
    const entries = Array.isArray(doc)
        ? doc
        : typeof doc === 'object' &&
            doc !== null &&
            Array.isArray((doc as {assets?: unknown}).assets)
          ? (doc as {assets: unknown[]}).assets
          : undefined;
    if (!entries) {
        return result({
            count: undefined,
            fatal: {
                code: 'invalid_structure',
                message:
                    'The document must be an array of entries or an object with an "assets" array',
            },
        });
    }
    entries.forEach((entry, i) => c.add(i + 1, entry));

    return result();
}

/** Reads the file (its beginning only when it is large) and previews it */
export async function previewImportFile(
    file: Blob,
    format: AssetImportFormat
): Promise<ImportPreview> {
    const truncated = file.size > IMPORT_PREVIEW_MAX_SIZE;
    if (truncated && format === 'json') {
        return parseImportContent('', format, {truncated});
    }
    const text = await readText(
        truncated ? file.slice(0, IMPORT_PREVIEW_MAX_SIZE) : file
    );

    return parseImportContent(text, format, {truncated});
}

function readText(blob: Blob): Promise<string> {
    if (typeof blob.text === 'function') {
        return blob.text();
    }

    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result as string);
        reader.onerror = () => reject(reader.error);
        reader.readAsText(blob);
    });
}
