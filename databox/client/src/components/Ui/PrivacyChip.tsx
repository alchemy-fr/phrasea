import {Chip, ChipProps, SvgIconProps, Tooltip} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {grey} from '@mui/material/colors';
import LockIcon from '@mui/icons-material/Lock';
import {TooltipProps} from '@mui/material/Tooltip';
import {Privacy} from '../../api/privacy';

function usePrivacyLabel(privacy: Privacy, noAccess: boolean | undefined) {
    const {t} = useTranslation();

    if (noAccess) {
        return t('privacy.no_access', 'No Access');
    }

    const privacyIndices: Record<Privacy, string> = {
        [Privacy.Secret]: t('privacy.secret', 'Secret'),
        [Privacy.PrivateInWorkspace]: t(
            'privacy.private_in_workspace',
            'Private in workspace'
        ),
        [Privacy.PublicInWorkspace]: t(
            'privacy.public_in_workspace',
            'Public in workspace'
        ),
        [Privacy.Private]: t('privacy.private', 'Private'),
        [Privacy.PublicForUsers]: t(
            'privacy.public_for_users',
            'Public for users'
        ),
        [Privacy.Public]: t('privacy.public', 'Public'),
    };

    return privacyIndices[privacy];
}

type Props = {
    privacy: Privacy;
    noAccess: boolean | undefined;
};

export default function PrivacyChip({privacy, noAccess, ...props}: Props & ChipProps) {
    const privacyLabel = usePrivacyLabel(privacy, noAccess);

    return (
        <Chip
            {...props}
            icon={<LockIcon color={'inherit'} fontSize={props.size} />}
            label={privacyLabel}
            sx={() => ({
                ml: 1,
                bgcolor: grey[200],
                color: grey[800],
            })}
        />
    );
}

export function PrivacyTooltip({
    privacy,
    iconProps = {},
    tooltipProps = {},
    noAccess,
}: {
    privacy: Privacy;
    iconProps?: SvgIconProps;
    tooltipProps?: Omit<TooltipProps, 'children' | 'title'>;
    noAccess?: boolean;
}) {
    const privacyLabel = usePrivacyLabel(privacy, noAccess);

    return (
        <Tooltip title={privacyLabel} {...tooltipProps}>
            <LockIcon color={'inherit'} {...iconProps} />
        </Tooltip>
    );
}
