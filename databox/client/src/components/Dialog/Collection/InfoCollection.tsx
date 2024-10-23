import {Collection} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {Divider, MenuList} from '@mui/material';
import KeyIcon from '@mui/icons-material/Key';
import EventIcon from '@mui/icons-material/Event';
import InfoRow from '../Info/InfoRow';
import PersonIcon from '@mui/icons-material/Person';
import {useTranslation} from 'react-i18next';
import FolderIcon from '@mui/icons-material/Folder';
import BusinessIcon from '@mui/icons-material/Business';
import {useNavigateToModal} from "../../Routing/ModalLink.tsx";
import {modalRoutes} from "../../../routes.ts";

type Props = {
    id: string;
    data: Collection;
} & DialogTabProps;

export default function InfoCollection({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <MenuList>
                <InfoRow
                    label={t('collection.info.id', `ID`)}
                    value={data.id}
                    copyValue={data.id}
                    icon={<KeyIcon />}
                />
                <Divider />
                <InfoRow
                    label={t('collection.info.owner', `Owner`)}
                    value={data.owner?.username ?? data.owner?.id ?? '-'}
                    copyValue={data.owner?.id}
                    icon={<PersonIcon />}
                />
                <InfoRow
                    label={t('collection.info.creation_date', `Creation date`)}
                    value={data.createdAt}
                    icon={<EventIcon />}
                />
                <InfoRow
                    label={t(
                        'collection.info.modification_date',
                        `Modification date`
                    )}
                    value={data.updatedAt}
                    icon={<EventIcon />}
                />
                <InfoRow
                    icon={<BusinessIcon />}
                    label={t('collection.info.workspace', `Workspace`)}
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
                    label={t('collection.info.absolute_path', `Absolute Path`)}
                    value={data.absoluteTitle}
                />
            </MenuList>
        </ContentTab>
    );
}
