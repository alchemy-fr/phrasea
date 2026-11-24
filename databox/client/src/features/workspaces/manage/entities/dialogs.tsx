'use client';

import {useState} from 'react';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {DownloadIcon, MergeIcon, UploadIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeEntity, EntityList} from '@/types/api';
import {
    exportEntityList,
    getLocales,
    importEntityList,
    mergeAttributeEntities,
} from '@/lib/api/metadata';
import {FormRow, Input, Textarea} from '@alchemy/next-ui/ui/input';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {type ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import {Progress} from '@alchemy/next-ui/ui/misc';
import {downloadUrl} from '@alchemy/next-ui/utils/misc';
import {getErrorMessage} from '@alchemy/next-ui/utils/errors';

export const THESAURUS_FORMAT = 'thesaurus';
const NO_DEFAULT_LOCALE = '__none';

/**
 * Progress of a running import: undefined when none runs, null while its
 * progress is unknown, else a ratio.
 */
type ImportState = number | null | undefined;

function ImportProgress({progress}: {progress: ImportState}) {
    const {t} = useTranslation();
    if (progress === undefined) {
        return null;
    }

    return (
        <div className="space-y-1" data-testid="import-progress">
            <Progress
                value={progress === null ? undefined : progress * 100}
                indeterminate={progress === null}
            />
            <p className="text-xs text-muted-foreground">
                {progress === null
                    ? t('entity.import.running', 'Import in progress…')
                    : t(
                          'entity.import.progress',
                          'Import in progress… {{percent}}%',
                          {percent: Math.floor(progress * 100)}
                      )}
            </p>
        </div>
    );
}

/**
 * Runs an import, following its progress.
 */
function useImportProgress() {
    const [progress, setProgress] = useState<ImportState>();

    return {
        progress,
        run: async (
            importFn: (
                onProgress: (done: number, total: number) => void
            ) => Promise<void>
        ) => {
            setProgress(null);
            try {
                await importFn((done, total) => setProgress(done / total));
            } finally {
                setProgress(undefined);
            }
        },
    };
}

const THESAURUS_EXAMPLE = `animal
    mammifère [fr]; mammal [en]
        {#ff0000} chien [fr]; dog [en]
        ! {#00f800} cat [en]; chat [fr]
    ? oiseau [fr]`;

export function MergeEntitiesDialog({
    open,
    onOpenChange,
    resolve,
    entities,
    onMerged,
}: ModalProps<string> & {entities: AttributeEntity[]; onMerged: () => void}) {
    const {t} = useTranslation();
    const [kept, setKept] = useState(entities[0]?.id);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('entity.merge.title', 'Merge {{count}} values', {
                count: entities.length,
            })}
            submitLabel={t('entity.merge', 'Merge')}
            submitIcon={<MergeIcon />}
            canSubmit={!!kept}
            onSubmit={async () => {
                await mergeAttributeEntities(
                    kept!,
                    entities.map(e => e.id)
                );
                toast.success(t('entity.merged', 'Values merged'));
                onMerged();
                resolve?.(kept!);
            }}
        >
            <p className="mb-2 text-sm text-muted-foreground">
                {t(
                    'entity.merge.help',
                    'Select the value to keep; the others are replaced by it everywhere.'
                )}
            </p>
            <SimpleSelect
                value={kept}
                onValueChange={setKept}
                options={entities.map(e => ({
                    value: e.id,
                    label: e.value,
                }))}
            />
        </FormDialog>
    );
}

export function ExportEntitiesDialog({
    open,
    onOpenChange,
    list,
    locales,
}: ModalProps & {list: EntityList; locales: string[]}) {
    const {t} = useTranslation();
    const [format, setFormat] = useState('csv');
    const [locale, setLocale] = useState('__all');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('entity.export.title', 'Export "{{name}}"', {
                name: list.name,
            })}
            submitLabel={t('entity.export', 'Export')}
            submitIcon={<DownloadIcon />}
            bodyClassName="space-y-3"
            onSubmit={async () => {
                const {blob, filename} = await exportEntityList(list.id, {
                    format,
                    locale: locale === '__all' ? undefined : locale,
                });
                downloadUrl(URL.createObjectURL(blob), filename);
            }}
        >
            <FormRow label={t('entity.export.format', 'Format')}>
                <SimpleSelect
                    value={format}
                    onValueChange={setFormat}
                    options={[
                        {value: 'csv', label: 'CSV'},
                        {value: 'json', label: 'JSON'},
                        {value: 'liform', label: 'LiForm (Uploader)'},
                    ]}
                />
            </FormRow>
            <FormRow label={t('entity.export.locale', 'Locale')}>
                <SimpleSelect
                    value={locale}
                    onValueChange={setLocale}
                    options={[
                        {
                            value: '__all',
                            label: t(
                                'entity.export.all_locales',
                                'All locales'
                            ),
                        },
                        ...locales.map(l => ({value: l, label: l})),
                    ]}
                />
            </FormRow>
        </FormDialog>
    );
}

