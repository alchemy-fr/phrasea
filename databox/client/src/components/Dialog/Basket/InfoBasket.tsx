import {Basket} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {Divider, MenuList} from '@mui/material';
import KeyIcon from '@mui/icons-material/Key';
import EventIcon from '@mui/icons-material/Event';
import InfoRow from '../Info/InfoRow';
import PersonIcon from '@mui/icons-material/Person';
import { useTranslation } from 'react-i18next';

type Props = {
    id: string;
    data: Basket;
} & DialogTabProps;

export default function InfoBasket({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <MenuList>
                <InfoRow
                    label={t('basket.info.id', `ID`)}
                    value={data.id}
                    copyValue={data.id}
                    icon={<KeyIcon />}
                />
                <Divider />
                <InfoRow
                    label={t('basket.info.owner', `Owner`)}
                    value={data.owner?.username ?? data.owner?.id ?? '-'}
                    copyValue={data.owner?.id}
                    icon={<PersonIcon />}
                />
                <InfoRow
                    label={t('basket.info.creation_date', `Creation date`)}
                    value={data.createdAt}
                    icon={<EventIcon />}
                />
                <InfoRow
                    label={t('basket.info.modification_date', `Modification date`)}
                    value={data.updatedAt}
                    icon={<EventIcon />}
                />
            </MenuList>
        </ContentTab>
    );
}
