import React from 'react';
import {Grid, Slider} from '@mui/material';
import PhotoSizeSelectLargeIcon from '@mui/icons-material/PhotoSizeSelectLarge';
import PhotoSizeSelectActualIcon from '@mui/icons-material/PhotoSizeSelectActual';

type Props = {
    onChange: (value: number) => void;
    sliderId: string;
    defaultValue: number;
};

export default function ThumbSizeWidget({
    onChange,
    sliderId,
    defaultValue,
}: Props) {
    const [value, setValue] = React.useState<number>(defaultValue);
    const max = 400;
    const min = 60;

    return (
        <Grid container spacing={2} alignItems="center">
            <Grid item>
                <PhotoSizeSelectLargeIcon />
            </Grid>
            <Grid item xs>
                <Slider
                    max={max}
                    min={min}
                    value={value}
                    aria-labelledby={sliderId}
                    valueLabelDisplay="auto"
                    onChange={(_e, value) => {
                        setValue(value as number);
                        onChange(value as number);
                    }}
                />
            </Grid>
            <Grid item>
                <PhotoSizeSelectActualIcon />
            </Grid>
        </Grid>
    );
}
