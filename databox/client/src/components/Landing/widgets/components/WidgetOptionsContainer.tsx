import {Paper, Typography} from '@mui/material';
import {WidgetOptionsContainerProps} from '../widgetTypes.ts';

export default function WidgetOptionsContainer({
    title,
    children,
}: WidgetOptionsContainerProps) {
    return (
        <div style={{position: 'relative'}}>
            <Paper
                sx={{
                    display: 'flex',
                    flexDirection: 'row',
                    alignItems: 'center',
                    gap: 1,
                    p: 1,
                }}
            >
                <Typography variant={'h6'}>{title}</Typography>
                {children}
            </Paper>
        </div>
    );
}
