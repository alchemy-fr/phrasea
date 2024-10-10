import {Share} from '../../types.ts';
import {Box, Icon, ListItem, ListItemText, Typography} from '@mui/material';
import moment from 'moment';
import CopiableTextField from '../Ui/CopiableTextField.tsx';
import AccessTimeIcon from '@mui/icons-material/AccessTime';
import {LoadingButton} from '@mui/lab';
import {useTranslation} from 'react-i18next';
import DeleteIcon from '@mui/icons-material/Delete';
import {routes} from '../../routes.ts';
import {getPath} from '@alchemy/navigation';
import {FlexRow} from '../../../../../lib/js/phrasea-ui';

type Props = {
    share: Share;
    revoking: boolean;
    onRevoke: (id: string) => void;
};

export default function ShareItem({share, revoking, onRevoke}: Props) {
    const {t} = useTranslation();

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
                                    variant={'body1'}
                                    style={{display: 'inline'}}
                                >
                                    {share.title}
                                    {' - '}
                                </Typography>
                            ) : (
                                ''
                            )}

                            <Typography
                                variant={'body2'}
                                style={{display: 'inline'}}
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
                                    value={getShareUrl(share)}
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
                                        <AccessTimeIcon />
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
                                        <AccessTimeIcon />
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

                            {Object.keys(share.alternateUrls).map(name => {
                                return (
                                    <Box
                                        sx={{
                                            mt: 2,
                                        }}
                                        key={name}
                                    >
                                        <Typography variant={'body1'}>
                                            {name}
                                        </Typography>
                                        <CopiableTextField
                                            disabled={revoking}
                                            value={share.alternateUrls[name]}
                                        />
                                    </Box>
                                );
                            })}
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
                        startIcon={<DeleteIcon />}
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

export const getShareUrl = (s: Share) =>
    getPath(
        routes.share,
        {
            id: s.id,
            token: s.token,
        },
        {
            absoluteUrl: true,
        }
    );
