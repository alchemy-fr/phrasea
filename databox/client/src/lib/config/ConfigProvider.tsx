'use client';

import {createContext, PropsWithChildren, useContext, useState} from 'react';
import type {AppConfig} from './types';
import {getRuntimeConfig, setRuntimeConfig} from './runtime';

const ConfigContext = createContext<AppConfig | null>(null);

export function ConfigProvider({
    config,
    children,
}: PropsWithChildren<{config: AppConfig}>) {
    // Registered synchronously (state initializer) so that non-React modules
    // (API client, auth) can read it from the very first child effect.
    useState(() => {
        setRuntimeConfig(config);

        return true;
    });

    return (
        <ConfigContext.Provider value={config}>
            {children}
        </ConfigContext.Provider>
    );
}

export function useConfig(): AppConfig {
    const config = useContext(ConfigContext);
    if (!config) {
        throw new Error('useConfig must be used within ConfigProvider');
    }

    return config;
}

/**
 * Imperative accessor for non-React modules (API client, auth, realtime).
 */
export function getConfig(): AppConfig {
    return getRuntimeConfig();
}
