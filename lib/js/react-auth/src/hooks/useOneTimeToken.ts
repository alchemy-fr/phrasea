import React from "react";
import {HttpClient} from "@alchemy/api";
import {getOneTimeToken} from "@alchemy/auth";

export function useOneTimeToken(client: HttpClient) {
    const [loading, setLoading] = React.useState(false);

    const getToken = async () => {
        setLoading(true);

        try {
            return await getOneTimeToken(client);
        } finally {
            setLoading(false);
        }
    }

    return {
        getToken,
        loading,
    }
}
