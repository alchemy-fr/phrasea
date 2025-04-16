import {Workspace} from '../../../types';
import {DataTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {Divider, MenuList} from '@mui/material';
import KeyIcon from '@mui/icons-material/Key';
import EventIcon from '@mui/icons-material/Event';
import InfoRow from '../Info/InfoRow';
import {useTranslation} from 'react-i18next';
import PersonIcon from "@mui/icons-material/Person";

type Props = DataTabProps<Workspace>;

export default function InfoWorkspace({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <MenuList>
                <InfoRow
                    label={t('workspace.info.id', `ID`)}
                    value={data.id}
                    copyValue={data.id}
                    icon={<KeyIcon />}
                />
                <Divider />
                <InfoRow
                    label={t('workspace.info.owner', `Owner`)}
                    value={data.owner?.username ?? data.owner?.id ?? '-'}
                    copyValue={data.owner?.id}
                    icon={<PersonIcon />}
                />
                <InfoRow
                    label={t('workspace.info.creation_date', `Creation date`)}
                    value={data.createdAt}
                    copyValue={data.createdAt}
                    icon={<EventIcon />}
                />
            </MenuList>
        </ContentTab>
    );
}
