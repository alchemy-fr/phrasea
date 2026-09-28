'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {DownloadIcon} from 'lucide-react';
import type {Asset} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Checkbox} from '@/components/ui/controls';
import {FileTypeChip} from '@/components/chips';
import {formatFileSize} from '@/lib/utils/format';
import {saveUrlAs} from '@/lib/utils/misc';
import {cn} from '@/lib/utils/cn';
import {
    extensionFromMimeType,
    getDownloadFileName,
    type ShareRendition,
} from './shareRenditions';

export type ShareDownloadItem = {
    asset: Asset;
    renditions: ShareRendition[];
};

/** A line of the dialog: a rendition, or every asset's rendition of a name */
type Choice = {
    key: string;
    label: string;
    type?: string;
    size?: number;
    /** Number of files it downloads */
    count: number;
    files: {asset: Asset; rendition: ShareRendition}[];
};

/**
 * Lets the visitor pick the renditions to download. For one asset, a line per
 * rendition — keyed by the rendition, as several renditions may be the same
 * file; for several assets, a line per rendition name, downloading it for
 * every asset having it.
 */
export function ShareDownloadDialog({
    open,
    onOpenChange,
    resolve,
    items,
}: ModalProps<number> & {items: ShareDownloadItem[]}) {
    const {t, i18n} = useTranslation();
    const single = items.length === 1;

    const choices = useMemo<Choice[]>(() => {
        if (single) {
            const {asset, renditions} = items[0];

            return renditions.map(r => ({
                key: r.id,
                label: r.label,
                type: r.type,
                size: r.size,
                count: 1,
                files: [{asset, rendition: r}],
            }));
        }
        const byName = new Map<string, Choice>();
        items.forEach(({asset, renditions}) =>
            renditions.forEach(r => {
                const choice = byName.get(r.name) ?? {
                    key: r.name,
                    label: r.label,
                    count: 0,
                    size: 0,
                    files: [],
                };
                choice.files.push({asset, rendition: r});
                choice.count++;
                choice.size = (choice.size ?? 0) + (r.size ?? 0);
                byName.set(r.name, choice);
            })
        );

        return [...byName.values()];
    }, [items, single]);

    const [selected, setSelected] = useState<string[]>(() =>
        choices.length === 1 ? [choices[0].key] : []
    );
    const [progress, setProgress] = useState<{done: number; total: number}>();
    const allSelected =
        choices.length > 0 && selected.length === choices.length;

    const toggle = (key: string, on: boolean) =>
        setSelected(prev =>
            on ? [...prev, key] : prev.filter(k => k !== key)
        );

    const submit = async () => {
        // A rendition once, even if several lines lead to it
        const files = new Map<string, Choice['files'][number]>();
        choices
            .filter(c => selected.includes(c.key))
            .forEach(c =>
                c.files.forEach(f =>
                    files.set(`${f.asset.id}:${f.rendition.id}`, f)
                )
            );
        const list = [...files.values()];
        setProgress({done: 0, total: list.length});
        // One at a time: browsers hold back bursts of downloads
        for (const [i, {asset, rendition}] of list.entries()) {
            await saveUrlAs(
                rendition.url,
                getDownloadFileName(asset.name, rendition),
                rendition.size
            );
            setProgress({done: i + 1, total: list.length});
        }
        resolve?.(list.length);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={
                single
                    ? t('share.download.title', 'Download')
                    : t(
                          'share.download.title_multiple',
                          'Download {{count}} assets',
                          {count: items.length}
                      )
            }
            description={
                single
                    ? t(
                          'share.download.help',
                          'Select the renditions to download.'
                      )
                    : t(
                          'share.download.help_multiple',
                          'Select the renditions to download for every asset.'
                      )
            }
            submitLabel={
                progress
                    ? t(
                          'share.download.progress',
                          'Downloading {{done}}/{{total}}…',
                          progress
                      )
                    : t('share.download.submit', 'Download')
            }
            submitIcon={<DownloadIcon />}
            canSubmit={selected.length > 0}
            bodyClassName="space-y-3"
            onSubmit={submit}
        >
            {choices.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'share.download.empty',
                        'No file available for download.'
                    )}
                </p>
            ) : (
                <>
                    {choices.length > 1 ? (
                        <label className="flex cursor-pointer items-center gap-2.5 border-b pb-2 text-sm font-medium">
                            <Checkbox
                                checked={
                                    allSelected
                                        ? true
                                        : selected.length > 0
                                          ? 'indeterminate'
                                          : false
                                }
                                onCheckedChange={v =>
                                    setSelected(
                                        v === true
                                            ? choices.map(c => c.key)
                                            : []
                                    )
                                }
                            />
                            {t('share.download.select_all', 'Select all')}
                        </label>
                    ) : null}
                    <ul
                        data-testid="share-download-renditions"
                        className="flex flex-col gap-1"
                    >
                        {choices.map(c => (
                            <li key={c.key}>
                                <label
                                    data-testid="share-download-rendition"
                                    className={cn(
                                        'flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm hover:bg-accent',
                                        selected.includes(c.key) &&
                                            'bg-accent/60'
                                    )}
                                >
                                    <Checkbox
                                        checked={selected.includes(c.key)}
                                        onCheckedChange={v =>
                                            toggle(c.key, v === true)
                                        }
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {c.label}
                                    </span>
                                    {single ? (
                                        c.type ? (
                                            <FileTypeChip
                                                mimeType={c.type}
                                                extension={extensionFromMimeType(
                                                    c.type
                                                )}
                                            />
                                        ) : null
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            {t(
                                                'share.download.files',
                                                '{{count}} file(s)',
                                                {count: c.count}
                                            )}
                                        </span>
                                    )}
                                    {c.size ? (
                                        <span className="w-20 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                                            {formatFileSize(
                                                c.size,
                                                true,
                                                i18n.language
                                            )}
                                        </span>
                                    ) : null}
                                </label>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </FormDialog>
    );
}
