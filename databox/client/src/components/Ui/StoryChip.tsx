import BurstModeIcon from '@mui/icons-material/BurstMode';
import {Chip} from '@mui/material';
import {useTranslation} from 'react-i18next';
import assetClasses from '../AssetList/classes.ts';

type Props = {};

export default function StoryChip({}: Props) {
    const {t} = useTranslation();

    return (
        <div className={assetClasses.storyChip}>
            <Chip
                color={'info'}
                icon={<BurstModeIcon />}
                label={t('story.chip.label', 'Story')}
            />
        </div>
    );
}
