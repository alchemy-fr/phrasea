'use client';

import {useTranslation} from 'react-i18next';
import {BookOpenIcon, ChevronDownIcon} from 'lucide-react';
import {CopyButton} from '@/components/ui/copy-button';

export type ReferenceSection = {
    name: string;
    description?: string | null;
    reference: string;
};

/**
 * Collapsible configuration reference (YAML samples of the available
 * modules), shown below the field it documents: wide samples scroll
 * horizontally inside it instead of overflowing.
 */
export function ReferenceDetails({
    sections,
    title,
}: {
    sections: ReferenceSection[];
    title?: string;
}) {
    const {t} = useTranslation();
    const shown = sections.filter(r => r.reference.trim());
    if (shown.length === 0) {
        return null;
    }

    return (
        <details className="group/ref min-w-0 rounded-md border text-xs">
            <summary className="flex cursor-pointer items-center gap-2 p-3 font-medium select-none">
                <BookOpenIcon className="size-4" />
                <span className="flex-1">
                    {title ??
                        t('integration.reference', 'Configuration reference')}
                </span>
                <ChevronDownIcon className="size-4 transition-transform group-open/ref:rotate-180" />
            </summary>
            <div className="space-y-3 border-t p-3">
                {shown.map(r => (
                    <div key={r.name} className="min-w-0">
                        <div className="flex items-center gap-1 font-semibold">
                            {r.name} <CopyButton value={r.reference} />
                        </div>
                        {r.description ? (
                            <p className="text-muted-foreground">
                                {r.description}
                            </p>
                        ) : null}
                        <pre className="mt-1 overflow-x-auto rounded bg-muted p-2 font-mono">
                            {r.reference}
                        </pre>
                    </div>
                ))}
            </div>
        </details>
    );
}
