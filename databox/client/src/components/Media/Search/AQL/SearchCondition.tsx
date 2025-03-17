import {AQLQuery} from "./query.ts";
import {Chip, Menu, MenuItem} from "@mui/material";
import {useModals} from "@alchemy/navigation";
import SearchConditionDialog from "./SearchConditionDialog.tsx";
import React, {useContext, useRef} from "react";
import {SearchContext} from "../SearchContext.tsx";
import MoreVertIcon from '@mui/icons-material/MoreVert';
import {useTranslation} from 'react-i18next';

type Props = {
    condition: AQLQuery;
    onDelete: (condition: AQLQuery) => void;
    onUpsert: (condition: AQLQuery) => void;
};

export default function SearchCondition({
    condition,
    onDelete,
    onUpsert,
}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const open = Boolean(anchorEl);
    const onContextMenu = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleClose = () => {
        setAnchorEl(null);
    };

    const edit = () => {
        openModal(SearchConditionDialog, {
            condition,
            onUpsert,
        });
    }

    return <>
        <Chip
            sx={{
                mr: 1,
                color: condition.disabled ? 'warning.contrastText' : 'primary.contrastText',
                bgcolor: condition.disabled ? 'warning.main' : 'primary.main',
                fontFamily: 'Courier New'
            }}
            label={condition.query}
            onClick={edit}
            onDelete={onContextMenu}
            onContextMenu={onContextMenu}
            deleteIcon={<MoreVertIcon />}
        />
        <Menu
            id="demo-positioned-menu"
            aria-labelledby="demo-positioned-button"
            anchorEl={anchorEl}
            open={open}
            onClose={handleClose}
            anchorOrigin={{
                vertical: 'top',
                horizontal: 'left',
            }}
            transformOrigin={{
                vertical: 'top',
                horizontal: 'left',
            }}
        >
            <MenuItem onClick={() => {
                onUpsert({
                    ...condition,
                    disabled: !condition.disabled,
                });
                handleClose();
            }}>
                {condition.disabled ? t('search_condition.menu.enable', 'Enable') : t('search_condition.menu.disable', 'Disable')}
            </MenuItem>
            <MenuItem onClick={() => {
                edit();
                handleClose();
            }}>
                {t('search_condition.menu.edit', 'Edit')}
            </MenuItem>
            <MenuItem onClick={() => {
                onDelete(condition);
            }}>
                {t('search_condition.menu.remove', 'Remove')}
            </MenuItem>
        </Menu>
    </>
}
