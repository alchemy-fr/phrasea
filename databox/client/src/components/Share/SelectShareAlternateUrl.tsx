import React from "react";
import {useTranslation} from 'react-i18next';
import {Button, Menu, MenuItem} from "@mui/material";
import {ShareAlternateUrl} from "../../types.ts";
import KeyboardArrowDownIcon from "@mui/icons-material/KeyboardArrowDown";

type Props = {
    onSelect: (value: string | undefined) => void;
    value: string | undefined;
    alternateUrls: ShareAlternateUrl[];
};

export default function SelectShareAlternateUrl({
    onSelect,
    value,
    alternateUrls,
}: Props) {
    const {t} = useTranslation();
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const open = Boolean(anchorEl);
    const handleClick = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleClose = () => {
        setAnchorEl(null);
    };

    const select: Props['onSelect'] = (value) => {
        onSelect(value);
        handleClose();
    }

    const defaultLabel = t('share.item.rendition.asset', 'Asset');

    return <>
        <Button
            aria-haspopup="true"
            aria-expanded={open ? 'true' : undefined}
            variant="contained"
            disableElevation
            onClick={handleClick}
            endIcon={<KeyboardArrowDownIcon />}
        >
            {value || defaultLabel}
        </Button>
        <Menu
            anchorEl={anchorEl}
            open={open}
            onClose={handleClose}
        >
            <MenuItem
                onClick={() => select(undefined)}
                selected={!value}
            >
                {defaultLabel}
            </MenuItem>
            {alternateUrls.map(a => (
                <MenuItem
                    key={a.name}
                    onClick={() => select(a.name)}
                    selected={a.name === value}
                >
                    {a.name}
                </MenuItem>
            ))}
        </Menu>
    </>
}
