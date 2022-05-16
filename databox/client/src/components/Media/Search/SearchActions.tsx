import React, {useContext, useEffect, useRef, useState} from 'react';
import {styled} from "@mui/material/styles";
import {alpha, Box, Button, InputBase} from "@mui/material";
import SearchIcon from '@mui/icons-material/Search';
import SearchFilters from "./SearchFilters";
import {ResultContext} from "./ResultContext";
import {useTranslation} from "react-i18next";
import {LAYOUT_GRID, LAYOUT_LIST} from "./Pager";

type Props = {};

export default function SearchActions({}: Props) {
    const search = useContext(ResultContext);
    const {t} = useTranslation();

    return <Box
        sx={{
            display: 'flex',
        }}
    >
        <div>
            <Button
                color={layout === LAYOUT_GRID ? "primary" : undefined}
                onClick={() => setLayout(LAYOUT_GRID)}>Grid</Button>

            <Button
                color={layout === LAYOUT_LIST ? "primary" : undefined}
                onClick={() => setLayout(LAYOUT_LIST)}
            >List</Button>
        </div>
    </Box>
}
