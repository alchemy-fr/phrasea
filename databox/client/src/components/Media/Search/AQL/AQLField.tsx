import {TextField} from "@mui/material";
import React from "react";
import {parseAQLQuery} from "./AQL.ts";

type Props = {};

export default function AqlField({}: Props) {
    const [value, setValue] = React.useState('');

    React.useEffect(() => {
        console.log(value, parseAQLQuery(value));
    }, [value]);

    return <>
        <TextField
            value={value}
            onChange={(e) => setValue(e.target.value)}
        />
    </>
}
