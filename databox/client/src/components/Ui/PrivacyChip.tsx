import React from 'react';
import {Chip, ChipProps, SvgIconProps, Tooltip} from "@mui/material";
import {useTranslation} from 'react-i18next';
import {grey} from "@mui/material/colors";
import LockIcon from '@mui/icons-material/Lock';
import {TooltipProps} from "@mui/material/Tooltip";
import {Privacy} from "../../api/privacy";

type Props = {
    privacy: Privacy;
};

function usePrivacyLabel(privacy: Privacy) {
    const {t} = useTranslation();

    const privacyIndices: Record<Privacy, string> = {
        [Privacy.Secret]: t('privacy.secret', 'Secret'),
        [Privacy.PrivateInWorkspace]: t('privacy.private_in_workspace', 'Private in workspace'),
        [Privacy.PublicInWorkspace]: t('privacy.public_in_workspace', 'Public in workspace'),
        [Privacy.Private]: t('privacy.private', 'Private'),
        [Privacy.PublicForUsers]: t('privacy.public_for_users', 'Public for users'),
        [Privacy.Public]: t('privacy.public', 'Public'),
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
