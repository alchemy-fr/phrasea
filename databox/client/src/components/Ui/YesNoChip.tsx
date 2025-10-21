import {Chip} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {
    value: boolean;
};

export default function YesNoChip({value}: Props) {
    const {t} = useTranslation();
    return (
        <>
            {value ? (
                <Chip
                    size={'small'}
                    color={'success'}
                    label={t('common.yes', `Yes`)}
                />
            ) : (
                <Chip
                    size={'small'}
                    color={'error'}
                    label={t('common.no', `No`)}
                />
            )}
        </>
    );
}
