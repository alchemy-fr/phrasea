'use client';

import {createContext, PropsWithChildren, useContext, useState} from 'react';

/**
 * The runtime configuration of a client, of type `T`. It is resolved on the
 * server at request time from environment variables and injected into the
 * client tree through the ConfigProvider, so the same Docker image can be
 * deployed against any stack.
 */
export function createConfig<T>() {
    const ConfigContext = createContext<T | null>(null);
    let current: T | null = null;

    function ConfigProvider({
        config,
        children,
    }: PropsWithChildren<{config: T}>) {
        // Registered synchronously (state initializer) so that non-React
        // modules (API client, auth) can read it from the very first child
        // effect.
        useState(() => {
            current = config;

            return true;
        });

        return (
            <ConfigContext.Provider value={config}>
                {children}
            </ConfigContext.Provider>
        );
    }

    function useConfig(): T {
        const config = useContext(ConfigContext);
        if (!config) {
            throw new Error('useConfig must be used within ConfigProvider');
        }

        return config;
    }

    /** Imperative accessor for non-React modules (API client, auth…) */
    function getConfig(): T {
        if (!current) {
            throw new Error('Runtime configuration is not available yet');
        }

        return current;
    }

    return {ConfigProvider, useConfig, getConfig};
}
