import React from 'react';
import {parseInlineStyle} from '@alchemy/core';
import {Typography} from '@mui/material';
import {AppLogoProps} from './types';

export function AppLogo({appTitle, config, onLogoClick}: AppLogoProps) {
    return (
        <>
            <Typography
                variant="h1"
                noWrap
                component="div"
                onClick={onLogoClick}
                sx={theme => ({
                    fontSize: 20,
                    color: theme.palette.primary.main,
                    display: 'flex',
                })}
            >
                {config.logo?.src ? (
                    <img
                        src={config.logo.src}
                        alt={appTitle}
                        style={{
                            cursor: onLogoClick ? 'pointer' : undefined,
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
                            cursor: onLogoClick ? 'pointer' : undefined,
                        }}
                    >
                        <img
                            style={{
                                height: 32,
                                marginRight: 8,
                                ...(config.logo?.style
                                    ? parseInlineStyle(config.logo!.style)
                                    : {maxHeight: 32, maxWidth: 150}),
                            }}
                            src="https://phrasea-alchemy-statics.s3.eu-west-3.amazonaws.com/Images/phrasea-logo.png"
                            alt={appTitle}
                        />
                        {appTitle}
                    </div>
                )}
            </Typography>
        </>
    );
}
