import {Stack, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {useAuth} from '@alchemy/react-auth';
import PersonIcon from '@mui/icons-material/Person';
import PublicIcon from '@mui/icons-material/Public';
import {DisplayProfile} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {getSharedProfileOwner} from '../../../store/profileStore.ts';

type Props = {
    id: string;
    data: DisplayProfile;
} & DialogTabProps;

function InfoRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <Typography variant="caption" color="text.secondary">
                {label}
            </Typography>
            <Typography
                variant="body2"
                component="div"
                sx={{display: 'flex', alignItems: 'center', gap: 0.5}}
            >
                {children}
            </Typography>
        </div>
    );
}

export default function ProfileInfo({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const {user} = useAuth();

    const sharedOwner = getSharedProfileOwner(data, user?.id);

    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <Stack spacing={2}>
                <InfoRow label={t('display_profile.info.name', 'Name')}>
                    {data.name}
                </InfoRow>

                {data.description ? (
                    <InfoRow
                        label={t(
                            'display_profile.info.description',
                            'Description'
                        )}
                    >
                        {data.description}
                    </InfoRow>
                ) : null}

                {sharedOwner ? (
                    <InfoRow label={t('display_profile.info.owner', 'Owner')}>
                        <PersonIcon fontSize="small" />
                        {sharedOwner.username}
                    </InfoRow>
                ) : null}

                <InfoRow
                    label={t('display_profile.info.visibility', 'Visibility')}
                >
                    {data.public ? (
                        <>
                            <PublicIcon fontSize="small" />
                            {t('display_profile.public', 'Public')}
                        </>
                    ) : (
                        t('display_profile.private', 'Private')
                    )}
                </InfoRow>

                <InfoRow
                    label={t('display_profile.info.created_at', 'Created at')}
                >
                    {new Date(data.createdAt).toLocaleString()}
                </InfoRow>

                <InfoRow
                    label={t('display_profile.info.updated_at', 'Updated at')}
                >
                    {new Date(data.updatedAt).toLocaleString()}
                </InfoRow>
            </Stack>
        </ContentTab>
    );
}
