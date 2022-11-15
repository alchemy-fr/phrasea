import React from 'react';
import {SortBy} from "./Filter";
import ArrowDropDownIcon from "@mui/icons-material/ArrowDropDown";
import ArrowDropUpIcon from "@mui/icons-material/ArrowDropUp";
import {Chip} from "@mui/material";

type Props = {} & SortBy;

export default function SortByChip({
                                       t,
                                       w,
                                       a,
                                   }: Props) {

    return <Chip
        sx={{
            ml: 1,
        }}
        key={a}
        label={<>
            {t}
            {' '}
            {w ? <ArrowDropDownIcon
                fontSize={'small'}
                sx={{
                    verticalAlign: 'middle'
                }}
            /> : <ArrowDropUpIcon
                fontSize={'small'}
                sx={{
                    verticalAlign: 'middle'
                }}
            />}
        </>}
        color={'primary'}
    />
}
