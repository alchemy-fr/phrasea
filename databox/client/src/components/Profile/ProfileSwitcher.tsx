import {useProfileStore} from '../../store/profileStore.ts';
import {Button, ButtonGroup} from '@mui/material';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import React from 'react';
import SelectProfileDialog from './SelectProfileDialog.tsx';

type Props = {};

export default function ProfileSwitcher({}: Props) {
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
        <ButtonGroup
            aria-label="split button"
            disableElevation={true}
            style={{
                verticalAlign: 'middle',
            }}
        >
            <Button
                aria-label="Select profile action"
                aria-haspopup="menu"
                onClick={openList}
                loading={!loaded}
                loadingPosition={'start'}
                endIcon={<ArrowDropDownIcon />}
            >
                {current?.title || t('profile.default.title', 'My Profile')}
            </Button>
        </ButtonGroup>
    );
}
