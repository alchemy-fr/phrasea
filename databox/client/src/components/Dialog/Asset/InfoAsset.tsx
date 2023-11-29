import {Asset} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {Divider, MenuList} from '@mui/material';
import KeyIcon from '@mui/icons-material/Key';
import EventIcon from '@mui/icons-material/Event';
import PersonIcon from '@mui/icons-material/Person';
import InfoRow from '../Info/InfoRow';

type Props = {
    data: Asset;
} & DialogTabProps;

export default function InfoAsset({data, onClose, minHeight}: Props) {
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <MenuList>
                <InfoRow
                    label={'ID'}
                    value={data.id}
                    copyValue={data.id}
                    icon={<KeyIcon />}
                />
                <Divider />
                <InfoRow
                    label={'Owner'}
                    value={data.owner?.username ?? data.owner?.id ?? '-'}
                    copyValue={data.owner?.id}
                    icon={<PersonIcon />}
                />
                <InfoRow
                    label={'Date Added'}
                    value={data.createdAt}
                    copyValue={data.createdAt}
                    icon={<EventIcon />}
                />
                <InfoRow
                    label={'Last Modification date'}
                    value={data.editedAt}
                    copyValue={data.editedAt}
                    icon={<EventIcon />}
                />
                <InfoRow
                    label={'Last attribute modification date'}
                    value={data.attributesEditedAt}
                    copyValue={data.attributesEditedAt}
                    icon={<EventIcon />}
                />
            </MenuList>
        </ContentTab>
    );
}
