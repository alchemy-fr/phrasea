'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQueries, useQuery} from '@tanstack/react-query';
import {DownloadIcon} from 'lucide-react';
import type {Asset, RenditionDefinition, Workspace} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {InlineLoader} from '@/components/ui/loader';
import {exportAssets, getRenditionDefinitions} from '@/lib/api/misc';
import {getWorkspace, signWorkspaceTerms} from '@/lib/api/collections';
import {useCollectionStore} from '@/features/collections/collectionStore';
import {TermsContent} from '@/features/share/ShareTerms';
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
    const [acceptedTerms, setAcceptedTerms] = useState<Record<string, boolean>>(
        {}
    );
    const addExport = useExportStore(s => s.add);
    const workspaceIds = useMemo(
        () => [...new Set(assets.map(a => a.workspace.id))],
        [assets]
    );

    const definitions = useQuery({
        queryKey: ['rendition-definitions', 'export', workspaceIds],
        queryFn: () => getRenditionDefinitions({workspaceIds}),
    });

    // Terms of the exported assets' workspaces, to sign before exporting
    const workspaces = useQueries({
        queries: workspaceIds.map(id => ({
            queryKey: ['workspace', id],
            queryFn: () => getWorkspace(id),
            // The signature status may have changed since cached
            staleTime: 0,
        })),
    });
    const workspacesLoading = workspaces.some(q => q.isLoading);
    const unsignedTerms = workspaces.flatMap(({data: w}) =>
        w?.terms && (w.terms.text || w.terms.pdfUrl) && w.terms.signed === false
            ? [{workspace: w, terms: w.terms}]
            : []
    );
    const allTermsAccepted = unsignedTerms.every(
        ({workspace}) => acceptedTerms[workspace.id]
    );

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
        if (unsignedTerms.length > 0) {
            await Promise.all(
                unsignedTerms.map(({workspace}) =>
                    signWorkspaceTerms(workspace.id)
                )
            );
            void useCollectionStore.getState().loadWorkspaces(true);
        }

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
            title={t('export.dialog.title', 'Export {{count}} assets', {
                count: assets.length,
            })}
            description={t(
                'export.dialog.help',
                'Select the renditions to include in the export.'
            )}
            submitLabel={t('export.dialog.submit', 'Export')}
            submitIcon={<DownloadIcon />}
            canSubmit={
                selected.length > 0 && !workspacesLoading && allTermsAccepted
            }
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
            {unsignedTerms.map(({workspace: w, terms}) => (
                <section
                    key={w.id}
                    className="space-y-2"
                    data-testid="export-terms"
                >
                    <h4 className="text-xs font-semibold text-muted-foreground uppercase">
                        {t(
                            'export.dialog.terms.title',
                            'Terms & Conditions — {{workspace}}',
                            {workspace: w.displayName ?? w.name}
                        )}
                    </h4>
                    <TermsContent terms={terms} />
                    <LabeledControl
                        label={t(
                            'export.dialog.terms.accept',
                            'I have read and accept the Terms & Conditions (version {{version}})',
                            {version: terms.version}
                        )}
                    >
                        <Checkbox
                            checked={acceptedTerms[w.id] ?? false}
                            onCheckedChange={v =>
                                setAcceptedTerms(prev => ({
                                    ...prev,
                                    [w.id]: v === true,
                                }))
                            }
                        />
                    </LabeledControl>
                </section>
            ))}
        </FormDialog>
    );
}
