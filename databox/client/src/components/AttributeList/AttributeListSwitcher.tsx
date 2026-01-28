import {useAttributeListStore} from '../../store/attributeListStore';
import {Button, ButtonGroup} from '@mui/material';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import React from 'react';
import SelectAttributeListDialog from './SelectAttributeListDialog.tsx';

type Props = {};

export default function AttributeListSwitcher({}: Props) {
    const {t} = useTranslation();
    const load = useAttributeListStore(state => state.load);
    const current = useAttributeListStore(state => state.current);
    const loaded = useAttributeListStore(state => state.loaded);
    const {openModal} = useModals();

    React.useEffect(() => {
        load();
    }, [load]);

    const openList = () => {
        openModal(SelectAttributeListDialog, {});
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
                aria-label="Select attributeList action"
                aria-haspopup="menu"
                onClick={openList}
                loading={!loaded}
                loadingPosition={'start'}
                endIcon={<ArrowDropDownIcon />}
            >
                {current?.title ||
                    t('attributeList.default.title', 'My Attribute List')}
            </Button>
        </ButtonGroup>
    );
}
