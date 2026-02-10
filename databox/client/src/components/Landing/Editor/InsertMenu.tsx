import {useTranslation} from 'react-i18next';
import {Button} from '@mui/material';
type Props = {};

export default function InsertMenu({}: Props) {
    const {t} = useTranslation();

    return (
        <>
            <Button onClick={() => {}}>
                {t('landing.editor.menu.add.widget', 'Widget')}
            </Button>
        </>
    );
}
