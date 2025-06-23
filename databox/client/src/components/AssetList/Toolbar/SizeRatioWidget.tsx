import React from 'react';
import {Slider} from '@mui/material';

type Props = {
    onChange: (value: number) => void;
    sliderId: string;
    defaultValue: number;
    min: number;
    max: number;
};

export default function SizeRatioWidget({
    onChange,
    sliderId,
    defaultValue,
    min,
    max,
}: Props) {
    const [value, setValue] = React.useState<number>(defaultValue);

    return (
        <Slider
            max={max}
            min={min}
            value={value}
            aria-labelledby={sliderId}
            valueLabelDisplay="auto"
            onChange={(_e, value) => {
                setValue(value as number);
                setTimeout(() => {
                    onChange(value as number);
                }, 0);
            }}
        />
    );
}
