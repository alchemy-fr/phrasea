'use client';

import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {FileImageIcon, PencilRulerIcon, Trash2Icon} from 'lucide-react';
import {toast} from 'sonner';
import type {ApiFile, Asset, WorkspaceIntegration} from '@/types/api';
import {getIntegrationData, runIntegrationAction} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {InlineLoader} from '@/components/ui/loader';
import {useModals} from '@/components/modals/ModalProvider';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {PhotoEditorDialog} from './PhotoEditorDialog';

/** Integration key of the Toast UI photo editor (`TuiPhotoEditorIntegration`) */
export const tuiPhotoEditor = 'tui.photo-editor';

type SavedFile = {id: string; url: string};

/**
 * Toast UI photo editor integration: edits the displayed file in a full
 * screen editor and saves the result as a new file, listing the previous
 * exports so they can be reopened or deleted.
 */
export function TuiPhotoEditorPanel({
    integration,
    asset,
    file,
}: {
    integration: WorkspaceIntegration;
    asset: Asset;
    file: ApiFile;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const {openModal} = useModals();
    const queryKey = ['integration-data', integration.id, file.id];

    const data = useQuery({
        queryKey,
        queryFn: ({signal}) =>
            getIntegrationData(integration.id, undefined, signal),
    });
    const reload = () => queryClient.invalidateQueries({queryKey});

    useChannelEvent(`file-${file.id}`, `integration:${tuiPhotoEditor}`, () =>
        reload()
    );

    const exports = (data.data?.items ?? []).filter(
        d => !d.object || (d.object as {id?: string}).id === file.id
    );
    const allowed = integration.capabilities?.interact !== false;

    const edit = (source: SavedFile, suggestedName?: string) =>
        openModal(
            PhotoEditorDialog,
            {
                asset,
                file,
                integration,
                source,
                suggestedName,
                onSaved: () => void reload(),
            },
            // Bound to the file: closing the viewer closes the editor
            {key: `photo-editor-${file.id}`}
        );

    const remove = async (id: string) => {
        try {
            await runIntegrationAction(integration.id, 'delete', {
                fileId: file.id,
                id,
            });
            void reload();
        } catch (e: any) {
            toast.error(e?.message);
        }
    };

    if (!allowed) {
        return (
            <p className="text-xs text-muted-foreground">
                {t('tui_photo_editor.not_allowed', 'You cannot edit this file')}
            </p>
        );
    }

    return (
        <div className="space-y-2 text-sm">
            <Button
                size="sm"
                variant="outline"
                className="w-full"
                disabled={!file.url}
                onClick={() => edit({id: file.id, url: file.url!})}
            >
                <PencilRulerIcon />{' '}
                {t('tui_photo_editor.open', 'Open photo editor')}
            </Button>
            {data.isLoading ? <InlineLoader /> : null}
            {exports.length > 0 ? (
                <ul className="space-y-1">
                    {exports.map(d => {
                        const value = d.value as SavedFile | undefined;

                        return (
                            <li
                                key={d.id}
                                className="flex items-center gap-2 rounded bg-muted/50 px-2 py-1 text-xs"
                            >
                                <FileImageIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <button
                                    type="button"
                                    className="min-w-0 flex-1 truncate text-left hover:underline"
                                    disabled={!value?.url}
                                    onClick={() =>
                                        value?.url
                                            ? edit(value, d.keyId ?? '')
                                            : undefined
                                    }
                                    title={t(
                                        'tui_photo_editor.reopen',
                                        'Open in the editor'
                                    )}
                                >
                                    {d.keyId || d.name}
                                </button>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={() => remove(d.id)}
                                    aria-label={t('common.delete', 'Delete')}
                                >
                                    <Trash2Icon />
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            ) : !data.isLoading ? (
                <p className="text-xs text-muted-foreground">
                    {t('tui_photo_editor.no_export', 'No edited version yet')}
                </p>
            ) : null}
        </div>
    );
}
