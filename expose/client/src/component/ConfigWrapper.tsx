import React from 'react';
import config from '../config';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import apiClient from '../lib/api-client';
import Root from './Root.tsx';

export default function ConfigWrapper() {
    const [loaded, setLoaded] = React.useState(false);

    if (!loaded) {
        apiClient.get(`/config`).then(({data}) => {
            Object.keys(data).forEach(k => {
                // @ts-expect-error bypass readonly
                config[k] = data[k];
            });

            setLoaded(true);
        });

        return <FullPageLoader />;
    }

    return <Root />;
}
