import Typography from '@mui/material/Typography';
import React from 'react';
import {useTranslation} from 'react-i18next';
import {parseInlineStyle} from '@alchemy/core';
import {config} from '../init.ts';

type Props = {
    onClick?: () => void;
};

export default function Logo({onClick}: Props) {
    const {t} = useTranslation();

    const appName = t('common.uploader', `Uploader`);

    return (
        <>
            <Typography
                variant="h1"
                noWrap
                component="div"
                onClick={onClick}
                sx={theme => ({
                    fontSize: 20,
                    p: 2,
                    color: theme.palette.primary.main,
                    display: 'flex',
                })}
            >
                {config.logo?.src ? (
                    <img
                        src={config.logo.src}
                        alt={appName}
                        style={{
                            cursor: 'pointer',
                            ...(config.logo!.style
                                ? parseInlineStyle(config.logo.style)
                                : {maxHeight: 32, maxWidth: 150}),
                        }}
                    />
                ) : (
                    <div
                        style={{
                            display: 'flex',
                            alignItems: 'center',
                            cursor: 'pointer',
                        }}
                    >
                        <img
                            style={{
                                height: 32,
                                marginRight: 8,
                            }}
                            src="https://phrasea-alchemy-statics.s3.eu-west-3.amazonaws.com/Images/phrasea-logo.png"
                            alt={appName}
                        />
                        {appName}
                    </div>
                )}
            </Typography>
        </>
    );
}
