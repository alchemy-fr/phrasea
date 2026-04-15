import {useProfileStore} from '../../store/profileStore.ts';
import {ListItemText, MenuItem} from '@mui/material';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import React from 'react';
import SelectProfileDialog from './SelectProfileDialog.tsx';
import {CloseWrapper} from '@alchemy/phrasea-ui';
import {ListItemLoadingIcon} from '@alchemy/phrasea-framework';
import AccountBoxIcon from '@mui/icons-material/AccountBox';

type Props = {
    closeWrapper: CloseWrapper;
};

export default function ProfileSwitcher({closeWrapper}: Props) {
    const {t} = useTranslation();
    const load = useProfileStore(state => state.load);
    const current = useProfileStore(state => state.current);
    const loaded = useProfileStore(state => state.loaded);
    const {openModal} = useModals();

    React.useEffect(() => {
        load();
    }, [load]);

    const openList = () => {
        openModal(SelectProfileDialog, {});
    };

    return (
        <MenuItem
            aria-label="Select profile action"
            aria-haspopup="menu"
            onClick={closeWrapper(openList)}
        >
            <ListItemLoadingIcon loading={!loaded}>
                <AccountBoxIcon />
            </ListItemLoadingIcon>
            <ListItemText>
                {current?.title ||
                    t('profile.default.title', 'Default Profile')}
            </ListItemText>
        </MenuItem>
    );
}
