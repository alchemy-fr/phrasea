import React, {useContext, useMemo} from 'react';
import {Button, Menu} from '@mui/material';
import ImportExportIcon from '@mui/icons-material/ImportExport';
import SortByChip from '../SortByChip';
import EditSortBy from './EditSortBy';
import {SearchContext} from '../SearchContext';
import {useTranslation} from 'react-i18next';
import {
    useAttributeDefinitionStore,
    useIndexBySearchSlug,
} from '../../../../store/attributeDefinitionStore.ts';
import {AttributeDefinitionOrBuiltIn} from '../../../../types.ts';

type Props = {};

export default function SortBy({}: Props) {
    const {t} = useTranslation();
    const search = useContext(SearchContext)!;
    const {load, definitions, builtIn, loaded} = useAttributeDefinitionStore();

    const allDefinitions = useMemo<AttributeDefinitionOrBuiltIn[]>(() => {
        return [...builtIn, ...definitions];
    }, [definitions, builtIn]);

    const definitionsIndex = useIndexBySearchSlug(true);
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);

    React.useEffect(() => {
        load();
    }, [load]);

    const handleClose = () => {
        setAnchorEl(null);
    };

    return (
        <>
            <Button
                onClick={event => {
                    setAnchorEl(event.currentTarget);
                }}
                disabled={!loaded}
                sx={{
                    'mr': 1,
                    '.MuiChip-root': {
                        my: -1,
                    },
                }}
                startIcon={<ImportExportIcon />}
            >
                {t('sort_by.sort_by', `Sort by`)}
                {loaded ? (
                    <>
                        {search.sortBy.map((o, i) => (
                            <SortByChip
                                key={i}
                                definition={definitionsIndex[o.a]!}
                                sortBy={o}
                            />
                        ))}
                    </>
                ) : null}
            </Button>
            <Menu anchorEl={anchorEl} open={menuOpen} onClose={handleClose}>
                <EditSortBy
                    definitionsIndex={definitionsIndex}
                    definitions={allDefinitions}
                    onClose={handleClose}
                />
            </Menu>
        </>
    );
}
