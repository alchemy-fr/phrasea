import React, {useContext, useState} from 'react';
import {Chip, Menu} from "@mui/material";
import ImportExportIcon from "@mui/icons-material/ImportExport";
import SortByChip from "../SortByChip";
import EditSortBy from "./EditSortBy";
import {SearchContext} from "../SearchContext";

type Props = {};

export default function SortBy({}: Props) {
    const search = useContext(SearchContext);
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);
    const handleOpen = (event: React.MouseEvent<HTMLDivElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleClose = () => {
        setAnchorEl(null);
    };

    return <>
        <Chip
            onClick={handleOpen}
            label={<>
                <ImportExportIcon
                    style={{
                        verticalAlign: 'middle',
                    }}
                />
                Sort by
                <>
                    {search.sortBy.map((o, i ) => <SortByChip
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
    </>
}
