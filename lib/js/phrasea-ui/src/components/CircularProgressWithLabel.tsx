import {
    Box,
    CircularProgress,
    CircularProgressProps,
    Typography,
} from '@mui/material';

type Props = CircularProgressProps & {value: number};

export default function CircularProgressWithLabel(props: Props) {
    return (
        <div style={{position: 'relative', display: 'inline-flex'}}>
            <CircularProgress variant="determinate" {...props} />
            <Box
                sx={{
                    top: 0,
                    left: 0,
                    bottom: 0,
                    right: 0,
                    position: 'absolute',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                }}
            >
                <Typography
                    variant="caption"
                    component="div"
                    sx={{color: 'text.secondary'}}
                >{`${Math.round(props.value)}%`}</Typography>
            </Box>
        </div>
    );
}
