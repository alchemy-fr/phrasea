import {useProfileStore} from '../../store/profileStore.ts';
import {ListItemText, MenuItem} from '@mui/material';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import React from 'react';
import SelectDisplayProfileDialog from './SelectDisplayProfileDialog.tsx';
import {CloseWrapper} from '@alchemy/phrasea-ui';
import {ListItemLoadingIcon} from '@alchemy/phrasea-framework';
import AccountBoxIcon from '@mui/icons-material/AccountBox';

type Props = {
    closeWrapper: CloseWrapper;
};

export default function DisplayProfileSwitcher({closeWrapper}: Props) {
    const {t} = useTranslation();
    const current = useProfileStore(state => state.current);
    const currentLoaded = useProfileStore(state => state.currentLoaded);
    const {openModal} = useModals();

    const openList = () => {
        openModal(SelectDisplayProfileDialog, {});
    };

    return (
        <MenuItem
            aria-label="Select Display Profile action"
            aria-haspopup="menu"
            onClick={closeWrapper(openList)}
        >
            <ListItemLoadingIcon loading={!!current && !currentLoaded}>
                <AccountBoxIcon />
            </ListItemLoadingIcon>
            <ListItemText>
                {current?.name ||
                    t(
                        'display_profile.default.title',
                        'Default Display Profile'
                    )}
            </ListItemText>
        </MenuItem>
    );
}
