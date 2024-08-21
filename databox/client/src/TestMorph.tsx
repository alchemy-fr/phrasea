import { useTranslation } from 'react-i18next';

type Props = {};

export default function TestMorph({}: Props) {
    const {t} = useTranslation();

    // @ts-expect-error Unused
    const _a = {
        'Hello': 'you!',
    };

    return <>
        {t('test_morph.ok', `OK`)}<div title={t('test_morph.the_title', `The title`)}></div>
    </>
}
