import {ErrorPage} from '@alchemy/phrasea-ui';

type Props = {
    error: any;
};

export function DefaultErrorComponent({error}: Props) {
    // eslint-disable-next-line no-console
    console.trace(error);

    return <ErrorPage error={error} />;
}
