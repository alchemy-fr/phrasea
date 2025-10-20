import InfoRow from '../../Dialog/Info/InfoRow.tsx';
import KeyIcon from '@mui/icons-material/Key';
import {Divider, MenuList} from '@mui/material';
import PersonIcon from '@mui/icons-material/Person';
import EventIcon from '@mui/icons-material/Event';
import BusinessIcon from '@mui/icons-material/Business';
import {modalRoutes} from '../../../routes.ts';
import FolderIcon from '@mui/icons-material/Folder';
import {useTranslation} from 'react-i18next';
import {Asset} from '../../../types.ts';
import {useNavigateToModal} from '../../Routing/ModalLink.tsx';
import InsertDriveFileIcon from '@mui/icons-material/InsertDriveFile';

type Props = {
    data: Asset;
};

export default function AssetInfoList({data}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    return (
        <>
            <MenuList>
                <InfoRow
                    label={t('asset.info.id', `ID`)}
                    value={data.id}
                    copyValue={data.id}
                    icon={<KeyIcon />}
                />
                <Divider />
                <InfoRow
                    label={t('asset.info.owner', `Owner`)}
                    value={data.owner?.username ?? data.owner?.id ?? '-'}
                    copyValue={data.owner?.id}
                    icon={<PersonIcon />}
                />
                <InfoRow
                    label={t('asset.info.created_at', `Created At`)}
                    value={data.createdAt}
                    copyValue={data.createdAt}
                    icon={<EventIcon />}
                />
                <InfoRow
                    label={t(
                        'asset.info.last_modification_date',
                        `Last Modification date`
                    )}
                    value={data.editedAt}
                    copyValue={data.editedAt}
                    icon={<EventIcon />}
                />
                <InfoRow
                    label={t(
                        'asset.info.last_attribute_modification_date',
                        `Last attribute modification date`
                    )}
                    value={data.attributesEditedAt}
                    copyValue={data.attributesEditedAt}
                    icon={<EventIcon />}
                />
                <InfoRow
                    icon={<BusinessIcon />}
                    label={t('asset.info.workspace', `Workspace`)}
                    value={data.workspace.nameTranslated}
                    copyValue={data.workspace.id}
                    onClick={() => {
                        navigateToModal(modalRoutes.workspaces.routes.manage, {
                            id: data.workspace.id,
                            tab: 'info',
                        });
                    }}
                />
                <InfoRow
                    icon={<FolderIcon />}
                    label={t('asset.info.collection', `Collection`)}
                    value={
                        data.referenceCollection?.absoluteTitleTranslated ??
                        t('asset.info.collections.none', 'None')
                    }
                    copyValue={data.referenceCollection?.id}
                    onClick={
                        data.referenceCollection
                            ? () => {
                                  navigateToModal(
                                      modalRoutes.collections.routes.manage,
                                      {
                                          id: data.referenceCollection!.id,
                                          tab: 'info',
                                      }
                                  );
                              }
                            : undefined
                    }
                />
                <InfoRow
                    icon={<InsertDriveFileIcon />}
                    label={t('asset.info.source_file', `Source File`)}
                    value={
                        data.source?.id ??
                        t('asset.info.source_file.none', 'None')
                    }
                    copyValue={data.source?.id}
                    onClick={
                        data.source
                            ? () => {
                                  navigateToModal(
                                      modalRoutes.files.routes.manage,
                                      {
                                          tab: 'metadata',
                                          id: data.source!.id,
                                      }
                                  );
                              }
                            : undefined
                    }
                />
            </MenuList>
        </>
    );
}
