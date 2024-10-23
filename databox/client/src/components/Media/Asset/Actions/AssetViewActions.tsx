import {useAssetActions} from '../../../../hooks/useAssetActions.ts';
import {Asset, File} from '../../../../types.ts';
import {Box, Button} from '@mui/material';
import GroupButton from '../../../Ui/GroupButton.tsx';
import EditIcon from '@mui/icons-material/Edit';
import TextSnippetIcon from '@mui/icons-material/TextSnippet';
import {useTranslation} from 'react-i18next';
import ShareIcon from '@mui/icons-material/Share';
import FileDownloadIcon from '@mui/icons-material/FileDownload';
import DeleteForeverIcon from '@mui/icons-material/DeleteForever';
import {useCloseModal} from '../../../Routing/ModalLink.tsx';
import SaveAsButton from './SaveAsButton.tsx';

type Props = {
    asset: Asset;
    file: File | undefined;
};

export default function AssetViewActions({asset, file}: Props) {
    const {t} = useTranslation();
    const closeModal = useCloseModal();
    const {
        onDelete,
        onDownload,
        onEdit,
        onEditAttr,
        onShare,
        onSubstituteFile,
        can,
    } = useAssetActions({asset, onDelete: closeModal});

    return (
        <>
            <Box
                sx={{
                    'zIndex': 1,
                    'position': 'relative',
                    'display': 'inline-block',
                    'ml': 2,
                    '> * + *': {
                        ml: 1,
                    },
                }}
            >
                {can.download ? (
                    <Button
                        variant={'contained'}
                        onClick={onDownload}
                        startIcon={<FileDownloadIcon />}
                    >
                        {t('asset_actions.download', 'Download')}
                    </Button>
                ) : (
                    ''
                )}
                {can.edit ? (
                    <GroupButton
                        id={'edit'}
                        onClick={onEdit}
                        startIcon={<EditIcon />}
                        actions={[
                            {
                                id: 'edit_attrs',
                                label: t(
                                    'asset_actions.edit_attributes',
                                    'Edit attributes'
                                ),
                                onClick: onEditAttr,
                                disabled: !can.editAttributes,
                                startIcon: <TextSnippetIcon />,
                            },
                            {
                                id: 'substitute',
                                label: t(
                                    'asset_actions.substitute_file',
                                    'Substitute File'
                                ),
                                onClick: onSubstituteFile,
                                disabled: !can.substitute,
                                startIcon: <TextSnippetIcon />,
                            },
                        ]}
                    >
                        {t('asset_actions.edit', 'Edit')}
                    </GroupButton>
                ) : (
                    ''
                )}
                {file && can.edit ? (
                    <SaveAsButton
                        asset={asset}
                        file={file}
                        componentProps={{
                            variant: 'contained',
                        }}
                    />
                ) : (
                    ''
                )}
                {can.share ? (
                    <Button
                        variant={'contained'}
                        onClick={() => onShare()}
                        startIcon={<ShareIcon />}
                    >
                        {t('asset_actions.share', 'Share')}
                    </Button>
                ) : (
                    ''
                )}

                {can.delete ? (
                    <Button
                        color={'error'}
                        onClick={onDelete}
                        variant={'contained'}
                        startIcon={<DeleteForeverIcon />}
                    >
                        {t('asset_actions.delete', 'Delete')}
                    </Button>
                ) : (
                    ''
                )}
            </Box>
        </>
    );
}
