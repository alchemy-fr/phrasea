import {TextField} from "@mui/material";
import React from "react";
import {parseAQLQuery} from "./AQL.ts";

type Props = {
    value: string;
    onChange: (value: string) => void;
    error?: boolean;
};

export default function AqlField({
    value,
    onChange,
    error,
}: Props) {
    return <>
        <TextField
            fullWidth={true}
            value={value}
            error={error}
            inputProps={{
                style: {
                    fontFamily: 'Courier New',
                },
                spellCheck: false,
            }}
            onChange={(e) => {
                onChange(e.target.value);
            }}
        />
    </>
}
