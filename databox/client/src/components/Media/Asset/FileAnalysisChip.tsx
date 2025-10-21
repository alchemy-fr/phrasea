import {Chip} from '@mui/material';
import React, {PropsWithChildren} from 'react';
import {ApiFile} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import HourglassBottomIcon from '@mui/icons-material/HourglassBottom';
import ErrorIcon from '@mui/icons-material/Error';
import {modalRoutes} from '../../../routes.ts';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';

type Props = PropsWithChildren<{
    file: ApiFile;
}>;

export default function FileAnalysisChip({file, children}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    return (
        <div
            style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                flexDirection: 'column',
                gap: '8px',
            }}
        >
            {file.analysisPending ? (
                <Chip
                    label={t('file.analysis_pending', 'Analysis in progress…')}
                    color="info"
                    icon={<HourglassBottomIcon />}
                />
            ) : (
                <Chip
                    style={{
                        cursor: 'pointer',
                    }}
                    onClick={() => {
                        navigateToModal(modalRoutes.files.routes.manage, {
                            tab: 'info',
                            id: file!.id,
                        });
                    }}
                    label={t('file.rejected', 'Rejected')}
                    color="error"
                    icon={<ErrorIcon />}
                />
            )}

            {children}
        </div>
    );
}
