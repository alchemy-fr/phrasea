'use client';

import {ReactNode, useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {AlertTriangleIcon} from 'lucide-react';
import type {Attribute, AttributeDefinitionOrBuiltIn} from '@/types/api';
import {formatAttributeValue, getAttributeType} from './types/registry';
import {useAttributeFormats} from './formatStore';
import {Tooltip} from '@/components/ui/overlays';
import {isRtl, NO_LOCALE, pickLocale} from '@/lib/utils/locale';

export type AttributeGroup = {
    definition: Attribute['definition'];
    attribute: Attribute | Attribute[];
    locale?: string;
};

/**
 * Groups asset attributes by definition, keeping the best locale.
 */
export function groupAttributes(attributes: Attribute[]): AttributeGroup[] {
    const byDef = new Map<
        string,
        {
            definition: Attribute['definition'];
            locales: Record<string, Attribute | Attribute[]>;
        }
    >();
    for (const attr of attributes) {
        const defId = attr.definition.id;
        const entry = byDef.get(defId) ?? {
            definition: attr.definition,
            locales: {},
        };
        const locale = attr.locale ?? NO_LOCALE;
        if (attr.definition.multiple) {
            const list =
                (entry.locales[locale] as Attribute[] | undefined) ?? [];
            list.push(attr);
            entry.locales[locale] = list;
        } else {
            entry.locales[locale] = attr;
        }
        byDef.set(defId, entry);
    }

    const groups: AttributeGroup[] = [];
    for (const {definition, locales} of byDef.values()) {
        const available = Object.keys(locales).filter(l => l !== NO_LOCALE);
        const best = pickLocale(available) ?? available[0];
        const attribute = locales[best] ?? locales[NO_LOCALE];
        if (attribute) {
            groups.push({definition, attribute, locale: best});
        }
    }

    return groups;
}

export function useFormatContext(locale?: string) {
    const {t, i18n} = useTranslation();

    return useMemo(
        () => ({t, lang: i18n.language, locale}),
        [t, i18n.language, locale]
    );
}

export function AttributeValue({
    definition,
    attribute,
    format,
}: {
    definition: AttributeDefinitionOrBuiltIn;
    attribute: Attribute | Attribute[] | undefined;
    format?: string;
}) {
    const {getFormat} = useAttributeFormats();
    const fmt = format ?? getFormat(definition.type, definition.id);

    if (!attribute) {
        return <span className="text-muted-foreground">—</span>;
    }
    const list = Array.isArray(attribute) ? attribute : [attribute];
    const rtl = isRtl(list[0]?.locale);
    const rich = getAttributeType(definition.type).rich;

    return (
        <div
            dir={rtl ? 'rtl' : undefined}
            className={
                Array.isArray(attribute) && rich
                    ? 'flex flex-wrap gap-1'
                    : undefined
            }
        >
            {list.map((a, i) => (
                <AttributeSingleValue
                    key={a.id ?? i}
                    attribute={a}
                    format={fmt}
                    type={definition.type}
                    asListItem={list.length > 1 && !rich}
                />
            ))}
        </div>
    );
}

function AttributeSingleValue({
    attribute,
    format,
    type,
    asListItem,
}: {
    attribute: Attribute;
    format?: string;
    type: string;
    asListItem: boolean;
}) {
    const {t} = useTranslation();
    const ctx = useFormatContext(attribute.locale);

    let node: ReactNode;
    if (attribute.invalid) {
        node = (
            <span className="inline-flex items-center gap-1 text-warning-foreground">
                <Tooltip content={t('attribute.invalid', 'Invalid value')}>
                    <AlertTriangleIcon className="size-3.5" />
                </Tooltip>
                {String(attribute.value ?? '')}
            </span>
        );
    } else {
        node = formatAttributeValue(type, attribute.value, format, {
            ...ctx,
            highlight: attribute.highlight,
        });
    }

    return asListItem ? (
        <div className="before:mr-1 before:text-muted-foreground before:content-['•']">
            {node}
        </div>
    ) : (
        <>{node}</>
    );
}

export function formatValueForCopy(
    definition: AttributeDefinitionOrBuiltIn,
    attribute: Attribute | Attribute[],
    ctx: ReturnType<typeof useFormatContext>
): string {
    const list = Array.isArray(attribute) ? attribute : [attribute];
    const def = getAttributeType(definition.type);

    return list.map(a => def.formatString(a.value, undefined, ctx)).join('\n');
}
