import React, {useState} from 'react';
import {OrderBy} from "./Filter";
import ArrowDropDownIcon from "@mui/icons-material/ArrowDropDown";
import ArrowDropUpIcon from "@mui/icons-material/ArrowDropUp";
import {Chip} from "@mui/material";

type Props = {} & OrderBy;

export default function OrderByChip({
    t,
    w,
    a,
                                    }: Props) {

    return <Chip
            sx={{
                mr: 1,
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
