import {
    Card,
    CardActions,
    CardContent,
    CardMedia,
    Grid2 as Grid,
    IconButton,
    Typography,
    Link,
} from '@mui/material';
import {JSX, PropsWithChildren, ReactNode} from 'react';

type BaseProps = {
    title: string;
    mainUrl?: string;
    description?: ReactNode;
    logo?: string;
    disabled?: boolean;
};

type AppLink = {
    icon: ReactNode;
    title: string;
    href: string;
};

type Props = {
    links?: AppLink[];
} & BaseProps;

export type {BaseProps as ServiceBaseProps};

export default function Service({
    title,
    logo,
    description,
    mainUrl,
    links = [],
    disabled = false,
}: Props) {
    const href = disabled ? undefined : mainUrl;
    return (
        <Grid
            size={{
                sm: 4,
                md: 3,
                xs: 6,
            }}
            sx={{
                justifyContent: 'stretch',
            }}
        >
            <Card
                aria-disabled={disabled || undefined}
                sx={{
                    height: '100%',
                    ...(disabled
                        ? {
                              filter: 'grayscale(1)',
                              opacity: 0.5,
                          }
                        : {}),
                }}
            >
                <AnchorLink href={href}>
                    <CardMedia
                        sx={theme => ({
                            height: {
                                xs: 60,
                                sm: 80,
                                md: 140,
                            },
                            backgroundSize: 'contain',
                            p: 1,
                            backgroundColor: theme.palette.background.default,
                            img: {
                                height: '100%',
                                width: 'auto',
                                margin: '0 auto',
                                display: 'block',
                            },
                        })}
                    >
                        <img src={logo} alt="logo" />
                    </CardMedia>
                </AnchorLink>
                <CardContent>
                    <AnchorLink href={href}>
                        <Typography gutterBottom variant="h2">
                            {title}
                        </Typography>
                    </AnchorLink>
                    {description && (
                        <Typography
                            variant="body2"
                            sx={{
                                display: {
                                    xs: 'none',
                                    md: 'block',
                                },
                            }}
                        >
                            {description}
                        </Typography>
                    )}
                </CardContent>
                <CardActions>
                    {(disabled ? [] : links).map(({href, icon, title}, i) => (
                        <IconButton
                            size="small"
                            key={i}
                            href={href}
                            title={title}
                            target={'_blank'}
                            rel={'noreferrer noopener'}
                        >
                            {icon}
                        </IconButton>
                    ))}
                </CardActions>
            </Card>
        </Grid>
    );
}

function AnchorLink({
    href,
    children,
}: PropsWithChildren<{
    href: string | undefined;
}>) {
    if (href) {
        return (
            <Link
                style={{
                    textDecoration: 'none',
                }}
                href={href}
                target={'_blank'}
                rel={'noreferrer noopener'}
            >
                {children}
            </Link>
        );
    }

    return children as JSX.Element;
}
