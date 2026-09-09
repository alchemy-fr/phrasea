import type {ReactNode} from 'react';
import type {TFunction} from 'i18next';
import {AssetStatus, AttributeType, EntityName, Privacy} from '@/types/api';
import {
    formatDateTime,
    formatDuration,
    formatFileSize,
    formatNumber,
    type DateStyle,
} from '@/lib/utils/format';
import {Highlight} from '@/components/ui/highlight';
import {pickTranslation} from '@/lib/utils/locale';
import {
    AssetStatusChip,
    CollectionChip,
    ColorSwatch,
    EntityChip,
    PrivacyChip,
    PrivacyIcon,
    TagChip,
    UserChip,
    WorkspaceChip,
} from '@/components/chips';

export type FormatContext = {
    t: TFunction;
    lang: string;
    locale?: string;
    highlight?: unknown;
};

export type AvailableFormat = {name: string; label: string};

export type AttributeTypeDef = {
    /** Value renders as a rich node (chip, swatch...) */
    rich?: boolean;
    /** Entity type referenced by the value (ids resolved through the API) */
    entity?: EntityName;
    formats: (t: TFunction) => AvailableFormat[];
    format: (
        value: unknown,
        format: string | undefined,
        ctx: FormatContext
    ) => ReactNode;
    formatString: (
        value: unknown,
        format: string | undefined,
        ctx: FormatContext
    ) => string;
    /** Input value → API value */
    normalize?: (value: unknown) => unknown;
    /** API value → input value */
    denormalize?: (value: unknown) => unknown;
};

function str(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }

    return String(value);
}

const textType: AttributeTypeDef = {
    formats: () => [],
    format: (value, _f, ctx) => (
        <Highlight
            text={
                typeof ctx.highlight === 'string' ? ctx.highlight : str(value)
            }
        />
    ),
    formatString: value => str(value),
};

const dateFormats = (t: TFunction): AvailableFormat[] => [
    {name: 'medium', label: t('attribute.format.medium', 'Medium')},
    {name: 'short', label: t('attribute.format.short', 'Short')},
    {name: 'long', label: t('attribute.format.long', 'Long')},
    {name: 'relative', label: t('attribute.format.relative', 'Relative')},
    {name: 'iso', label: t('attribute.format.iso', 'ISO')},
];

function dateType(withTime: boolean): AttributeTypeDef {
    const fmt = (
        value: unknown,
        format: string | undefined,
        ctx: FormatContext
    ) =>
        formatDateTime(
            value,
            (format as DateStyle) ?? 'medium',
            ctx.lang,
            withTime
        );

    return {
        formats: dateFormats,
        format: (v, f, ctx) => fmt(v, f, ctx),
        formatString: fmt,
        denormalize: value => {
            if (typeof value !== 'string' || !value) {
                return value;
            }
            const local = value.replace(/(Z|[+-]\d{2}:\d{2})$/, '');

            return withTime ? local.slice(0, 19) : local.slice(0, 10);
        },
        normalize: value => {
            if (typeof value !== 'string' || !value) {
                return value;
            }
            const d = new Date(value);

            return Number.isNaN(d.getTime()) ? value : d.toISOString();
        },
    };
}

function toNumber(value: unknown): number | undefined {
    if (typeof value === 'number') {
        return value;
    }
    if (typeof value === 'string' && value.trim() !== '') {
        const n = parseFloat(value);

        return Number.isNaN(n) ? undefined : n;
    }

    return undefined;
}

const numberType: AttributeTypeDef = {
    formats: t => [
        {name: 'original', label: t('attribute.format.original', 'Original')},
        {name: 'integer', label: t('attribute.format.integer', 'Integer')},
        {
            name: 'formatted',
            label: t('attribute.format.formatted', 'Formatted'),
        },
        {
            name: 'fixed',
            label: t('attribute.format.fixed', 'Fixed (2 decimals)'),
        },
        {
            name: 'scientific',
            label: t('attribute.format.scientific', 'Scientific'),
        },
    ],
    format: (v, f, ctx) => numberType.formatString(v, f, ctx),
    formatString: (value, format, ctx) => {
        const n = toNumber(value);
        if (n === undefined) {
            return '';
        }
        switch (format) {
            case 'integer':
                return String(Math.round(n));
            case 'formatted':
                return formatNumber(n, ctx.lang);
            case 'fixed':
                return n.toFixed(2);
            case 'scientific':
                return n.toExponential(2);
            default:
                return String(n);
        }
    },
    normalize: value => (value === '' ? undefined : (toNumber(value) ?? value)),
};

