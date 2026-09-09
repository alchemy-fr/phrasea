'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {DownloadIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {Asset, RenditionDefinition, Workspace} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {InlineLoader} from '@/components/ui/loader';
import {exportAssets, getRenditionDefinitions} from '@/lib/api/misc';
import {useExportStore} from '@/features/assets/exportStore';
import {downloadUrl} from '@/lib/utils/misc';

export function ExportDialog({
    open,
    onOpenChange,
    assets,
}: ModalProps & {assets: Asset[]}) {
    const {t} = useTranslation();
    const [selected, setSelected] = useState<string[]>([]);
    const [loading, setLoading] = useState(false);
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
        setLoading(true);
        try {
            const exp = await exportAssets({
                assets: assets.map(a => a.id),
                renditions: selected,
            });
            addExport(exp);
            if (exp.downloadUrl) {
                downloadUrl(exp.downloadUrl);
            }
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {t('export.dialog.title', 'Export {{count}} asset(s)', {
                            count: assets.length,
                        })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'export.dialog.help',
                            'Select the renditions to include in the export.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody className="space-y-4">
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
                                                        : prev.filter(
                                                              x => x !== d.id
                                                          )
                                                )
                                            }
                                        />
                                    </LabeledControl>
                                ))}
                            </div>
                        </div>
                    ))}
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={selected.length === 0}
                        loading={loading}
                    >
                        <DownloadIcon /> {t('export.dialog.submit', 'Export')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
