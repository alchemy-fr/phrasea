import {TextField} from "@mui/material";
import React from "react";
import {parseAQLQuery} from "./AQL.ts";

type Props = {
    value: string;
    onChange: (value: string) => void;
};

export default function AqlField({
    value,
    onChange,
}: Props) {
    return <>
        <TextField
            fullWidth={true}
            value={value}
            onChange={(e) => {
                console.log('parseAQLQuery', parseAQLQuery(e.target.value));
                onChange(e.target.value);
            }}
        />
    </>
}
