import {Box, CircularProgress, Typography} from '@mui/material';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {getFileDuplicates} from '../../../../api/file.ts';
import {modalRoutes, Routing} from '../../../../routes.ts';
import {useNavigateToModal} from '../../../Routing/ModalLink.tsx';
import {AnalyzerName} from './analysisTypes.ts';
import DuplicateAssetRow from './DuplicateAssetRow.tsx';
import DisplayProvider from '../../DisplayProvider.tsx';
import {usePreview} from '../../../AssetList/usePreview.ts';
import PreviewPopover from '../../../AssetList/PreviewPopover.tsx';
import {ZIndex} from '../../../../themes/zIndex.ts';

type Props = {
    fileId: string;
    analyzerName?: AnalyzerName;
};

/**
 * Resolves the duplicates of the analyzed file into assets and lists them
 * with their thumbnail. Shared by the checksum and doc_unique_id analyzers.
 */
export default function DuplicateAssets(props: Props) {
    return (
        <DisplayProvider>
            <DuplicateAssetList {...props} />
        </DisplayProvider>
    );
}

function DuplicateAssetList({fileId, analyzerName}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    const {
        data: duplicates,
        isSuccess,
        isError,
    } = useQuery({
        queryKey: ['files', fileId, 'duplicates'],
        queryFn: () => getFileDuplicates(fileId),
        retry: false,
        staleTime: 2000,
    });

    const {previewAnchorEl, onPreviewToggle, onPreviewHide} = usePreview([
        duplicates,
    ]);

    if (isError) {
        return null;
    }
    if (!isSuccess) {
        return (
            <Box sx={{mt: 1}}>
                <CircularProgress size={16} />
            </Box>
        );
    }

    const assets = analyzerName
        ? duplicates.filter(d => d.analyzers.includes(analyzerName))
        : duplicates;

    if (assets.length === 0) {
        return (
            <Typography variant={'body2'} color={'text.secondary'} sx={{mt: 1}}>
                {t(
                    'quarantine.duplicates.unavailable',
                    'The duplicate assets are no longer available or not accessible.'
                )}
            </Typography>
        );
    }

    return (
        <Box sx={{mt: 1}}>
            <Typography
                variant={'subtitle2'}
                sx={{display: 'flex', alignItems: 'center', gap: 0.5, mb: 1}}
            >
                <ContentCopyIcon fontSize={'small'} />
                {t('quarantine.duplicates.title', {
                    defaultValue: '{{count}} duplicate asset(s) found',
                    count: assets.length,
                })}
            </Typography>
            {assets.map(({asset}) => (
                <DuplicateAssetRow
                    key={asset.id}
                    asset={asset}
                    subtitle={
                        asset.createdAt
                            ? new Date(asset.createdAt).toLocaleString()
                            : undefined
                    }
                    onClick={() => {
                        navigateToModal(modalRoutes.assets.routes.view, {
                            id: asset.id,
                            renditionId: Routing.UnknownRendition,
                        });
                    }}
                    onPreviewToggle={onPreviewToggle}
                />
            ))}
            <PreviewPopover
                onHide={onPreviewHide}
                key={previewAnchorEl?.asset.id ?? 'none'}
                asset={previewAnchorEl?.asset}
                anchorEl={previewAnchorEl?.anchorEl}
                displayAttributes={true}
                zIndex={ZIndex.modal + 1}
            />
        </Box>
    );
}