const durationType: AttributeTypeDef = {
    formats: t => [
        {name: 'compact', label: t('attribute.format.compact', 'Compact')},
        {
            name: 'formatted',
            label: t('attribute.format.formatted', 'Formatted'),
        },
        {
            name: 'humanized',
            label: t('attribute.format.humanized', 'Humanized'),
        },
        {name: 'original', label: t('attribute.format.original', 'Original')},
    ],
    format: (v, f, ctx) => durationType.formatString(v, f, ctx),
    formatString: (value, format) => {
        const n = toNumber(value);
        if (n === undefined) {
            return '';
        }
        if (format === 'original') {
            return String(n);
        }

        return formatDuration(
            n,
            (format as 'compact' | 'formatted' | 'humanized') ?? 'compact'
        );
    },
};

const fileSizeType: AttributeTypeDef = {
    formats: t => [
        {
            name: 'humanized',
            label: t('attribute.format.humanized', 'Humanized'),
        },
        {
            name: 'humanized10',
            label: t(
                'attribute.format.humanized_decimal',
                'Humanized (base 10)'
            ),
        },
        {name: 'original', label: t('attribute.format.original', 'Original')},
    ],
    format: (v, f, ctx) => fileSizeType.formatString(v, f, ctx),
    formatString: (value, format, ctx) => {
        const n = toNumber(value);
        if (n === undefined) {
            return '';
        }
        if (format === 'original') {
            return String(n);
        }

        return formatFileSize(n, format !== 'humanized10', ctx.lang);
    },
};

const booleanType: AttributeTypeDef = {
    rich: true,
    formats: t => [
        {name: 'label', label: t('attribute.format.label', 'Label')},
        {name: 'binary', label: t('attribute.format.binary', 'Binary')},
        {name: 'thumbs', label: t('attribute.format.thumbs', 'Thumbs')},
        {
            name: 'true_false',
            label: t('attribute.format.true_false', 'True / False'),
        },
    ],
    format: (v, f, ctx) => booleanType.formatString(v, f, ctx),
    formatString: (value, format, {t}) => {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const b =
            value === true || value === 'true' || value === 1 || value === '1';
        switch (format) {
            case 'binary':
                return b ? '1' : '0';
            case 'thumbs':
                return b ? '👍' : '👎';
            case 'true_false':
                return b ? 'true' : 'false';
            default:
                return b ? t('common.yes', 'Yes') : t('common.no', 'No');
        }
    },
    normalize: value =>
        value === '' || value === undefined
            ? undefined
            : value === 'true'
              ? true
              : value === 'false'
                ? false
                : value,
};

const colorType: AttributeTypeDef = {
    rich: true,
    formats: t => [
        {name: 'box', label: t('attribute.format.swatch', 'Swatch')},
        {name: 'hex', label: t('attribute.format.hex', 'Hexadecimal')},
    ],
    format: (value, format) => {
        const s = str(value);
        if (!s) {
            return null;
        }

        return format === 'hex' ? s : <ColorSwatch color={s} />;
    },
    formatString: value => str(value),
};

const geoPointType: AttributeTypeDef = {
    rich: true,
    formats: t => [
        {name: 'coords', label: t('attribute.format.coords', 'Coordinates')},
        {name: 'json', label: t('attribute.format.json', 'JSON')},
    ],
    format: (v, f, ctx) => geoPointType.formatString(v, f, ctx),
    formatString: (value, format) => {
        if (!value) {
            return '';
        }
        if (typeof value === 'string') {
            return value;
        }
        const {lat, lng} = value as {lat: number; lng: number};
        if (format === 'json') {
            return JSON.stringify(value);
        }

        return `${lat}, ${lng}`;
    },
    denormalize: value => {
        if (!value || typeof value === 'string') {
            return value;
        }
        const {lat, lng} = value as {lat: number; lng: number};

        return `${lat}, ${lng}`;
    },
};

