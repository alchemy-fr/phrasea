import {SavedSearch} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {useTranslation} from 'react-i18next';
import {Alert} from '@mui/material';

type Props = {
    data: SavedSearch;
} & DialogTabProps;

export default function Automations({onClose, minHeight}: Props) {
    const {t} = useTranslation();

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <Alert severity={'info'}>
                {t(
                    'saved_search.automations.coming_soon',
                    'Automations are coming soon!'
                )}
            </Alert>
        </ContentTab>
    );
}
