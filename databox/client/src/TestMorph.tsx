import {Typography} from '@mui/material';

type Props = {};

export default function TestMorph({}: Props) {
    // @ts-expect-error Unused
    const _a = {
        Hello: 'you!',
    };

    return (
        <>
            <Typography variant={'h2'}>{'Tag rules'}</Typography>
            OK
            <div title={'The title'} alt={`Template Literal`}></div>
            <Trans>Already translated</Trans>
            <Trans>
                Already <b>bold</b>.
            </Trans>
        </>
    );
}
