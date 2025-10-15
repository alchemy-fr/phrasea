import {useAssetActions} from '../../../../hooks/useAssetActions.ts';
import {Asset, ApiFile} from '../../../../types.ts';
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
import FollowButton from '../../../Ui/FollowButton.tsx';

type Props = {
    asset: Asset;
    file: ApiFile | undefined;
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
                    'flexShrink': 0,
                    'zIndex': 1,
                    'position': 'relative',
                    'ml': 2,
                    'display': 'flex',
                    'flexDirection': 'row',
                    '> * + *': {
                        ml: 1,
                    },
                }}
            >
                {asset.topicSubscriptions ? (
                    <FollowButton
                        entity={'assets'}
                        id={asset.id}
                        subscriptions={asset.topicSubscriptions}
                        topics={[
                            {
                                key: `asset:${asset.id}:update`,
                                label: t(
                                    'notification.topics.asset.update.label',
                                    'Update'
                                ),
                                description: t(
                                    'notification.topics.asset.update.desc',
                                    'Get notified when the asset is updated'
                                ),
                            },
                            {
                                key: `asset:${asset.id}:delete`,
                                label: t(
                                    'notification.topics.asset.delete.label',
                                    'Delete'
                                ),
                                description: t(
                                    'notification.topics.asset.delete.desc',
                                    'Get notified when the asset is deleted'
                                ),
                            },
                            {
                                key: `asset:${asset.id}:new_comment`,
                                label: t(
                                    'notification.topics.asset.new_comment.label',
                                    'Discussion'
                                ),
                                description: t(
                                    'notification.topics.asset.new_comment.desc',
                                    'Get notified when there is a new comment on the asset'
                                ),
                            },
                        ]}
                    />
                ) : null}
                {can.download ? (
                    <div>
                        <Button
                            variant={'contained'}
                            onClick={onDownload}
                            startIcon={<FileDownloadIcon />}
                        >
                            {t('asset_actions.download', 'Download')}
                        </Button>
                    </div>
                ) : (
                    ''
                )}
                {can.edit ? (
                    <div>
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
                    </div>
                ) : (
                    ''
                )}
                {file && can.edit ? (
                    <div>
                        <SaveAsButton
                            asset={asset}
                            file={file}
                            componentProps={{
                                variant: 'contained',
                            }}
                        />
                    </div>
                ) : (
                    ''
                )}
                {can.share ? (
                    <div>
                        <Button
                            variant={'contained'}
                            onClick={() => onShare()}
                            startIcon={<ShareIcon />}
                        >
                            {t('asset_actions.share', 'Share')}
                        </Button>
                    </div>
                ) : (
                    ''
                )}

                {can.delete ? (
                    <div>
                        <Button
                            color={'error'}
                            onClick={onDelete}
                            variant={'contained'}
                            startIcon={<DeleteForeverIcon />}
                        >
                            {t('asset_actions.delete', 'Delete')}
                        </Button>
                    </div>
                ) : (
                    ''
                )}
            </Box>
        </>
    );
}
