import {Chip} from '@mui/material';
import {ApiFile} from '../../../types.ts';
import {useTranslation} from 'react-i18next';
import HourglassBottomIcon from '@mui/icons-material/HourglassBottom';
import ErrorIcon from '@mui/icons-material/Error';
import WarningAmberIcon from '@mui/icons-material/WarningAmber';
import {modalRoutes} from '../../../routes.ts';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';
import {FileAnalysisState} from './Quarantine/analysisTypes.ts';
import {getFileAnalysisPresentation} from './Quarantine/analysisPresentation.ts';

type Props = {
    file: ApiFile;
};

export type {Props as FileAnalysisChipProps};

export default function FileAnalysisChip({file}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    const {severity, label, showChip} = getFileAnalysisPresentation(file, t);

    // Nominal states (analyzed, skipped, not applicable) stay silent so
    // thumbnails are not cluttered.
    if (!showChip) {
        return null;
    }

    const pending = FileAnalysisState.NotAnalyzed === file.analysisState;

    return (
        <Chip
            style={pending ? undefined : {cursor: 'pointer'}}
            onClick={
                pending
                    ? undefined
                    : () => {
                          navigateToModal(modalRoutes.files.routes.manage, {
                              tab: 'info',
                              id: file.id,
                          });
                      }
            }
            label={label}
            color={severity}
            icon={
                pending ? (
                    <HourglassBottomIcon />
                ) : 'error' === severity ? (
                    <ErrorIcon />
                ) : (
                    <WarningAmberIcon />
                )
            }
        />
    );
}
