import config from '../../config.ts';
import Typography from '@mui/material/Typography';
import React, {useContext} from 'react';
import {useTranslation} from 'react-i18next';
import {SearchContext} from '../Media/Search/SearchContext.tsx';
import {parseInlineStyle} from '@alchemy/core';

type Props = {};

export default function Logo({}: Props) {
    const {t} = useTranslation();
    const searchContext = useContext(SearchContext)!;
    const onTitleClick = () => searchContext.reset();

    return (
        <>
            <Typography
                variant="h1"
                noWrap
                component="div"
                sx={theme => ({
                    fontSize: 20,
                    p: 2,
                    color: theme.palette.primary.main,
                    display: {
                        xs: 'none',
                        md: 'flex',
                    },
                })}
            >
                {config.logo?.src ? (
                    <img
                        onClick={onTitleClick}
                        src={config.logo.src}
                        alt={t('common.databox', `Databox`)}
                        style={{
                            cursor: 'pointer',
                            ...(config.logo!.style
                                ? parseInlineStyle(config.logo.style)
                                : {maxHeight: 32, maxWidth: 150}),
                        }}
                    />
                ) : (
                    <div
                        onClick={onTitleClick}
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
                            alt="Databox"
                        />
                        {t('common.databox', `Databox`)}
                    </div>
                )}
            </Typography>
        </>
    );
}
