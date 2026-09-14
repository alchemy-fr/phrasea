import React from 'react';
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
import {useTranslation} from 'react-i18next';
import {ShareTerms} from '../../types.ts';
import {storeAcceptedShareTerms} from './shareTermsStorage.ts';

type Props = {
    shareId: string;
    terms: ShareTerms;
    onAccepted: () => void;
};

/**
 * Blocking dialog shown on the public share page until the visitor
 * accepts the workspace Terms & Conditions.
 */
export default function ShareTermsDialog({shareId, terms, onAccepted}: Props) {
    const {t} = useTranslation();
    const [accepted, setAccepted] = React.useState(false);

    const accept = () => {
        storeAcceptedShareTerms(shareId, terms);
        onAccepted();
    };

    return (
        <Dialog open maxWidth={'md'} fullWidth>
            <DialogTitle>
                {t('share.terms.title', 'Terms & Conditions')}
            </DialogTitle>
            <DialogContent>
                <Typography
                    variant={'body2'}
                    sx={{color: 'text.secondary', mb: 2}}
                >
                    {t(
                        'share.terms.dialog_intro',
                        'You must accept the Terms & Conditions of {{workspace}} (version {{version}}) to access this content.',
                        {
                            workspace: terms.workspaceName,
                            version: terms.version,
                        }
                    )}
                </Typography>
                {terms.pdfUrl ? (
                    <Box>
                        <Button
                            variant={'outlined'}
                            href={terms.pdfUrl}
                            target={'_blank'}
                            rel={'noreferrer'}
                            startIcon={<PictureAsPdfIcon />}
                        >
                            {t(
                                'share.terms.view_pdf',
                                'View Terms & Conditions (PDF)'
                            )}
                        </Button>
                    </Box>
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
                            onChange={(_e, checked) => setAccepted(checked)}
                        />
                    }
                    label={t(
                        'share.terms.accept_label',
                        'I have read and accept the Terms & Conditions'
                    )}
                />
            </DialogContent>
            <DialogActions>
                <Button
                    variant={'contained'}
                    onClick={accept}
                    disabled={!accepted}
                >
                    {t('share.terms.accept', 'Accept')}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
