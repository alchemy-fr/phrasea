'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {DownloadIcon, MergeIcon, UploadIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributeEntity, EntityList} from '@/types/api';
import {
    exportEntityList,
    importEntityList,
    mergeAttributeEntities,
} from '@/lib/api/metadata';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {type ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {downloadUrl} from '@/lib/utils/misc';

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
                await importEntityList(list.id, 'csv', data);
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
        </FormDialog>
    );
}
