import {useAttributeListStore} from '../../store/attributeListStore';
import {ButtonGroup} from '@mui/material';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import {LoadingButton} from '@alchemy/react-form';
import React from 'react';
import AttributeListsDialog from "./AttributeListsDialog.tsx";

type Props = {};

export default function AttributeListSwitcher({}: Props) {
    const {t} = useTranslation();
    const current = useAttributeListStore(state => state.current);
    const loadingCurrent = useAttributeListStore(state => state.loadingCurrent);
    const {openModal} = useModals();

    const openList = () => {
        openModal(AttributeListsDialog, {});
    };

    return (
        <ButtonGroup
            aria-label="split button"
            disableElevation={true}
            style={{
                verticalAlign: 'middle',
            }}
        >
            <LoadingButton
                aria-label="Select attributeList action"
                aria-haspopup="menu"
                onClick={openList}
                loading={loadingCurrent}
                loadingPosition={'start'}
                endIcon={<ArrowDropDownIcon/>}
            >
                {current?.title || t('attributeList.default.title', 'My Attribute List')}
            </LoadingButton>
        </ButtonGroup>
    );
}
