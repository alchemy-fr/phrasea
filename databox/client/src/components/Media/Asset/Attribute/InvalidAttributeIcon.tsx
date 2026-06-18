import WarningIcon from '@mui/icons-material/Warning';
import {Tooltip} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {};

export default function InvalidAttributeIcon({}: Props) {
    const {t} = useTranslation();
    return (
        <Tooltip
            sx={{
                ml: 1,
            }}
            title={t('attribute.invalidValue', 'Invalid value')}
        >
            <WarningIcon color={'warning'} />
        </Tooltip>
    );
}
