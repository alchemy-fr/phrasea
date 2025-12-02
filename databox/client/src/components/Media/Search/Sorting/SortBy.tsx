import React, {useContext} from 'react';
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

type Props = {};

export default function SortBy({}: Props) {
    const {t} = useTranslation();
    const search = useContext(SearchContext)!;
    const {load, definitions, loaded} = useAttributeDefinitionStore();
    const definitionsIndex = useIndexBySearchSlug();
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);

    React.useEffect(() => {
        load(t);
    }, [load, t]);

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
                    definitions={definitions}
                    onClose={handleClose}
                />
            </Menu>
        </>
    );
}
