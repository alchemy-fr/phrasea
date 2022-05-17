import React, {useContext} from 'react';
import {Box, IconButton, Menu, Slider, Tooltip} from "@mui/material";
import {useTranslation} from "react-i18next";
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import {DisplayContext} from "../DisplayContext";
import {debounce} from "../../../lib/debounce";


type Props = {};

export default function DisplayOptionsMenu({}: Props) {
    const {t} = useTranslation();
    const {thumbSize, setThumbSize} = useContext(DisplayContext);

    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const menuOpen = Boolean(anchorEl);
    const handleMoreClick = (event: React.MouseEvent<HTMLButtonElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleMoreClose = () => {
        setAnchorEl(null);
    };

    const onChange = debounce((e, v) => setThumbSize(v as number), 10);

    return <>
        <Tooltip title={t('layout.options.more', 'More options')}>
            <IconButton
                id="more-button"
                aria-controls={menuOpen ? 'more-menu' : undefined}
                aria-haspopup="true"
                aria-expanded={menuOpen ? 'true' : undefined}
                onClick={handleMoreClick}
            >
                <ArrowDropDownIcon/>
            </IconButton>
        </Tooltip>
        <Menu
            id="selection-more-menu"
            anchorEl={anchorEl}
            open={menuOpen}
            onClose={handleMoreClose}
            MenuListProps={{
                'aria-labelledby': 'more-button',
            }}
        >
            <Box

                sx={{
                    p: 1,
                }}
            >
                <Slider
                    sx={{
                        m: 5,
                        width: 200,
                    }}
                    max={400}
                    min={60}
                    defaultValue={thumbSize}
                    aria-label="Default"
                    valueLabelDisplay="auto"
                    onChange={onChange}
                />
            </Box>
        </Menu>
    </>
}
