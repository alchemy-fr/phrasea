import {Asset} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {Divider, MenuList} from '@mui/material';
import KeyIcon from '@mui/icons-material/Key';
import EventIcon from '@mui/icons-material/Event';
import PersonIcon from '@mui/icons-material/Person';
import InfoRow from '../Info/InfoRow';
import {useTranslation} from 'react-i18next';
import BusinessIcon from '@mui/icons-material/Business';
import FolderIcon from '@mui/icons-material/Folder';
import {useNavigateToModal} from "../../Routing/ModalLink.tsx";
import {modalRoutes} from "../../../routes.ts";

type Props = {
    data: Asset;
} & DialogTabProps;

export default function InfoAsset({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
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
                    label={t('asset.info.date_added', `Date Added`)}
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
                    value={data.workspace.name}
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
                        data.referenceCollection?.absoluteTitle ??
                        t('asset.info.collection.none', 'None')
                    }
                    copyValue={data.referenceCollection?.id}
                    onClick={data.referenceCollection ? () => {
                        navigateToModal(modalRoutes.collections.routes.manage, {
                            id: data.referenceCollection!.id,
                            tab: 'info',
                        });
                    } : undefined}
                />
            </MenuList>
        </ContentTab>
    );
}
