import {useContext} from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogTitle,
    List,
    ListItemButton,
} from '@mui/material';
import {useTranslation} from 'react-i18next';
import themes from '../../themes';
import {ThemeName} from '../../lib/theme';
import {UserPreferencesContext} from '../User/Preferences/UserPreferencesContext';
import {StackedModalProps, useModals} from '@alchemy/navigation';

type Props = {} & StackedModalProps;

export default function ChangeTheme({open}: Props) {
    const {t} = useTranslation();
    const prefContext = useContext(UserPreferencesContext);
    const {preferences, updatePreference} = prefContext;
    const {closeModal} = useModals();

    const handleClick = (name: ThemeName) => {
        updatePreference('theme', name);
    };

    const onClose = () => closeModal();

    return (
        <>
            <Dialog onClose={onClose} open={open}>
                <DialogTitle>
                    {t('change_theme.title', 'Choose a theme')}
                </DialogTitle>
                <List sx={{pt: 0}}>
                    {(Object.keys(themes) as ThemeName[]).map(
                        (t: ThemeName) => (
                            <ListItemButton
                                selected={preferences.theme === t}
                                onClick={() => handleClick(t)}
                                key={t}
                            >
                                {t}
                            </ListItemButton>
                        )
                    )}
                </List>
                <DialogActions>
                    <Button autoFocus onClick={onClose}>
                        {t('change_theme.save', 'Save')}
                    </Button>
                </DialogActions>
            </Dialog>
        </>
    );
}
