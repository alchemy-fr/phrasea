type Props = {};

export default function TestMorph({}: Props) {
    // @ts-expect-error Unused
    const _a = {
        Hello: 'you!',
    };

    return (
        <>
            OK
            <div title={'The title'} alt={`Template Literal`}></div>
            <Trans>Already translated</Trans>
        </>
    );
}
