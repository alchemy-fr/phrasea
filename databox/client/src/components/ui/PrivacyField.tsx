import React from "react";
import MenuItem from '@material-ui/core/MenuItem';
import {privacyIndices} from "../Media/AssetItem";
import {FormControl, InputLabel, Select} from "@material-ui/core";

export default function PrivacyField(params: any) {
    return <FormControl>
        <InputLabel id="demo-controlled-open-select-label">Privacy</InputLabel>
        <Select
            {...params.field}
        >
            {privacyIndices.map((p, i) => <MenuItem
                key={i}
                value={i}
            >
                {p}
            </MenuItem>)}
        </Select>
    </FormControl>
}
