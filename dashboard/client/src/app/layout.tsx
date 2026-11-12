import type {Metadata, Viewport} from 'next';
import {connection} from 'next/server';
import {cookies} from 'next/headers';
import './globals.css';
import {getServerConfig} from '@/lib/config/server';
import {getStackConfig} from '@alchemy/next-core/config/stackConfig';
import {compileStackTheme} from '@alchemy/next-theme/compile';
import {Providers} from './providers';
import {languages} from '@/i18n/config';
import {embeddedScript} from '@/lib/embedded';
import {fontVariables} from '@alchemy/next-theme/googleFonts';

export const metadata: Metadata = {
    title: {
        default: 'Dashboard',
        template: '%s · Dashboard',
    },
    description: 'Phrasea Dashboard',
    icons: {icon: '/favicon.svg'},
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

    return (
        <html
            lang={language}
            // Defines every `--font-…` the themes pick from
            className={fontVariables}
            suppressHydrationWarning
        >
            <body className="min-h-[100dvh]">
                <script dangerouslySetInnerHTML={{__html: embeddedScript}} />
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
                <Providers config={config} language={language}>
                    {children}
                </Providers>
            </body>
        </html>
    );
}
