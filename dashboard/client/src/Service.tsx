import {
    Card,
    CardActions,
    CardContent,
    CardMedia,
    Grid,
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
}: Props) {
    return (
        <Grid item xs={6} sm={4} md={3}>
            <Card
                sx={{
                    minHeight: {
                        xs: 180,
                        sm: 200,
                        md: 300,
                    },
                }}
            >
                <AnchorLink href={mainUrl}>
                    <CardMedia
                        sx={theme => ({
                            height: {
                                xs: 60,
                                sm: 80,
                                md: 140,
                            },
                            backgroundSize: 'contain',
                            backgroundColor: theme.palette.background.default,
                        })}
                        image={logo}
                    />
                </AnchorLink>
                <CardContent>
                    <AnchorLink href={mainUrl}>
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
                                }
                            }}
                        >
                            {description}
                        </Typography>
                    )}
                </CardContent>
                <CardActions>
                    {links.map(({href, icon, title}, i) => (
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
