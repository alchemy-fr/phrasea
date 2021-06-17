import React, {useState} from 'react';
import App from "./App";
import {FullPageLoader} from '@alchemy-fr/phraseanet-react-components';
import apiClient from "../lib/apiClient";
import config from "../lib/config";

export default function ConfigWrapper() {
    const [loaded, setLoaded] = useState(false);

    if (!loaded) {
        apiClient
            .get(`${config.getApiBaseUrl()}/config`)
            .then((res) => {
                Object.keys(res).forEach(k => {
                    config.set(k, res[k]);
                });

                setLoaded(true);
            });

        return <FullPageLoader />
    }

    return <App/>
}
