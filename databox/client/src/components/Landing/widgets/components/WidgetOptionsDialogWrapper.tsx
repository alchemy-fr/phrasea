import WidgetOptionsContainer from './WidgetOptionsContainer.tsx';
import {useState} from 'react';
import {WidgetOptionsDialogWrapperProps} from '../widgetTypes.ts';
import IconButton from '@mui/material/IconButton';
import SettingsIcon from '@mui/icons-material/Settings';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {Button} from '@mui/material';

export default function WidgetOptionsDialogWrapper({
    children,
    dialogProps = {},
    ...props
}: WidgetOptionsDialogWrapperProps) {
    const {title} = props;
    const {t} = useTranslation();

    const [open, setOpen] = useState(false);

    return (
        <WidgetOptionsContainer {...props}>
            <IconButton onClick={() => setOpen(true)}>
                <SettingsIcon />
            </IconButton>
            {open ? (
                <AppDialog
                    open={true}
                    title={title}
                    onClose={() => setOpen(false)}
                    actions={({onClose}) => (
                        <>
                            <Button onClick={() => onClose()}>
                                {t('common.close', 'Close')}
                            </Button>
                        </>
                    )}
                    {...dialogProps}
                >
                    <div>{children}</div>
                </AppDialog>
            ) : null}
        </WidgetOptionsContainer>
    );
}
