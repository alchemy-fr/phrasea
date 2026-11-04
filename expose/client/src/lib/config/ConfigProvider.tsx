'use client';

import {createConfig} from '@alchemy/next-core/config/createConfig';
import type {AppConfig} from './types';

const config = createConfig<AppConfig>();

export const ConfigProvider = config.ConfigProvider;
export const useConfig = config.useConfig;
/** Imperative accessor for non-React modules (API client, auth, realtime) */
export const getConfig = config.getConfig;
