'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {UploadIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {EntityList} from '@/types/api';
import {
    importEntityHierarchy,
    type EntityHierarchyImportReport,
} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {FormRow, Textarea} from '@/components/ui/input';
import {FormDialog} from '@/components/modals/FormDialog';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {hierarchyQueryKey} from './EntityHierarchyDialog';

/**
 * Places existing entities from a CSV (entity_external_id, parent_external_id,
 * position): labels and translations are never changed. A dry run reports
 * the rejected or ambiguous rows first.
 */
export function ImportEntityHierarchyDialog({
    open,
    onOpenChange,
    list,
}: ModalProps & {list: EntityList}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [data, setData] = useState('');
    const [report, setReport] = useState<EntityHierarchyImportReport>();
    const [checking, setChecking] = useState(false);

    const check = async () => {
        setChecking(true);
        try {
            setReport(await importEntityHierarchy(list.id, data, true));
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setChecking(false);
        }
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            dirty={!!data}
            title={t('entity_hierarchy.import_title', 'Import the hierarchy')}
            submitLabel={t('entity_hierarchy.import_submit', 'Import')}
            submitIcon={<UploadIcon />}
            canSubmit={!!report && report.applied > 0}
            footerStart={
                <Button
                    variant="outline"
                    loading={checking}
                    disabled={!data.trim()}
                    onClick={check}
                >
                    {t('entity_hierarchy.import_check', 'Check')}
                </Button>
            }
            onSubmit={async () => {
                const result = await importEntityHierarchy(
                    list.id,
                    data,
                    false
                );
                toast.success(
                    t(
                        'entity_hierarchy.imported',
                        '{{count}} entities placed',
                        {
                            count: result.applied,
                        }
                    )
                );
                await queryClient.invalidateQueries({
                    queryKey: hierarchyQueryKey(list.id),
                });
            }}
        >
            <FormRow
                label="CSV"
                help={t(
                    'entity_hierarchy.import_help',
                    'Columns: entity_external_id, parent_external_id (empty for the root), position. Entities without external ID may be referenced by their ID.'
                )}
            >
                <Textarea
                    rows={10}
                    className="font-mono text-xs"
                    value={data}
                    onChange={e => {
                        setData(e.target.value);
                        setReport(undefined);
                    }}
                />
            </FormRow>
            {report ? (
                <div className="space-y-2 text-sm">
                    <p>
                        {t(
                            'entity_hierarchy.import_report',
                            '{{count}} entities will be placed.',
                            {count: report.applied}
                        )}
                    </p>
                    {report.rejected.length > 0 ? (
                        <ul className="max-h-48 list-disc overflow-auto pl-5 text-destructive">
                            {report.rejected.map((r, i) => (
                                <li key={i}>
                                    {t(
                                        'entity_hierarchy.import_rejected',
                                        'Line {{line}} ({{entity}}): {{reason}}',
                                        {
                                            line: r.line,
                                            entity: r.entity ?? '—',
                                            reason: r.reason,
                                        }
                                    )}
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            ) : null}
        </FormDialog>
    );
}
