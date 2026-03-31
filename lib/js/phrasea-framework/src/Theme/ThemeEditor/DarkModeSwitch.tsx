import * as React from 'react';
import {useColorScheme} from '@mui/material/styles';
import SettingsBrightnessIcon from '@mui/icons-material/SettingsBrightness';
import {ToggleButton, ToggleButtonGroup} from '@mui/material';
import DarkModeIcon from '@mui/icons-material/DarkMode';
import LightModeIcon from '@mui/icons-material/LightMode';

export default function DarkModeSwitch() {
    const {mode = 'system', setMode} = useColorScheme();

    return (
        <ToggleButtonGroup
            value={mode}
            exclusive
            onChange={(_e, value) => setMode(value)}
            aria-label="toggle dark mode"
        >
            <ToggleButton value="system" aria-label="system">
                <SettingsBrightnessIcon />
            </ToggleButton>
            <ToggleButton value="light" aria-label="light">
                <LightModeIcon />
            </ToggleButton>
            <ToggleButton value="dark" aria-label="dark">
                <DarkModeIcon />
            </ToggleButton>
        </ToggleButtonGroup>
    );
}
