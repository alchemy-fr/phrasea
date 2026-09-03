import React from 'react';
import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {
    Box,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    FormControlLabel,
    Typography,
} from '@mui/material';
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import {useAuth} from '@alchemy/react-auth';
import {
    getWorkspace,
    getWorkspaces,
    signWorkspaceTerms,
} from '../../api/workspace.ts';
import {queryClient} from '../../lib/query.ts';

/**
 * Presented on arrival: lists the workspaces whose Terms & Conditions
 * the current user has not signed yet, and asks to accept them one by one.
 * Access to a workspace content is denied server-side until its current
 * terms version is signed.
 */
export default function WorkspaceTermsGate() {
    const {isAuthenticated} = useAuth();
    const [dismissed, setDismissed] = React.useState(false);

    const {data, refetch} = useQuery({
        queryKey: ['workspaces', 'terms-gate'],
        queryFn: () => getWorkspaces({}).then(r => r.result),
        enabled: isAuthenticated,
    });

    const unsigned = (data ?? []).filter(w => w.termsUnsigned);

    if (dismissed || unsigned.length === 0) {
        return null;
    }

    return (
        <WorkspaceTermsDialog
            key={unsigned[0].id}
            workspaceId={unsigned[0].id}
            remaining={unsigned.length}
            onSigned={async () => {
                const r = await refetch();
                if (!(r.data ?? []).some(w => w.termsUnsigned)) {
                    // Everything is signed: refresh what was previously denied
                    queryClient.invalidateQueries();
                }
            }}
            onDismiss={() => setDismissed(true)}
        />
    );
}

type DialogProps = {
    workspaceId: string;
    remaining: number;
    onSigned: () => Promise<void>;
    onDismiss: () => void;
};

function WorkspaceTermsDialog({
    workspaceId,
    remaining,
    onSigned,
    onDismiss,
}: DialogProps) {
    const {t} = useTranslation();
    const [accepted, setAccepted] = React.useState(false);
    const [signing, setSigning] = React.useState(false);

    const {data} = useQuery({
        queryKey: ['workspace', workspaceId],
        queryFn: () => getWorkspace(workspaceId),
    });

    if (!data) {
        return null;
    }

    const terms = data.terms;
    if (!terms) {
        return null;
    }

    const sign = async () => {
        setSigning(true);
        try {
            await signWorkspaceTerms(workspaceId);
            await onSigned();
        } finally {
            setSigning(false);
        }
    };

    return (
        <Dialog open maxWidth={'md'} fullWidth>
            <DialogTitle>
                {t(
                    'workspace.terms_gate.title',
                    'Terms & Conditions — {{workspace}}',
                    {
                        workspace: data.name,
                    }
                )}
            </DialogTitle>
            <DialogContent>
                <Typography
                    variant={'body2'}
                    sx={{color: 'text.secondary', mb: 2}}
                >
                    {t(
                        'workspace.terms_gate.intro',
                        'You must accept the Terms & Conditions (version {{version}}) to access this workspace.',
                        {
                            version: terms.version,
                        }
                    )}
                </Typography>
                {terms.pdfUrl ? (
                    <Button
                        variant={'outlined'}
                        href={terms.pdfUrl}
                        target={'_blank'}
                        rel={'noreferrer'}
                        startIcon={<PictureAsPdfIcon />}
                    >
                        {t(
                            'workspace.terms_gate.view_pdf',
                            'Read the Terms & Conditions (PDF)'
                        )}
                    </Button>
                ) : (
                    <Box
                        sx={theme => ({
                            whiteSpace: 'pre-wrap',
                            maxHeight: 350,
                            overflow: 'auto',
                            border: `1px solid ${theme.palette.divider}`,
                            borderRadius: 1,
                            p: 2,
                        })}
                    >
                        {terms.text}
                    </Box>
                )}
                <FormControlLabel
                    sx={{mt: 2}}
                    control={
                        <Checkbox
                            checked={accepted}
                            disabled={signing}
                            onChange={(_e, checked) => setAccepted(checked)}
                        />
                    }
                    label={t(
                        'workspace.terms_gate.accept_label',
                        'I have read and accept the Terms & Conditions'
                    )}
                />
            </DialogContent>
            <DialogActions>
                <Button
                    color={'warning'}
                    onClick={onDismiss}
                    disabled={signing}
                >
                    {remaining > 1
                        ? t(
                              'workspace.terms_gate.later_all',
                              'Not now ({{count}} pending)',
                              {
                                  count: remaining,
                              }
                          )
                        : t('workspace.terms_gate.later', 'Not now')}
                </Button>
                <Button
                    variant={'contained'}
                    onClick={sign}
                    disabled={!accepted || signing}
                    loading={signing}
                >
                    {t('workspace.terms_gate.accept', 'Accept')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
