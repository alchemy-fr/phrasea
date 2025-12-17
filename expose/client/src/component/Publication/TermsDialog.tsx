import {Trans} from 'react-i18next';
import React from 'react';
import {AppDialog} from '@alchemy/phrasea-ui';
import {TermsConfig} from '../../types.ts';
import {useTranslation} from 'react-i18next';
import {Button, Typography} from '@mui/material';

type Props = {
    accepted: boolean;
    terms: TermsConfig;
    onAccept: () => void;
};

export default function TermsDialog({
    accepted,
    onAccept,
    terms: {text, url},
}: Props) {
    const {t} = useTranslation();

    return (
        <AppDialog
            modalIndex={0}
            open={!accepted}
            onClose={() => {}}
            title={'Publication'}
            actions={({onClose}) => {
                return (
                    <>
                        <Button
                            onClick={() => {
                                onAccept();
                                onClose();
                            }}
                        >
                            {t('terms.accept', 'Accept')}
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
                    <Typography variant={'body1'}>
                        {!text ? (
                            <>
                                <Trans
                                    i18nKey={'terms.please_read_accept'}
                                    components={{
                                        link: (
                                            <a href={url} target={'_blank'} />
                                        ),
                                    }}
                                    defaults={`Please read and accept the <link>terms</link>`}
                                />
                            </>
                        ) : (
                            <a
                                href={url}
                                target={'_blank'}
                                rel={'noopener noreferrer'}
                            >
                                {t('terms.terms_cta', 'Terms')}
                            </a>
                        )}
                    </Typography>
                )}
            </div>
        </AppDialog>
    );
}
