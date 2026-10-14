'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {Button} from '@/components/ui/button';
import {Checkbox, LabeledControl} from '@/components/ui/controls';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {InlineLoader} from '@/components/ui/loader';
import {getWorkspace, signWorkspaceTerms} from '@/lib/api/collections';
import {toastError} from '@/lib/utils/errors';
import {
    pagerKey,
    useCollectionStore,
} from '@/features/collections/collectionStore';
import {useOptionalSearch} from '@/features/search/SearchProvider';
import {TermsContent} from '@/features/share/ShareTerms';

/**
 * Presented on arrival: lists the workspaces whose Terms & Conditions the
 * current user has not signed yet, and asks to accept them one by one.
 * Access to a workspace content is denied server-side until its current
 * terms version is signed.
 */
export function WorkspaceTermsGate() {
    const workspaces = useCollectionStore(s => s.workspaces);
    const loadWorkspaces = useCollectionStore(s => s.loadWorkspaces);
    const loadChildren = useCollectionStore(s => s.loadChildren);
    const queryClient = useQueryClient();
    const search = useOptionalSearch();
    const [dismissed, setDismissed] = useState(false);

    useEffect(() => {
        void loadWorkspaces();
    }, [loadWorkspaces]);

    const unsigned = workspaces.filter(w => w.termsUnsigned);

    if (dismissed || unsigned.length === 0) {
        return null;
    }

    const onSigned = async (workspaceId: string) => {
        await loadWorkspaces(true);
        // The content denied so far is now readable: refresh what was loaded
        if (useCollectionStore.getState().pagers[pagerKey(workspaceId)]) {
            void loadChildren(workspaceId, undefined, true).catch(() => {});
        }
        void queryClient.invalidateQueries();
        search?.reload();
    };

    return (
        <WorkspaceTermsDialog
            key={unsigned[0].id}
            workspaceId={unsigned[0].id}
            remaining={unsigned.length}
            onSigned={onSigned}
            onDismiss={() => setDismissed(true)}
        />
    );
}

function WorkspaceTermsDialog({
    workspaceId,
    remaining,
    onSigned,
    onDismiss,
}: {
    workspaceId: string;
    remaining: number;
    onSigned: (workspaceId: string) => Promise<void>;
    onDismiss: () => void;
}) {
    const {t} = useTranslation();
    const [accepted, setAccepted] = useState(false);
    const [signing, setSigning] = useState(false);

    // The workspace list does not expose the terms themselves
    const {data: workspace} = useQuery({
        queryKey: ['workspace', workspaceId],
        queryFn: () => getWorkspace(workspaceId),
    });
    const terms = workspace?.terms;

    const sign = async () => {
        setSigning(true);
        try {
            await signWorkspaceTerms(workspaceId);
            await onSigned(workspaceId);
        } catch (e) {
            toastError(e);
        } finally {
            setSigning(false);
        }
    };

    return (
        <Dialog open>
            <DialogContent
                hideClose
                size="lg"
                data-testid="workspace-terms-dialog"
                onEscapeKeyDown={e => e.preventDefault()}
                onInteractOutside={e => e.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            'workspace.terms_gate.title',
                            'Terms & Conditions — {{workspace}}',
                            {
                                workspace:
                                    workspace?.displayName ??
                                    workspace?.name ??
                                    '',
                            }
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {terms
                            ? t(
                                  'workspace.terms_gate.intro',
                                  'You must accept the Terms & Conditions (version {{version}}) to access this workspace.',
                                  {version: terms.version}
                              )
                            : null}
                    </DialogDescription>
                </DialogHeader>
                {terms ? (
                    <>
                        <TermsContent terms={terms} />
                        <LabeledControl
                            label={t(
                                'workspace.terms_gate.accept_label',
                                'I have read and accept the Terms & Conditions'
                            )}
                        >
                            <Checkbox
                                checked={accepted}
                                disabled={signing}
                                onCheckedChange={v => setAccepted(v === true)}
                            />
                        </LabeledControl>
                    </>
                ) : (
                    <InlineLoader />
                )}
                <DialogFooter>
                    <Button
                        variant="ghost"
                        disabled={signing}
                        onClick={onDismiss}
                    >
                        {remaining > 1
                            ? t(
                                  'workspace.terms_gate.later_all',
                                  'Not now ({{count}} pending)',
                                  {count: remaining}
                              )
                            : t('workspace.terms_gate.later', 'Not now')}
                    </Button>
                    <Button
                        disabled={!terms || !accepted}
                        loading={signing}
                        onClick={sign}
                    >
                        {t('workspace.terms_gate.accept', 'Accept')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
