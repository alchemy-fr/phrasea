export type AnalyticsConfig = {
    matomo?: {
        baseUrl: string;
        siteId: string;
    };
};

export interface WindowConfigBase {
    globalCSS?: Readonly<string | undefined>;
    autoConnectIdP: Readonly<string | undefined>;
    sentryDsn?: Readonly<string | undefined>;
    sentryEnvironment: Readonly<string>;
    sentryRelease: Readonly<string>;
    appId: Readonly<string>;
    appName: string;
    locales: Readonly<string[]>;
    baseUrl: Readonly<string>;
    keycloakUrl: Readonly<string>;
    realmName: Readonly<string>;
    clientId: Readonly<string>;
    displayServicesMenu: Readonly<boolean>;
    dashboardBaseUrl: Readonly<string>;
    devMode: Readonly<boolean>;
    analytics?: AnalyticsConfig;
    pusherHost?: Readonly<string>;
    pusherKey?: Readonly<string>;
    logo?: {
        src?: string;
        style?: string;
    };
    novuAppIdentifier?: Readonly<string>;
    novuSocketUrl?: Readonly<string>;
    novuApiUrl?: Readonly<string>;
}

declare global {
    interface WindowConfig extends WindowConfigBase {}

    interface Window {
        config: WindowConfig;
    }
}

export type SentryConfig = Pick<
    WindowConfig,
    'sentryDsn' | 'sentryEnvironment' | 'sentryRelease' | 'appId' | 'appName'
>;

export type RegisterWebSocketOptions = {
    onError: (error: any) => void;
};
export type PusherEventCallback = (data: any) => void;
export type UnregisterWebSocket = () => void;

export enum FileTypeEnum {
    Unknown,
    Document,
    Audio,
    Video,
    Image,
}

export type Dimensions = {
    width: number;
    height?: number;
};

export type StrictDimensions = {
    width: number;
    height: number;
};
