'use client';

import {Fragment, ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {EyeIcon} from 'lucide-react';
import type {Asset, GroupValue} from '@/types/api';
import {getAttributeType} from '@/features/attributes/types/registry';
import {useFormatContext} from '@/features/attributes/AttributeValue';
import {useAttributeFormats} from '@/features/attributes/formatStore';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {cn} from '@/lib/utils/cn';

export type ListSection = {
    key: string;
    pageIndex?: number;
    group?: GroupValue;
    items: {asset: Asset; index: number}[];
};

/**
 * Splits pages into sections: a page divider for every page after the first
 * and a group divider whenever the `groupValue` changes.
 */
export function buildSections(pages: Asset[][]): ListSection[] {
    const sections: ListSection[] = [];
    let index = 0;
    let lastGroupKey: string | undefined;
    pages.forEach((page, pageIndex) => {
        let current: ListSection | undefined;
        page.forEach(asset => {
            const groupKey = asset.groupValue
                ? `${asset.groupValue.name}:${asset.groupValue.key ?? ''}`
                : undefined;
            const newGroup =
                groupKey !== undefined && groupKey !== lastGroupKey;
            if (!current || newGroup) {
                current = {
                    key: `${pageIndex}-${groupKey ?? ''}-${index}`,
                    pageIndex: current ? undefined : pageIndex,
                    group: newGroup ? asset.groupValue : undefined,
                    items: [],
                };
                sections.push(current);
                lastGroupKey = groupKey ?? lastGroupKey;
            }
            current.items.push({asset, index: index++});
        });
        if (page.length === 0 && pageIndex > 0) {
            sections.push({key: `${pageIndex}-empty`, pageIndex, items: []});
        }
    });

    return sections;
}

export function SectionDivider({
    section,
    sticky = true,
}: {
    section: ListSection;
    sticky?: boolean;
}) {
    const {t} = useTranslation();
    const showPage = section.pageIndex !== undefined && section.pageIndex > 0;
    if (!showPage && !section.group) {
        return null;
    }

    return (
        <div
            className={cn(
                'z-[5] flex items-center gap-3 bg-background/95 px-3 py-1.5 text-xs text-muted-foreground backdrop-blur',
                sticky && 'sticky top-0'
            )}
        >
            {section.group ? <GroupLabel group={section.group} /> : null}
            {showPage ? (
                <span className="ml-auto rounded-full border px-2 py-0.5 font-mono">
                    {t('list.page', 'Page {{n}}', {n: section.pageIndex! + 1})}
                </span>
            ) : null}
            <div className="absolute inset-x-0 bottom-0 h-px bg-border" />
        </div>
    );
}

function GroupLabel({group}: {group: GroupValue}) {
    const {t} = useTranslation();
    const ctx = useFormatContext();
    const {getFormat, setTypeFormat} = useAttributeFormats();
    const typeDef = getAttributeType(group.type);
    const formats = typeDef.formats(ctx.t);
    const current = getFormat(group.type) ?? formats[0]?.name;
    const values = group.values ?? [];

    let content: ReactNode;
    if (values.length === 0 || group.key === null) {
        content = (
            <span className="italic">{t('list.group.none', 'None')}</span>
        );
    } else {
        content = (
            <span className="flex flex-wrap items-center gap-1">
                {values.map((v, i) => (
                    <Fragment key={i}>
                        {typeDef.format(v, current, ctx)}
                    </Fragment>
                ))}
            </span>
        );
    }

    return (
        <div className="group/div flex items-center gap-2 text-sm font-medium text-foreground">
            <span className="text-xs text-muted-foreground uppercase">
                {group.name}
            </span>
            {content}
            {formats.length > 1 ? (
                <Tooltip
                    content={t('attribute.change_format', 'Change format')}
                >
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        className="opacity-0 group-hover/div:opacity-100"
                        onClick={() => {
                            const idx = formats.findIndex(
                                f => f.name === current
                            );
                            setTypeFormat(
                                group.type,
                                formats[(idx + 1) % formats.length].name
                            );
                        }}
                    >
                        <EyeIcon />
                    </Button>
                </Tooltip>
            ) : null}
        </div>
    );
}
