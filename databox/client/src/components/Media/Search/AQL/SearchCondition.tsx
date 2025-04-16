import {AQLQuery, astToString, replaceIdFromFacets} from './query.ts';
import {Chip, Menu, MenuItem} from '@mui/material';
import {useModals} from '@alchemy/navigation';
import SearchConditionDialog from './SearchConditionDialog.tsx';
import React from 'react';
import MoreVertIcon from '@mui/icons-material/MoreVert';
import {useTranslation} from 'react-i18next';
import {TResultContext} from "../ResultContext.tsx";
import {parseAQLQuery} from "./AQL.ts";
import {replaceEntities} from "./entities.tsx";

type Props = {
    condition: AQLQuery;
    onDelete: (condition: AQLQuery) => void;
    onUpsert: (condition: AQLQuery) => void;
    result: TResultContext;
};

export default function SearchCondition({
    condition,
    onDelete,
    onUpsert,
    result,
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
    };

    const ast = parseAQLQuery(condition.query);
    const facetBucket = ast && result?.facets ? replaceIdFromFacets(ast, result.facets!) : undefined;
    const query = facetBucket ? replaceEntities(astToString(ast)) : condition.query;

    return (
        <>
            <Chip
                sx={theme => ({
                    mr: 1,
                    color: condition.disabled
                        ? 'warning.contrastText'
                        : 'primary.contrastText',
                    bgcolor: condition.disabled
                        ? 'warning.main'
                        : 'primary.main',
                    fontFamily: 'Courier New',
                    '.entity': {
                        border: `1px solid ${theme.palette.primary.contrastText}`,
                        p: 0.5,
                        borderRadius: 2,
                    }
                })}
                label={query}
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
                <MenuItem
                    onClick={() => {
                        onUpsert({
                            ...condition,
                            disabled: !condition.disabled,
                        });
                        handleClose();
                    }}
                >
                    {condition.disabled
                        ? t('search_condition.menu.enable', 'Enable')
                        : t('search_condition.menu.disable', 'Disable')}
                </MenuItem>
                <MenuItem
                    onClick={() => {
                        edit();
                        handleClose();
                    }}
                >
                    {t('search_condition.menu.edit', 'Edit')}
                </MenuItem>
                <MenuItem
                    onClick={() => {
                        onDelete(condition);
                    }}
                >
                    {t('search_condition.menu.remove', 'Remove')}
                </MenuItem>
            </Menu>
        </>
    );
}
