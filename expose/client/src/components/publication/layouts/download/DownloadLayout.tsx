import React from 'react';
import {Button, Container, Typography} from '@mui/material';
import {LayoutProps} from '../types.ts';
import {useTranslation} from 'react-i18next';
import Description from '../common/Description.tsx';
import {getTranslatedDescription} from '../../../../i18n.ts';
import AssetIconThumbnail from '../../asset/AssetIconThumbnail.tsx';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import {useDownload} from '../../../../hooks/useDownload.ts';
import DownloadIcon from '@mui/icons-material/Download';

type Props = {} & LayoutProps;

export default function DownloadLayout({publication}: Props) {
    const {t} = useTranslation();

    const onDownload = useDownload({
        publication,
    });

    return (
        <>
            <Container
                sx={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 2,
                }}
            >
                {publication.assets.map(a => {
                    const {
                        thumbUrl,
                        originalName,
                        translations,
                        mimeType,
                        description,
                        subDefinitions,
                        downloadUrl,
                    } = a;

                    return (
                        <Card
                            key={a.id}
                            sx={{
                                display: 'flex',
                                flexDirection: {
                                    xs: 'column',
                                    sm: 'row',
                                },
                            }}
                        >
                            <CardMedia
                                sx={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    width: {
                                        xs: '100%',
                                        sm: 200,
                                    },
                                    minHeight: {
                                        xs: 200,
                                        sm: 'auto',
                                    },
                                    flexShrink: 0,
                                    img: {
                                        maxWidth: 200,
                                    },
                                }}
                            >
                                {thumbUrl ? (
                                    <img src={thumbUrl} alt={originalName} />
                                ) : (
                                    <AssetIconThumbnail mimeType={mimeType} />
                                )}
                            </CardMedia>
                            <CardContent
                                sx={{
                                    gap: 2,
                                    display: 'flex',
                                    flexDirection: 'column',
                                }}
                            >
                                <Typography component="div" variant="h5">
                                    {originalName} - {mimeType}
                                </Typography>
                                <Description
                                    descriptionHtml={getTranslatedDescription({
                                        translations,
                                        description,
                                    })}
                                />

                                <div>
                                    {downloadUrl ? (
                                        <Button
                                            component={'a'}
                                            startIcon={<DownloadIcon />}
                                            onClick={() =>
                                                onDownload(downloadUrl)
                                            }
                                            href={downloadUrl}
                                            variant={'contained'}
                                        >
                                            {t(
                                                'publication.layout.download.download_original',
                                                'Download original'
                                            )}
                                        </Button>
                                    ) : null}
                                    {subDefinitions
                                        .filter(d => !!d.downloadUrl)
                                        .map(d => {
                                            return (
                                                <Button
                                                    component={'a'}
                                                    startIcon={<DownloadIcon />}
                                                    key={d.id}
                                                    onClick={() =>
                                                        onDownload(
                                                            d.downloadUrl
                                                        )
                                                    }
                                                    href={d.downloadUrl}
                                                    variant={'outlined'}
                                                >
                                                    {t(
                                                        'publication.layout.download.download_subdef',
                                                        {
                                                            defaultValue:
                                                                'Download {{name}}',
                                                            name: d.name,
                                                        }
                                                    )}
                                                </Button>
                                            );
                                        })}
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </Container>
        </>
    );
}
