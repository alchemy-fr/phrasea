'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {DownloadIcon} from 'lucide-react';
import type {Asset, RenditionDefinition, Workspace} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {InlineLoader} from '@/components/ui/loader';
import {exportAssets, getRenditionDefinitions} from '@/lib/api/misc';
import {useExportStore} from '@/features/assets/exportStore';
import {downloadUrl} from '@/lib/utils/misc';

export function ExportDialog({
    open,
    onOpenChange,
    resolve,
    assets,
}: ModalProps<string[]> & {assets: Asset[]}) {
    const {t} = useTranslation();
    const [selected, setSelected] = useState<string[]>([]);
    const addExport = useExportStore(s => s.add);
    const workspaceIds = useMemo(
        () => [...new Set(assets.map(a => a.workspace.id))],
        [assets]
    );

    const definitions = useQuery({
        queryKey: ['rendition-definitions', 'export', workspaceIds],
        queryFn: () => getRenditionDefinitions({workspaceIds}),
    });

    const byWorkspace = useMemo(() => {
        const index: Record<
            string,
            {name: string; defs: RenditionDefinition[]}
        > = {};
        definitions.data?.items.forEach(rd => {
            const ws = rd.workspace as Workspace;
            const id = typeof ws === 'string' ? ws : ws.id;
            const name =
                typeof ws === 'string' ? ws : (ws.displayName ?? ws.name);
            (index[id] ??= {name, defs: []}).defs.push(rd);
        });

        return index;
    }, [definitions.data]);

    const submit = async () => {
        const exp = await exportAssets({
            assets: assets.map(a => a.id),
            renditions: selected,
        });
        addExport(exp);
        if (exp.downloadUrl) {
            downloadUrl(exp.downloadUrl);
        }
        resolve?.(selected);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('export.dialog.title', 'Export {{count}} asset(s)', {
                count: assets.length,
            })}
            description={t(
                'export.dialog.help',
                'Select the renditions to include in the export.'
            )}
            submitLabel={t('export.dialog.submit', 'Export')}
            submitIcon={<DownloadIcon />}
            canSubmit={selected.length > 0}
            bodyClassName="space-y-4"
            onSubmit={submit}
        >
            {definitions.isLoading ? <InlineLoader /> : null}
            {Object.entries(byWorkspace).map(([wsId, {name, defs}]) => (
                <div key={wsId}>
                    {workspaceIds.length > 1 ? (
                        <h4 className="mb-1 text-xs font-semibold text-muted-foreground uppercase">
                            {name}
                        </h4>
                    ) : null}
                    <div className="space-y-1.5">
                        {defs.map(d => (
                            <LabeledControl
                                key={d.id}
                                label={d.displayName ?? d.name}
                            >
                                <Checkbox
                                    checked={selected.includes(d.id)}
                                    onCheckedChange={v =>
                                        setSelected(prev =>
                                            v
                                                ? [...prev, d.id]
                                                : prev.filter(x => x !== d.id)
                                        )
                                    }
                                />
                            </LabeledControl>
                        ))}
                    </div>
                </div>
            ))}
        </FormDialog>
    );
}
