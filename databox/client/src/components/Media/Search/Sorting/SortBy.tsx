import React, {useContext} from 'react';
import {Chip, Menu} from "@mui/material";
import ImportExportIcon from "@mui/icons-material/ImportExport";
import SortByChip from "../SortByChip";
import EditSortBy from "./EditSortBy";
import {SearchContext} from "../SearchContext";
import {ResultContext} from "../ResultContext";

type Props = {};

export default function SortBy({}: Props) {
    const search = useContext(SearchContext);
    const resultContext = useContext(ResultContext);
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
            sx={{
                mr: 1,
            }}
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
