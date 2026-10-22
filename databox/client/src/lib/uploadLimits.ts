import type {TFunction} from 'i18next';
import {getMimeTypeFromFile} from '@/lib/utils/mime';
import {formatFileSize} from '@/lib/utils/format';

/*
 * Upload limits checked before sending a file. They copy the API ones, which
 * still apply: keep both in sync.
 */

const KiB = 1024;
const MiB = 1024 * KiB;

export const SVG_TYPE = 'image/svg+xml';

/** `UploadAttributeEntityImageProcessor::MAX_SIZE` */
export const ENTITY_IMAGE_MAX_SIZE = 20 * MiB;
/** `LogoManager::MAX_SIZE` */
export const LOGO_MAX_SIZE = 5 * MiB;
/** `InlineSvg::MAX_SIZE`: an SVG image, whatever the raster limit */
export const SVG_MAX_SIZE = 100 * KiB;
/** `UploadWorkspaceTermsPdfProcessor::MAX_SIZE` */
export const TERMS_PDF_MAX_SIZE = 20 * MiB;

/** `ImageSetManager::RASTER_TYPES` + `InlineSvg::TYPE` */
export const IMAGE_SET_ACCEPT = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    SVG_TYPE,
].join(',');
export const PDF_ACCEPT = 'application/pdf';

export type UploadLimits = {
    /** File types, as in the `accept` attribute of a file input */
    accept: string;
    maxSize: number;
    /** Limit of an SVG file, instead of `maxSize` */
    svgMaxSize?: number;
};

export const ENTITY_IMAGE_LIMITS: UploadLimits = {
    accept: IMAGE_SET_ACCEPT,
    maxSize: ENTITY_IMAGE_MAX_SIZE,
    svgMaxSize: SVG_MAX_SIZE,
};
export const LOGO_LIMITS: UploadLimits = {
    accept: IMAGE_SET_ACCEPT,
    maxSize: LOGO_MAX_SIZE,
    svgMaxSize: SVG_MAX_SIZE,
};
export const TERMS_PDF_LIMITS: UploadLimits = {
    accept: PDF_ACCEPT,
    maxSize: TERMS_PDF_MAX_SIZE,
};

export type UploadRejection = {
    reason: 'type' | 'size';
    /** The limit applying to the file */
    maxSize: number;
};

function acceptList(accept: string): string[] {
    return accept
        .split(',')
        .map(s => s.trim().toLowerCase())
        .filter(Boolean);
}

function isAccepted(file: File, accept: string): boolean {
    const list = acceptList(accept);
    if (list.length === 0) {
        return true;
    }
    const type = getMimeTypeFromFile(file)?.toLowerCase();
    const name = file.name.toLowerCase();

    return list.some(a => {
        if (a.startsWith('.')) {
            return name.endsWith(a);
        }
        if (!type) {
            return false;
        }
        if (a.endsWith('/*')) {
            return type.startsWith(a.slice(0, -1));
        }

        return type === a;
    });
}

/**
 * Whether a file can be sent: its type must be in `accept` (the attribute of
 * the file input is only a hint to the browser's picker) and its size within
 * the limit. `null` when it can.
 */
export function checkUploadFile(
    file: File,
    {accept, maxSize, svgMaxSize}: UploadLimits
): UploadRejection | null {
    const isSvg = getMimeTypeFromFile(file) === SVG_TYPE;
    const limit = isSvg && svgMaxSize !== undefined ? svgMaxSize : maxSize;
    if (!isAccepted(file, accept)) {
        return {reason: 'type', maxSize: limit};
    }
    if (file.size > limit) {
        return {reason: 'size', maxSize: limit};
    }

    return null;
}

const typeLabels: Record<string, string> = {
    'image/jpeg': 'JPEG',
    'image/png': 'PNG',
    'image/webp': 'WebP',
    'image/gif': 'GIF',
    [SVG_TYPE]: 'SVG',
    'application/pdf': 'PDF',
};

/** "JPEG, PNG or SVG" */
export function formatAcceptedTypes(accept: string, lang?: string): string {
    const labels = [
        ...new Set(
            acceptList(accept).map(
                a =>
                    typeLabels[a] ??
                    (a.startsWith('.') ? a.slice(1) : a).toUpperCase()
            )
        ),
    ];
    try {
        return new Intl.ListFormat(lang, {
            style: 'long',
            type: 'disjunction',
        }).format(labels);
    } catch {
        return labels.join(', ');
    }
}

/** "JPEG, PNG or SVG · 20 MiB max (SVG: 100 KiB)" */
export function describeUploadLimits(
    t: TFunction,
    limits: UploadLimits,
    lang?: string
): string {
    const types = formatAcceptedTypes(limits.accept, lang);
    const size = formatFileSize(limits.maxSize, true, lang);
    const hasSvg = acceptList(limits.accept).includes(SVG_TYPE);
    if (
        hasSvg &&
        limits.svgMaxSize !== undefined &&
        limits.svgMaxSize !== limits.maxSize
    ) {
        return t(
            'file_picker.help_svg',
            '{{types}} · {{size}} max (SVG: {{svgSize}})',
            {
                types,
                size,
                svgSize: formatFileSize(limits.svgMaxSize, true, lang),
            }
        );
    }

    return t('file_picker.help', '{{types}} · {{size}} max', {types, size});
}

/** Why a file is refused, with the limit */
export function describeUploadRejection(
    t: TFunction,
    file: File,
    rejection: UploadRejection,
    limits: UploadLimits,
    lang?: string
): string {
    if (rejection.reason === 'type') {
        return t(
            'file_picker.error.type',
            '"{{name}}" is not an accepted file type ({{types}}).',
            {
                name: file.name,
                types: formatAcceptedTypes(limits.accept, lang),
            }
        );
    }

    return t(
        'file_picker.error.too_large',
        '"{{name}}" is too large ({{size}}): the limit is {{max}}.',
        {
            name: file.name,
            size: formatFileSize(file.size, true, lang),
            max: formatFileSize(rejection.maxSize, true, lang),
        }
    );
}
