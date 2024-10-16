import {Share} from '../../types.ts';
import {Icon, ListItem, ListItemText, Typography} from '@mui/material';
import moment from 'moment';
import CopiableTextField from '../Ui/CopiableTextField.tsx';
import AccessTimeIcon from '@mui/icons-material/AccessTime';
import {LoadingButton} from '@mui/lab';
import {useTranslation} from 'react-i18next';
import DeleteIcon from '@mui/icons-material/Delete';
import {FlexRow} from '../../../../../lib/js/phrasea-ui';
import {getShareTitle, UrlActions} from "./UrlActions.tsx";
import React from "react";
import {getShareUrl} from "./shareUtils.ts";
import SelectShareAlternateUrl from "./SelectShareAlternateUrl.tsx";
import ShareSocials from "./ShareSocials.tsx";

type Props = {
    share: Share;
    revoking: boolean;
    onRevoke: (id: string) => void;
};

export default function ShareItem({share, revoking, onRevoke}: Props) {
    const {t} = useTranslation();
    const [selectedAlternate, setSelectedAlternate] = React.useState<string | undefined>();
    const shareTitle = getShareTitle(share);
    const alternateUrl = selectedAlternate ? share.alternateUrls.find(a => a.name === selectedAlternate) : undefined;
    const shareUrl = alternateUrl?.url ?? getShareUrl(share);

    return (
        <>
            <ListItem divider={true}>
                <ListItemText
                    sx={{
                        flexShrink: 1,
                    }}
                    primary={
                        <>
                            {share.title ? (
                                <Typography
                                    component={'span'}
                                    variant={'body1'}
                                >
                                    {share.title}
                                    {' - '}
                                </Typography>
                            ) : (
                                ''
                            )}

                            <Typography
                                component={'span'}
                                variant={'body2'}
                            >
                                {t(
                                    'share.item.createdAt',
                                    'Created at {{date}}',
                                    {
                                        date: moment(share.createdAt).format(
                                            'LLL'
                                        ),
                                    }
                                )}
                            </Typography>

                            <div>
                                <CopiableTextField
                                    disabled={revoking}
                                    value={shareUrl}
                                    startAdornment={<div
                                    >
                                        <SelectShareAlternateUrl
                                            onSelect={setSelectedAlternate}
                                            value={selectedAlternate}
                                            alternateUrls={share.alternateUrls}
                                        />
                                    </div>}
                                    actions={<UrlActions
                                        url={shareUrl}
                                    />}
                                />
                            </div>
                        </>
                    }
                    secondary={
                        <>
                            {share.startsAt ? (
                                <FlexRow>
                                    <Icon
                                        sx={{
                                            mr: 1,
                                        }}
                                    >
                                        <AccessTimeIcon/>
                                    </Icon>
                                    {t(
                                        'share.item.startsAt',
                                        'Starts at {{date}}',
                                        {
                                            date: moment(share.startsAt).format(
                                                'LLL'
                                            ),
                                        }
                                    )}
                                </FlexRow>
                            ) : (
                                ''
                            )}

                            {share.expiresAt ? (
                                <FlexRow>
                                    <Icon
                                        sx={{
                                            mr: 1,
                                        }}
                                    >
                                        <AccessTimeIcon/>
                                    </Icon>
                                    {t(
                                        'share.item.expiresAt',
                                        'Expires at {{date}}',
                                        {
                                            date: moment(
                                                share.expiresAt
                                            ).format('LLL'),
                                        }
                                    )}
                                </FlexRow>
                            ) : (
                                ''
                            )}
                            <div>
                                <ShareSocials
                                    url={shareUrl}
                                    title={shareTitle}
                                    isImage={alternateUrl?.type?.startsWith('image/') ?? false}
                                />
                            </div>
                        </>
                    }
                />
                <div
                    style={{
                        flexGrow: 0,
                    }}
                >
                    <LoadingButton
                        sx={{
                            ml: 2,
                        }}
                        color={'error'}
                        startIcon={<DeleteIcon/>}
                        loading={revoking}
                        disabled={revoking}
                        onClick={() => onRevoke(share.id)}
                    >
                        {t('common.revoke', 'Revoke')}
                    </LoadingButton>
                </div>
            </ListItem>
        </>
    );
}
