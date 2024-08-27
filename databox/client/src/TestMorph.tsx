import {Typography} from '@mui/material';
import {Trans} from "react-i18next";

type Props = {};

export default function TestMorph({}: Props) {
    // @ts-expect-error Unused
    const _a = {
        Hello: 'you!',
        key: 'untranslated_key',
    };

    return (
        <>
            <Typography variant={'h2'}>{'Tag rules'}</Typography>
            OK
            <div title={'The title'} data-alt={`Template Literal`}></div>
            <Trans>Already translated</Trans>
            <Trans>
                Already <b>bold</b>.
            </Trans>
        </>
    );
}
