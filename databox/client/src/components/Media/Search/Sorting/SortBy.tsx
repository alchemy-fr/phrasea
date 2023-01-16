import React, {useContext, useEffect} from 'react';
import {Chip, Menu} from "@mui/material";
import ImportExportIcon from "@mui/icons-material/ImportExport";
import SortByChip from "../SortByChip";
import EditSortBy from "./EditSortBy";
import {SearchContext} from "../SearchContext";
import GroupWorkIcon from '@mui/icons-material/GroupWork';
import CloseIcon from '@mui/icons-material/Close';
import {LoadingButton} from "@mui/lab";
import {ResultContext} from "../ResultContext";

type Props = {};

export default function SortBy({}: Props) {
    const search = useContext(SearchContext);
    const resultContext = useContext(ResultContext);
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);
    const [loading, setLoading] = React.useState(false);
    const handleOpen = (event: React.MouseEvent<HTMLDivElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleClose = () => {
        setAnchorEl(null);
    };

    const isGrouped = search.sortBy[0].g;

    const toggleGroup = React.useCallback(() => {
        const newSortBy = [...search.sortBy];
        newSortBy[0].g = !newSortBy[0].g;
        search.setSortBy(newSortBy);
        setLoading(true);
    }, [search.sortBy]);

    useEffect(() => {
        setLoading(false);
    }, [search.sortBy]);

    return <>
        <Chip
            onClick={handleOpen}
            disabled={resultContext.loading}
            label={<>
                <ImportExportIcon
                    style={{
                        verticalAlign: 'middle',
                    }}
                />
                Sort by
                <>
                    {search.sortBy.map((o, i) => <SortByChip
                        key={i}
                        {...o}
                    />)}
                </>
            </>}
        />
        <Menu
            anchorEl={anchorEl}
            open={menuOpen}
            onClose={handleClose}
        >
            <EditSortBy
                onClose={handleClose}
            />
        </Menu>
        <LoadingButton
            sx={{ml: 1}}
            loading={loading || resultContext.loading}
            onClick={toggleGroup}
            startIcon={isGrouped ? <CloseIcon/> : <GroupWorkIcon/>}
        >
            {isGrouped ? 'Ungroup' : 'Group'}
        </LoadingButton>
    </>
}
