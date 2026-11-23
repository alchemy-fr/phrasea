import type {Metadata, Viewport} from 'next';
import {connection} from 'next/server';
import {cookies, headers} from 'next/headers';
import './globals.css';
import {getServerConfig} from '@/lib/config/server';
import {getStackConfig} from '@alchemy/next-core/config/stackConfig';
import {compileStackTheme} from '@alchemy/next-theme/compile';
import {Providers} from './providers';
import {NONCE_HEADER} from '@alchemy/next-core/security/csp';
import {languages} from '@/i18n/config';
import {embeddedScript} from '@/lib/embedded';
import {fontVariables} from '@alchemy/next-theme/googleFonts';

export const metadata: Metadata = {
    title: {
        default: 'Dashboard',
        template: '%s · Dashboard',
    },
    description: 'Phrasea Dashboard',
    icons: {
        icon: [
            {url: '/favicon.ico', sizes: 'any'},
            {url: '/favicon.svg', type: 'image/svg+xml'},
        ],
        apple: '/apple-touch-icon.png',
    },
};

export const viewport: Viewport = {
    width: 'device-width',
    initialScale: 1,
    themeColor: [
        {media: '(prefers-color-scheme: light)', color: '#fafafa'},
        {media: '(prefers-color-scheme: dark)', color: '#1b1c22'},
    ],
};

export default async function RootLayout({
    children,
}: Readonly<{children: React.ReactNode}>) {
    // Opt into request-time rendering: the configuration comes from the
    // container environment, never from the build.
    await connection();
    // The stack configuration (configurator entries pushed to the bucket)
    // carries the logo and the organisation theme.
    const stackConfig = await getStackConfig();
    const config = getServerConfig(stackConfig);
    const compiledTheme = compileStackTheme(stackConfig);
    if (compiledTheme) {
        config.theme = compiledTheme.meta;
    }
    const language = languages.resolveLanguage(
        (await cookies()).get(languages.cookie)?.value
    );
    // Set by the proxy (src/proxy.ts) with the Content Security Policy
    const nonce = (await headers()).get(NONCE_HEADER) ?? undefined;

    return (
        <html
            lang={language}
            // Defines every `--font-…` the themes pick from
            className={fontVariables}
            suppressHydrationWarning
        >
            <body className="min-h-[100dvh]">
                <script
                    nonce={nonce}
                    // The browser hides the nonce from the DOM once parsed
                    suppressHydrationWarning
                    dangerouslySetInnerHTML={{__html: embeddedScript}}
                />
                {/* Rendered in the body: a <head> written by hand in the
                    root layout does not hydrate (Next manages it). */}
                {compiledTheme ? (
                    <style
                        id="client-theme"
                        dangerouslySetInnerHTML={{__html: compiledTheme.css}}
                    />
                ) : null}
                {/* The fonts uploaded with the theme are served apart and
                    cached for good: addressed by the digest of their rules.
                    React hoists it into <head>. */}
                {compiledTheme?.fontsCss ? (
                    <link
                        rel="stylesheet"
                        precedence="default"
                        href={`/api/theme-fonts?v=${compiledTheme.fontsHash}`}
                    />
                ) : null}
                <Providers config={config} language={language} nonce={nonce}>
                    {children}
                </Providers>
            </body>
        </html>
    );
}
