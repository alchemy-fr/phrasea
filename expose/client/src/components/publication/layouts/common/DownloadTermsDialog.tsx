import {Trans} from 'react-i18next';
import React from 'react';
import {AppDialog} from '@alchemy/phrasea-ui';
import {TermsConfig} from '../../../../types.ts';
import {useTranslation} from 'react-i18next';
import {Box, Button, Typography} from '@mui/material';
import CheckIcon from '@mui/icons-material/Check';
import {StackedModalProps, useModals} from '@alchemy/navigation';

type Props = {
    terms: TermsConfig;
    onAccept: () => void;
} & StackedModalProps;

export default function DownloadTermsDialog({
    onAccept,
    terms: {text, url},
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    return (
        <AppDialog
            modalIndex={modalIndex}
            maxWidth={'sm'}
            open={open}
            onClose={closeModal}
            title={t(
                'download_terms.dialog.title',
                'Download Terms and Conditions'
            )}
            actions={({onClose}) => {
                return (
                    <>
                        <Button onClick={onClose}>
                            {t('download_terms.dialog.cancel', 'Cancel')}
                        </Button>
                        <Button
                            startIcon={<CheckIcon />}
                            variant={'contained'}
                            onClick={() => {
                                onClose();
                                onAccept();
                            }}
                        >
                            {t(
                                'download_terms.dialog.accept',
                                'Accept & Download'
                            )}
                        </Button>
                    </>
                );
            }}
        >
            <div>
                {text && (
                    <Typography
                        variant={'body1'}
                        dangerouslySetInnerHTML={{
                            __html: text,
                        }}
                    />
                )}
                {url && (
                    <Typography variant={'body1'} component={'div'}>
                        {!text ? (
                            <>
                                <Trans
                                    i18nKey={
                                        'download_terms.dialog.please_read_accept'
                                    }
                                    components={{
                                        link: (
                                            <a href={url} target={'_blank'} />
                                        ),
                                    }}
                                    defaults={`Please Read and Accept the Download <link>Terms</link>`}
                                />
                            </>
                        ) : (
                            <Box sx={{mt: 2}}>
                                <Button
                                    variant={'outlined'}
                                    href={url}
                                    target={'_blank'}
                                    rel={'noopener noreferrer'}
                                >
                                    {t(
                                        'download_terms.dialog.terms_cta',
                                        'View Download Terms'
                                    )}
                                </Button>
                            </Box>
                        )}
                    </Typography>
                )}
            </div>
        </AppDialog>
    );
}
