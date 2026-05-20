import {PropsWithChildren, ReactNode, useState} from 'react';
import {Box, Button, Divider, Typography} from '@mui/material';
import {Trans} from 'react-i18next';

type Props = PropsWithChildren<{
    title: ReactNode;
    name: string;
    parentDisplay?: boolean;
}>;

export default function ParentAcl({
    title,
    name,
    children,
    parentDisplay,
}: Props) {
    const [display, setDisplay] = useState(parentDisplay ?? false);

    return (
        <div>
            <Divider
                sx={{
                    my: 2,
                }}
            />
            {display ? (
                <>
                    <Typography
                        sx={{
                            mb: 1,
                        }}
                    >
                        {title}
                    </Typography>
                    {children}
                </>
            ) : (
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'row',
                        alignItems: 'center',
                        justifyContent: 'center',
                    }}
                >
                    <Button variant="outlined" onClick={() => setDisplay(true)}>
                        <Trans
                            i18nKey={'acl.parent.show'}
                            defaults={
                                'Show Parent Permissions <strong>{{name}}</strong>'
                            }
                            values={{
                                name,
                            }}
                        />
                    </Button>
                </Box>
            )}
        </div>
    );
}
