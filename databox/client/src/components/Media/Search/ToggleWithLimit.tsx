import React from 'react';
import {FormControlLabel, FormGroup, Grid, Input, InputAdornment, Switch} from "@mui/material";

type Props = {
    value: boolean;
    toggle: () => void;
    setLimit: (limit: number) => void;
    limit: number;
    label: string;
    unit: string;
};

export default function ToggleWithLimit({
    value,
    toggle,
    setLimit,
    limit,
    label,
    unit
}: Props) {

    return <Grid container spacing={2} alignItems="center">
        <Grid item>
            <FormGroup>
                <FormControlLabel
                    control={<Switch
                        checked={value}
                        onChange={toggle}
                    />}
                    label={label}
                />
            </FormGroup>
        </Grid>

        {value && <Grid item>
            <Input
                onChange={(e) => setLimit(parseInt(e.target.value))}
                value={limit}
                type={'number'}
                inputProps={{
                    min: 1
                }}
                sx={theme => ({
                    input: {
                        width: theme.spacing(5)
                    }
                })}
                endAdornment={<InputAdornment position={'end'}>
                    {unit}
                </InputAdornment>}
            />
        </Grid>}
    </Grid>
}
