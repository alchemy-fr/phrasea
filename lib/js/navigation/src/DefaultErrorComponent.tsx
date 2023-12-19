import {ErrorPage} from '@alchemy/phrasea-ui';

type Props = {
    error: any;
};

export function DefaultErrorComponent({
    error,
}: Props) {
    console.trace(error);

    return <ErrorPage
        error={error}
    />
}
