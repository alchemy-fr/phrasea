import React, {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import '../locales/i18n';
import {baseUrl} from "../lib/location";

type Provider = {
    name: string,
    title: string,
    type: "oauth" | "saml",
}

type Props = {
    providers: Provider[];
    authBaseUrl: string,
    authClientId: string,
    redirectUri?: string | ((provider: Provider) => string) | undefined;
};

export type {Props as IdentityProvidersProps};

export default function IdentityProviders({
                                              providers,
                                              authBaseUrl,
                                              redirectUri,
                                              authClientId,
                                          }: Props) {
    const {t} = useTranslation();

    const redirectUriGenerator = useMemo(() => typeof redirectUri === 'function'
        ? redirectUri
        : (provider: Provider) => `${redirectUri || `${baseUrl}/auth`}/${provider.name}`, [redirectUri, baseUrl]);

    return <>
        {providers.map((provider) => {
            const redirectUri = redirectUriGenerator(provider);
            const authorizeUrl = `${authBaseUrl}/${provider.type}/${provider.name}/authorize?redirect_uri=${encodeURIComponent(redirectUri)}&client_id=${authClientId}`;

            return <div
                key={provider.name}
            >
                <a href={authorizeUrl}>
                    {t('login.idp.connect_with', {
                        defaultValue: `Connect with {{provider}}`,
                        provider: provider.title
                    }) as string}
                </a>
            </div>
        })}
    </>
}
