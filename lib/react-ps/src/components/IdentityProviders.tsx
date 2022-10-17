import React from 'react';

type Provider = {
    name: string,
    title: string,
    type: "oauth" | "saml",
}

type Props = {
    providers: Provider[];
    authBaseUrl: string,
    authClientId: string,
    redirectUri: string | ((provider: Provider) => string);
};

export default function IdentityProviders({
                                              providers,
                                              authBaseUrl,
                                              redirectUri,
                                              authClientId,
                                          }: Props) {
    const redirectUriGenerator = typeof redirectUri === 'function'
        ? redirectUri
        : (provider) => `${redirectUri || `${authBaseUrl}/auth`}/${provider.name}`;


    return <>
        {providers.map((provider) => {
            const redirectUri = redirectUriGenerator(provider);
            const authorizeUrl = `${authBaseUrl}/${provider.type}/${provider.name}/authorize?redirect_uri=${encodeURIComponent(redirectUri)}&client_id=${authClientId}`;

            return <div
                key={provider.name}
            >
                <a href={authorizeUrl}>Connect with {provider.title}</a>
            </div>
        })}
    </>
}
