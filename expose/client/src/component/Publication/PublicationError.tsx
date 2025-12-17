import React from 'react';
import ErrorPage from '../ErrorPage.tsx';
import {useTranslation} from 'react-i18next';

type Props = {
    errorCode: number;
};

export default function PublicationError({errorCode}: Props) {
    const {t} = useTranslation();

    switch (errorCode) {
        case 401:
            return (
                <ErrorPage
                    title={t(
                        'error.authentication_required',
                        'Authentication required'
                    )}
                />
            );
        case 403:
            return <ErrorPage title={t('error.forbidden', 'Forbidden')} />;
        case 404:
            return <ErrorPage title={t('error.not_found', 'Not found')} />;
        default:
            return (
                <ErrorPage title={t('error.unexpected', 'Unexpected error')} />
            );
    }
}
