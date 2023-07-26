import React, {useState} from 'react';
import App from "./App";
import config from "../lib/config";
import FullPageLoader from "./FullPageLoader";
import apiClient from "../lib/api-client";

export default function ConfigWrapper() {
    const [loaded, setLoaded] = useState(false);

    if (!loaded) {
        apiClient
            .get(`/config`)
            .then((res) => {
                Object.keys(res).forEach(k => {
                    config.set(k, res[k]);
                });

                setLoaded(true);
            });

        return <FullPageLoader/>
    }

    return <App/>
}
