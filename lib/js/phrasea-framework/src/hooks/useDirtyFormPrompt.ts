import {useTranslation} from 'react-i18next';
import {useFormPrompt} from '@alchemy/navigation';

export function useDirtyFormPrompt(isDirty: boolean, modalIndex?: number) {
    const {t} = useTranslation();

    useFormPrompt(t, isDirty, modalIndex);
}
