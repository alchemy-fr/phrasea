import {Typography} from '@mui/material';
import {Trans, useTranslation} from "react-i18next";
import {PropsWithChildren} from "react";

type Props = {};

type A = {
    a: string;
    b: boolean;
}
// @ts-expect-error Unused
type T = PropsWithChildren<Pick<
    A,
    'b' | 'a'
>>;

export default function TestMorph({}: Props) {
    const {t} = useTranslation();

    if (t.hasOwnProperty('ws')) {
        console.log('Bonjour');
    }

    // @ts-expect-error Unused
    const _a = {
        Hello: 'you!',
        'Hello2': 'you!',
        ['Hello3']: 'you!',
        'Content-Type': 'the value',
        key: 'untranslated_key',
        sub: {
            Yeah: 'yo!',
            'Yeah2': 'yo!',
            ['Yeah3']: 'yo!',
            key: 'untranslated_key',
        }
    };

    // @ts-expect-error Unused
    const eventHandler = (_e: HTMLElementEventMap['scroll']) => {
        const data = {
            foo: 'bar',
        };

        const otherData = {
            foo: 'bar',
        };

        console.log('debug', data['foo']);
    };

    return (
        <>
            <Typography variant={'h2'}>{'Tag rules'}</Typography>
            OK
            {t('foo', 'bar')}
            <div
                title={'The title'}
                data-alt={`Template Literal`}
                data-test="A text"
            ></div>
            <Trans>Already translated</Trans>
            <Trans>
                Already <b>bold</b>.
            </Trans>
        </>
    );
}
