import React from 'react';
import {Menu, Tooltip} from '@mui/material';
import {useTranslation} from 'react-i18next';
import DisplaySettingsIcon from '@mui/icons-material/DisplaySettings';
import IconButton from '@mui/material/IconButton';
import DisplayOptionsMenu from './DisplayOptionsMenu.tsx';

type Props = {};

export default function DisplaySettingButton({}: Props) {
    const {t} = useTranslation();
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);
    const handleMoreClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleMoreClose = () => {
        setAnchorEl(null);
    };

    const btnId = 'display-settings';

    return (
        <>
            <Tooltip
                title={t('layout.display_settings.tooltip', 'Display Settings')}
            >
                <IconButton
                    id={btnId}
                    aria-controls={menuOpen ? btnId : undefined}
                    aria-haspopup="true"
                    aria-expanded={menuOpen ? 'true' : undefined}
                    onClick={handleMoreClick}
                >
                    <DisplaySettingsIcon />
                </IconButton>
            </Tooltip>
            <Menu
                anchorEl={anchorEl}
                open={menuOpen}
                onClose={handleMoreClose}
                MenuListProps={{
                    'aria-labelledby': btnId,
                }}
            >
                <DisplayOptionsMenu />
            </Menu>
        </>
    );
}
