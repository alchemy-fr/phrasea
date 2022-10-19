import React, {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import '../locales/i18n';
import {baseUrl} from "../lib/location";

type Provider = {
    name: string,
    title: string,
    type: "oauth" | "saml",
    logoUrl?: string;
}

type Props = {
    displayIdPTitle?: boolean;
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
                                              displayIdPTitle = true,
                                          }: Props) {
    const {t} = useTranslation();

    const redirectUriGenerator = useMemo(() => typeof redirectUri === 'function'
        ? redirectUri
        : (provider: Provider) => `${redirectUri || `${baseUrl}/auth`}/${provider.name}`, [redirectUri, baseUrl]);


    return <div className={'identity-providers'} style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        flexWrap: 'wrap',
    }}>
        {providers.map((provider) => {
            const redirectUri = redirectUriGenerator(provider);
            const authorizeUrl = `${authBaseUrl}/${provider.type}/${provider.name}/authorize?redirect_uri=${encodeURIComponent(redirectUri)}&client_id=${authClientId}`;

            return <div
                className="identity-provider"
                style={{
                    margin: 10,
                    textAlign: 'center',
                }}
            >
                <a
                    className={'btn btn-light'}
                    href={authorizeUrl}
                    title={provider.title}
                >
                    {provider.logoUrl && <div style={{
                        marginBottom: displayIdPTitle ? 5 : 0,
                    }}>
                        <img
                            src={provider.logoUrl}
                            alt={provider.title}
                            style={{
                                maxWidth: 50,
                                maxHeight: 50,
                            }}
                        />
                    </div>}

                    {(displayIdPTitle || !provider.logoUrl) && <div>
                        {provider.title}
                    </div>}
                </a>
            </div>
        })}
    </div>
}
