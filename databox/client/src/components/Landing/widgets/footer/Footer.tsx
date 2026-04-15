import {PropsWithChildren} from 'react';
import {FooterWidgetProps} from './types.ts';
import {Box} from '@mui/material';

type Props = PropsWithChildren<FooterWidgetProps>;
export default function Footer({links, backgroundColor, textColor}: Props) {
    return (
        <Box
            component={'footer'}
            sx={{
                display: 'flex',
                flexDirection: 'row',
                backgroundColor: backgroundColor || undefined,
                color: textColor || undefined,
                padding: 4,
                gap: 2,
                a: {
                    'cursor': 'pointer',
                    'color': textColor || undefined,
                    'textDecoration': 'none',
                    '&:hover': {
                        textDecoration: 'underline',
                    },
                },
            }}
        >
            <div>
                {links?.map((link, i) => (
                    <div key={i}>
                        <a href={link.url} target={link.target ?? '_blank'}>
                            {link.label}
                        </a>
                    </div>
                ))}
            </div>
        </Box>
    );
}
