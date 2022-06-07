import React from 'react';
import {Chip, ChipProps, SvgIconProps, Tooltip} from "@mui/material";
import {useTranslation} from 'react-i18next';
import {grey} from "@mui/material/colors";
import LockIcon from '@mui/icons-material/Lock';
import {TooltipProps} from "@mui/material/Tooltip";

enum Privacy {
    secret,
    private_in_workspace,
    public_in_workspace,
    private,
    public_for_users,
    public,
}

type Props = {
    privacy: Privacy;
};

function usePrivacyLabel(privacy: Privacy) {
    const {t} = useTranslation();

    const privacyIndices: Record<Privacy, string> = {
        [Privacy.secret]: t('privacy.secret', 'Secret'),
        [Privacy.private_in_workspace]: t('privacy.private_in_workspace', 'Private in workspace'),
        [Privacy.public_in_workspace]: t('privacy.public_in_workspace', 'Public in workspace'),
        [Privacy.private]: t('privacy.private', 'Private'),
        [Privacy.public_for_users]: t('privacy.public_for_users', 'Public for users'),
        [Privacy.public]: t('privacy.public', 'Public'),
    };

    return privacyIndices[privacy];
}

export default function PrivacyChip({
                                        privacy,
                                        ...props
                                    }: Props & ChipProps) {
    const privacyLabel = usePrivacyLabel(privacy);

    return <Chip
        {...props}
        icon={<LockIcon
            color={'inherit'}
            fontSize={props.size}
        />}
        label={privacyLabel}
        sx={theme => ({
            ml: 1,
            bgcolor: grey[200],
            color: grey[800],
        })}
    />
}

export function PrivacyTooltip({
                                   privacy,
                                   iconProps = {},
                                   tooltipProps = {},
                               }: {
    privacy: Privacy;
    iconProps?: SvgIconProps;
    tooltipProps?: Omit<TooltipProps, "children" | "title">;

}) {
    const privacyLabel = usePrivacyLabel(privacy);

    return <Tooltip
        title={privacyLabel}
        {...tooltipProps}
    >
        <LockIcon
            color={'inherit'}
            {...iconProps}
        />
    </Tooltip>
}
