import {Chip, ChipProps, SvgIconProps} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {grey} from '@mui/material/colors';
import LockIcon from '@mui/icons-material/Lock';
import {Privacy} from '../../api/privacy';
import FastTooltip from './FastTooltip';
import assetClasses from '../AssetList/classes';
import {useMemo} from 'react';
import {getPrivacyTranslations} from '../../translations/privacyTranslations.ts';
import PrivacyTipIcon from '@mui/icons-material/PrivacyTip';

function usePrivacyLabel(privacy: Privacy, noAccess: boolean | undefined) {
    const {t} = useTranslation();
    const labels = useMemo(() => getPrivacyTranslations(t), [t]);

    if (noAccess) {
        return t('privacy.no_access', 'No Access');
    }

    return labels[privacy];
}

type Props = {
    privacy: Privacy;
    noAccess: boolean | undefined;
};

export default function PrivacyChip({
    privacy,
    noAccess,
    ...props
}: Props & ChipProps) {
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

export function PrivacyIcon({
    privacy,
    iconProps = {},
    noAccess,
}: {
    privacy: Privacy;
    iconProps?: SvgIconProps;
    noAccess?: boolean;
}) {
    const privacyLabel = usePrivacyLabel(privacy, noAccess);

    return (
        <FastTooltip title={privacyLabel}>
            <PrivacyTipIcon color={'inherit'} {...iconProps} />
        </FastTooltip>
    );
}

export function PrivacyTooltip({
    privacy,
    iconProps = {},
    noAccess,
}: {
    privacy: Privacy;
    iconProps?: SvgIconProps;
    noAccess?: boolean;
}) {
    return (
        <div className={assetClasses.privacy}>
            <PrivacyIcon
                privacy={privacy}
                iconProps={iconProps}
                noAccess={noAccess}
            />
        </div>
    );
}
