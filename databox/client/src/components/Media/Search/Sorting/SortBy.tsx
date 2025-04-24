import React, {useContext} from 'react';
import {Chip, Menu} from '@mui/material';
import ImportExportIcon from '@mui/icons-material/ImportExport';
import SortByChip from '../SortByChip';
import EditSortBy from './EditSortBy';
import {SearchContext} from '../SearchContext';
import {ResultContext} from '../ResultContext';
import {useTranslation} from 'react-i18next';
import {
    getIndexBySearchSlug,
    useAttributeDefinitionStore,
} from '../../../../store/attributeDeifnitionStore.ts';

type Props = {};

export default function SortBy({}: Props) {
    const {t} = useTranslation();
    const search = useContext(SearchContext)!;
    const {load, definitions, loaded} = useAttributeDefinitionStore();
    const definitionsIndex = getIndexBySearchSlug();
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);

    React.useEffect(() => {
        load(t);
    }, [load, t]);

    const handleOpen = (event: React.MouseEvent<HTMLDivElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleClose = () => {
        setAnchorEl(null);
    };

    return (
        <>
            <Chip
                onClick={handleOpen}
                disabled={!loaded}
                label={
                    <>
                        <ImportExportIcon
                            style={{
                                verticalAlign: 'middle',
                            }}
                        />
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
                    </>
                }
                sx={{
                    mr: 1,
                }}
            />
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
