'use client';

import type {ReactNode} from 'react';
import {useTranslation} from 'react-i18next';
import {Tooltip} from '@/components/ui/overlays';
import {CopyButton} from '@/components/ui/copy-button';
import {formatDateTime} from '@/lib/utils/format';
import {cn} from '@/lib/utils/cn';

/** Label / value pairs laid out in a row (the legacy `HorizontalTable`) */
export function DetailFields({
    fields,
    className,
}: {
    fields: [ReactNode, ReactNode][];
    className?: string;
}) {
    return (
        <dl className={cn('flex flex-wrap gap-x-6 gap-y-2', className)}>
            {fields.map(([label, value], i) => (
                <div key={i} className="min-w-0">
                    <dt className="text-[11px] text-muted-foreground">
                        {label}
                    </dt>
                    <dd className="truncate text-sm font-semibold">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

/** Relative date, the absolute one in a tooltip (the legacy `DateValue`) */
export function RelativeDate({date}: {date: string | null | undefined}) {
    const {i18n} = useTranslation();
    if (!date) {
        return <>-</>;
    }

    return (
        <Tooltip content={formatDateTime(date, 'long', i18n.language)}>
            <span className="whitespace-nowrap">
                {formatDateTime(date, 'relative', i18n.language)}
            </span>
        </Tooltip>
    );
}

export function DetailSection({
    title,
    actions,
    children,
}: {
    title: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section className="space-y-2 border-t pt-3">
            <div className="flex items-center gap-2">
                <h3 className="flex-1 text-sm font-semibold">{title}</h3>
                {actions}
            </div>
            {children}
        </section>
    );
}

/** Inputs, outputs, context… pretty printed */
export function JsonSection({title, data}: {title: ReactNode; data: unknown}) {
    const json = JSON.stringify(data, null, 4);

    return (
        <DetailSection title={title} actions={<CopyButton value={json} />}>
            <pre className="max-h-80 overflow-auto rounded-md bg-muted p-3 font-mono text-xs">
                {json}
            </pre>
        </DetailSection>
    );
}
