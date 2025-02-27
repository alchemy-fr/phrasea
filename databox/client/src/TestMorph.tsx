import {Button, TextField, Typography} from '@mui/material';
import {Trans, useTranslation} from 'react-i18next';
import {PropsWithChildren} from 'react';

type Props = {};

type A = {
    a: string;
    b: boolean;
};

type T = PropsWithChildren<Pick<A, 'b' | 'a'>>;

export default function TestMorph({}: Props) {
    const {t} = useTranslation();

    // @ts-expect-error undefined
    if (e.key === 'Enter') {
    }
    // @ts-expect-error undefined
    if (key === 'Enter') {
    }
    // @ts-expect-error undefined
    if (typeof a === 'undefined') {
    }

    if (t.hasOwnProperty('ws')) {
        console.log('Bonjour');
    }

    const _a = {
        'Hello': 'you!',
        'Hello2': 'you!',
        ['Hello3']: 'you!',
        'Content-Type': 'the value',
        'key': 'untranslated_key',
        'sub': {
            Yeah: 'yo!',
            Yeah2: 'yo!',
            ['Yeah3']: 'yo!',
            key: 'untranslated_key',
        },
    };

    const eventHandler = (_e: HTMLElementEventMap['scroll']) => {
        const data = {
            foo: 'bar',
        };

        const otherData = {
            foo: 'bar',
        };

        console.log('debug', data['foo'], 'XX');
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
            <Button variant={'text'}>submit</Button>
            <Trans>Already translated</Trans>
            <Trans>
                Already <b>bold</b>.
            </Trans>
            <TextField name={'toto'} />
        </>
    );
}
