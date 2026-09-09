import type {Metadata, Viewport} from 'next';
import {connection} from 'next/server';
import {cookies} from 'next/headers';
import './globals.css';
import {getServerConfig} from '@/lib/config/server';
import {Providers} from './providers';
import {defaultLanguage, isSupportedLanguage, LANG_COOKIE} from '@/i18n/config';

export const metadata: Metadata = {
    title: {
        default: 'Databox',
        template: '%s · Databox',
    },
    description: 'Phrasea Databox - Modern DAM',
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
    const config = getServerConfig();
    const cookieLang = (await cookies()).get(LANG_COOKIE)?.value;
    const language = isSupportedLanguage(cookieLang)
        ? cookieLang
        : defaultLanguage;

    return (
        <html lang={language} suppressHydrationWarning>
            <body className="h-full overflow-hidden">
                <Providers config={config} language={language}>
                    {children}
                </Providers>
            </body>
        </html>
    );
}