type EntityValue = {
    id: string;
    value: string | null;
    emoji?: string;
    color?: string;
    status?: number;
    translations?: Record<string, string>;
};

export function entityLabel(entity: EntityValue): string {
    const label = pickTranslation(entity.translations, entity.value ?? '');

    return entity.emoji ? `${entity.emoji} ${label}` : label;
}

const entityType: AttributeTypeDef = {
    rich: true,
    entity: EntityName.AttributeEntity,
    formats: t => [
        {name: 'full', label: t('attribute.format.full', 'Full')},
        {name: 'emoji', label: t('attribute.format.emoji', 'Emoji')},
        {name: 'color', label: t('attribute.format.color', 'Color')},
    ],
    format: (value, format) => {
        if (!value) {
            return null;
        }
        if (typeof value === 'string') {
            return value;
        }
        const e = value as EntityValue;
        if (format === 'emoji') {
            return e.emoji ?? null;
        }
        if (format === 'color') {
            return e.color ? <ColorSwatch color={e.color} /> : null;
        }

        return <EntityChip entity={e} />;
    },
    formatString: value => {
        if (!value) {
            return '';
        }

        return typeof value === 'string'
            ? value
            : entityLabel(value as EntityValue);
    },
    normalize: value =>
        value && typeof value === 'object' ? (value as EntityValue).id : value,
};

const tagType: AttributeTypeDef = {
    rich: true,
    entity: EntityName.Tag,
    formats: () => [],
    format: value => {
        if (!value) {
            return null;
        }
        if (typeof value === 'string') {
            return value;
        }

        return <TagChip tag={value as any} />;
    },
    formatString: value => {
        if (!value) {
            return '';
        }

        return typeof value === 'string'
            ? value
            : ((value as any).displayName ?? (value as any).name ?? '');
    },
    normalize: value =>
        value && typeof value === 'object' ? (value as any).id : value,
};

const userType: AttributeTypeDef = {
    rich: true,
    entity: EntityName.User,
    formats: () => [],
    format: value =>
        value && typeof value === 'object' ? (
            <UserChip user={value as any} />
        ) : (
            str(value)
        ),
    formatString: value =>
        value && typeof value === 'object'
            ? ((value as any).username ?? '')
            : str(value),
    normalize: value =>
        value && typeof value === 'object' ? (value as any).id : value,
};

const workspaceType: AttributeTypeDef = {
    rich: true,
    entity: EntityName.Workspace,
    formats: () => [],
    format: value =>
        value && typeof value === 'object' ? (
            <WorkspaceChip workspace={value as any} />
        ) : (
            str(value)
        ),
    formatString: value =>
        value && typeof value === 'object'
            ? ((value as any).displayName ?? (value as any).name ?? '')
            : str(value),
    normalize: value =>
        value && typeof value === 'object' ? (value as any).id : value,
};

const collectionType: AttributeTypeDef = {
    rich: true,
    entity: EntityName.Collection,
    formats: () => [],
    format: value =>
        value && typeof value === 'object' ? (
            <CollectionChip collection={value as any} />
        ) : (
            str(value)
        ),
    formatString: value =>
        value && typeof value === 'object'
            ? ((value as any).absoluteDisplayName ??
              (value as any).displayName ??
              (value as any).name ??
              '')
            : str(value),
};

export function privacyLabels(t: TFunction): Record<Privacy, string> {
    return {
        [Privacy.Secret]: t('privacy.secret', 'Secret'),
        [Privacy.PrivateInWorkspace]: t(
            'privacy.private_in_workspace',
            'Private in workspace'
        ),
        [Privacy.PublicInWorkspace]: t(
            'privacy.public_in_workspace',
            'Public in workspace'
        ),
        [Privacy.Private]: t('privacy.private', 'Private'),
        [Privacy.PublicForUsers]: t(
            'privacy.public_for_users',
            'Public for users'
        ),
        [Privacy.Public]: t('privacy.public', 'Public'),
    };
}

