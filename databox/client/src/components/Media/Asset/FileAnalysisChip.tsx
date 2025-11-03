import {Chip} from '@mui/material';
import React from 'react';
import {ApiFile} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import HourglassBottomIcon from '@mui/icons-material/HourglassBottom';
import ErrorIcon from '@mui/icons-material/Error';
import {modalRoutes} from '../../../routes.ts';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';

type Props = {
    file: ApiFile;
};

export type {Props as FileAnalysisChipProps};

export default function FileAnalysisChip({file}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    if (file.analysisPending) {
        return (
            <Chip
                label={t('file.analysis_pending', 'Analysis in progress…')}
                color="info"
                icon={<HourglassBottomIcon />}
            />
        );
    }

    if (!file.accepted) {
        return (
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
        );
    }
}
