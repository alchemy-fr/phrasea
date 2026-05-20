import {getContrastText} from '@alchemy/phrasea-framework';
import {Box, Theme} from '@mui/material';

type Props = {
    color?: string | null;
};

export default function TagColor({color}: Props) {
    return (
        <Box
            sx={theme => ({
                ...getTagColorStyle(theme, color),
            })}
        />
    );
}

export function getTagColorStyle(
    theme: Theme,
    color: string | null | undefined
) {
    if (!color) {
        return {
            height: 15,
            width: 15,
        };
    }

    return {
        marginLeft: '0.5px',
        marginRight: theme.spacing(1),
        borderRadius: '50%',
        backgroundColor: color,
        outline: `0.5px solid ${getContrastText(theme, color)}`,
        height: 15,
        width: 15,
    };
}
