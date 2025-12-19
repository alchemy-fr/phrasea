import {Box, CircularProgress} from '@mui/material';
import React from 'react';

type Props = {
    loading: boolean;
};

export default function AnimatedLoader({loading}: Props) {
    const inClass = 'appears-in';
    const [mounted, setMounted] = React.useState(loading);

    React.useEffect(() => {
        if (!loading) {
            const timeout = setTimeout(() => {
                setMounted(false);
            }, 500);

            return () => clearTimeout(timeout);
        } else {
            setMounted(true);
        }
    }, [loading]);

    return (
        <Box
            sx={{
                '> div': {
                    transform: 'scale(0.5)',
                    opacity: 0,
                    transition: 'opacity 0.5s ease-in, transform 0.5s ease-in',
                    [`&.${inClass}`]: {
                        opacity: 1,
                        transform: 'scale(1)',
                    },
                },
            }}
        >
            <div className={loading ? inClass : undefined}>
                {mounted && <CircularProgress />}
            </div>
        </Box>
    );
}