export function ImportEntitiesDialog({
    open,
    onOpenChange,
    list,
    onImported,
}: ModalProps & {list: EntityList; onImported: () => void}) {
    const {t} = useTranslation();
    const [data, setData] = useState('');
    const {progress, run} = useImportProgress();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('entity.import.title', 'Import into "{{name}}"', {
                name: list.name,
            })}
            submitLabel={t('entity.import', 'Import')}
            submitIcon={<UploadIcon />}
            canSubmit={!!data.trim()}
            dirty={!!data.trim()}
            bodyClassName="space-y-3"
            onSubmit={async () => {
                await run(onProgress =>
                    importEntityList(list.id, 'csv', data, {onProgress})
                );
                toast.success(t('entity.imported', 'Values imported'));
                onImported();
            }}
        >
            <Input
                type="file"
                accept=".csv,text/csv"
                onChange={async e => {
                    const f = e.target.files?.[0];
                    if (f) {
                        setData(await f.text());
                    }
                }}
            />
            <Textarea
                value={data}
                onChange={e => setData(e.target.value)}
                className="min-h-40 font-mono text-xs"
                placeholder={'value,locale,translation\n…'}
            />
            <ImportProgress progress={progress} />
        </FormDialog>
    );
}

/**
 * Imports a tabulated thesaurus: one entity per line, indented under its
 * parent. The syntax errors reported by the server are shown in place.
 */
export function ImportThesaurusDialog({
    open,
    onOpenChange,
    list,
    onImported,
}: ModalProps & {list: EntityList; onImported: () => void}) {
    const {t} = useTranslation();
    const [data, setData] = useState('');
    const [error, setError] = useState<string>();
    const [defaultLocale, setDefaultLocale] = useState(NO_DEFAULT_LOCALE);
    const locales = useQuery({queryKey: ['locales'], queryFn: getLocales});
    const {progress, run} = useImportProgress();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t(
                'entity.thesaurus.import_title',
                'Import a tabulated thesaurus into "{{name}}"',
                {name: list.name}
            )}
            submitLabel={t('entity.import', 'Import')}
            submitIcon={<UploadIcon />}
            canSubmit={!!data.trim()}
            dirty={!!data.trim()}
            bodyClassName="space-y-3"
            onSubmit={async () => {
                try {
                    await run(onProgress =>
                        importEntityList(list.id, THESAURUS_FORMAT, data, {
                            defaultLocale:
                                defaultLocale === NO_DEFAULT_LOCALE
                                    ? undefined
                                    : defaultLocale,
                            onProgress,
                        })
                    );
                } catch (e) {
                    setError(getErrorMessage(e));

                    return false;
                }
                toast.success(t('entity.imported', 'Values imported'));
                onImported();
            }}
        >
            <p className="text-sm text-muted-foreground">
                {t(
                    'entity.thesaurus.import_help',
                    'One entity per line, indented under its parent. A leading "?" makes it pending, "!" rejected; {#rrggbb} sets its color; the terms are separated by ";", "term [locale]" being a translation. An entity of the same value is updated, or moved when it is the only one of the list.'
                )}
            </p>
            <FormRow
                label={t(
                    'entity.thesaurus.default_locale',
                    'Language of the terms without language'
                )}
                help={t(
                    'entity.thesaurus.default_locale_help',
                    'The first one becomes the translation in this language, the next ones its synonyms.'
                )}
            >
                <SimpleSelect
                    value={defaultLocale}
                    onValueChange={v => {
                        setDefaultLocale(v);
                        setError(undefined);
                    }}
                    options={[
                        {
                            value: NO_DEFAULT_LOCALE,
                            label: t(
                                'entity.thesaurus.no_default_locale',
                                'None (labels without language)'
                            ),
                        },
                        ...(locales.data ?? []).map(l => ({
                            value: l.id,
                            label: `${l.name} (${l.id})`,
                        })),
                    ]}
                />
            </FormRow>
            <Input
                type="file"
                accept=".txt,text/plain"
                onChange={async e => {
                    const f = e.target.files?.[0];
                    if (f) {
                        setData(await f.text());
                        setError(undefined);
                    }
                }}
            />
            <Textarea
                value={data}
                onChange={e => {
                    setData(e.target.value);
                    setError(undefined);
                }}
                onKeyDown={e => {
                    // Tab indents instead of leaving the field
                    if (e.key === 'Tab' && !e.shiftKey) {
                        e.preventDefault();
                        const el = e.currentTarget;
                        const {selectionStart: start, selectionEnd: end} = el;
                        const next = `${data.slice(0, start)}\t${data.slice(end)}`;
                        setData(next);
                        requestAnimationFrame(() =>
                            el.setSelectionRange(start + 1, start + 1)
                        );
                    }
                }}
                className="min-h-60 font-mono text-xs [tab-size:4]"
                placeholder={THESAURUS_EXAMPLE}
                data-testid="thesaurus-data"
            />
            <ImportProgress progress={progress} />
            {error ? (
                <pre
                    className="max-h-40 overflow-auto rounded-md border border-destructive/50 bg-destructive/10 p-2 text-xs whitespace-pre-wrap text-destructive"
                    data-testid="thesaurus-error"
                >
                    {error}
                </pre>
            ) : null}
        </FormDialog>
    );
}
