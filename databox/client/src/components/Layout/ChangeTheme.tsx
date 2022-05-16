import React, {useContext} from 'react';
import {Dialog, DialogTitle, List, ListItemButton} from "@mui/material";
import {useTranslation} from "react-i18next";
import themes from "../../themes";
import {UserContext} from "../Security/UserContext";
import {ThemeName} from "../../lib/theme";

type Props = {
    onClose: () => void;
};

export default function ChangeTheme({
                                        onClose,
                                    }: Props) {
    const {t} = useTranslation();
    const userContext = useContext(UserContext);
    const {currentTheme, changeTheme} = userContext;

    const handleClick = (name: ThemeName) => {
        changeTheme!(name);
        onClose();
    }

    return <>
        <Dialog onClose={onClose} open={true}>
            <DialogTitle>{t('change_theme.title', 'Choose a theme')}</DialogTitle>
            <List sx={{pt: 0}}>
                {(Object.keys(themes) as ThemeName[]).map((t: ThemeName) => <ListItemButton
                    selected={currentTheme === t}
                    onClick={() => handleClick(t)}
                    key={t}>
                    {t}
                </ListItemButton>)}
            </List>
        </Dialog>
    </>
}