const privacyType: AttributeTypeDef = {
    rich: true,
    formats: t => [
        {name: 'full', label: t('attribute.format.full', 'Full')},
        {name: 'short', label: t('attribute.format.short', 'Short')},
    ],
    format: (value, format) => {
        const n = toNumber(value);
        if (n === undefined) {
            return null;
        }

        return format === 'short' ? (
            <PrivacyIcon privacy={n as Privacy} />
        ) : (
            <PrivacyChip privacy={n as Privacy} />
        );
    },
    formatString: (value, _f, {t}) => {
        const n = toNumber(value);

        return n === undefined
            ? ''
            : (privacyLabels(t)[n as Privacy] ?? String(n));
    },
    normalize: value => toNumber(value),
};

export function assetStatusLabels(t: TFunction): Record<AssetStatus, string> {
    return {
        [AssetStatus.Accepted]: t('asset_status.accepted', 'Accepted'),
        [AssetStatus.Pending]: t('asset_status.pending', 'Pending'),
        [AssetStatus.Quarantined]: t('asset_status.quarantined', 'Quarantined'),
    };
}

const assetStatusType: AttributeTypeDef = {
    rich: true,
    formats: () => [],
    format: value => {
        const n = toNumber(value);

        return n === undefined ? null : (
            <AssetStatusChip status={n as AssetStatus} />
        );
    },
    formatString: (value, _f, {t}) => {
        const n = toNumber(value);

        return n === undefined
            ? ''
            : (assetStatusLabels(t)[n as AssetStatus] ?? String(n));
    },
    normalize: value => toNumber(value),
};

const jsonType: AttributeTypeDef = {
    formats: () => [],
    format: value => (
        <pre className="max-h-60 overflow-auto rounded bg-muted p-2 font-mono text-xs">
            {typeof value === 'string' ? value : JSON.stringify(value, null, 2)}
        </pre>
    ),
    formatString: value =>
        typeof value === 'string' ? value : JSON.stringify(value),
};

const htmlType: AttributeTypeDef = {
    rich: true,
    formats: () => [],
    format: value => (
        <div
            className="prose prose-sm max-w-none dark:prose-invert"
            dangerouslySetInnerHTML={{__html: str(value)}}
        />
    ),
    formatString: value => str(value).replace(/<[^>]+>/g, ''),
};

const registry: Record<AttributeType, AttributeTypeDef> = {
    [AttributeType.Text]: textType,
    [AttributeType.Textarea]: {
        ...textType,
        format: (value, _f, ctx) => (
            <span className="whitespace-pre-wrap">
                <Highlight
                    text={
                        typeof ctx.highlight === 'string'
                            ? ctx.highlight
                            : str(value)
                    }
                />
            </span>
        ),
    },
    [AttributeType.Keyword]: textType,
    [AttributeType.Id]: textType,
    [AttributeType.Ip]: textType,
    [AttributeType.FileType]: textType,
    [AttributeType.Rendition]: textType,
    [AttributeType.Code]: jsonType,
    [AttributeType.WebVtt]: jsonType,
    [AttributeType.Json]: jsonType,
    [AttributeType.Html]: htmlType,
    [AttributeType.Boolean]: booleanType,
    [AttributeType.Number]: numberType,
    [AttributeType.Duration]: durationType,
    [AttributeType.FileSize]: fileSizeType,
    [AttributeType.Date]: dateType(false),
    [AttributeType.DateTime]: dateType(true),
    [AttributeType.Color]: colorType,
    [AttributeType.GeoPoint]: geoPointType,
    [AttributeType.Entity]: entityType,
    [AttributeType.Tag]: tagType,
    [AttributeType.User]: userType,
    [AttributeType.Workspace]: workspaceType,
    [AttributeType.CollectionPath]: collectionType,
    [AttributeType.Story]: collectionType,
    [AttributeType.Privacy]: privacyType,
    [AttributeType.AssetStatus]: assetStatusType,
};

export function getAttributeType(
    type: AttributeType | string | undefined
): AttributeTypeDef {
    return registry[(type as AttributeType) ?? AttributeType.Text] ?? textType;
}

export function formatAttributeValue(
    type: AttributeType | string | undefined,
    value: unknown,
    format: string | undefined,
    ctx: FormatContext
): ReactNode {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return getAttributeType(type).format(value, format, ctx);
}

export function formatAttributeString(
    type: AttributeType | string | undefined,
    value: unknown,
    format: string | undefined,
    ctx: FormatContext
): string {
    if (value === null || value === undefined) {
        return '';
    }

    return getAttributeType(type).formatString(value, format, ctx);
}
