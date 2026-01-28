import React, {PropsWithChildren} from 'react';
import {Typography} from '@mui/material';

type Props = PropsWithChildren<{
    title: string;
}>;

export default function ErrorPage({title, children}: Props) {
    return (
        <div
            style={{
                textAlign: 'center',
            }}
        >
            <Typography variant={'h1'}>{title}</Typography>
            {children}
        </div>
    );
}
