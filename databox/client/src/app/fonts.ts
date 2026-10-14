import {
    Inter,
    JetBrains_Mono,
    Lora,
    Manrope,
    Nunito,
    Open_Sans,
    Raleway,
    Roboto,
    Source_Serif_4,
    Work_Sans,
} from 'next/font/google';

/**
 * The Google fonts the themes are built with, self-hosted by Next (nothing
 * is fetched from Google at runtime) and exposed as custom properties on
 * <html>: `app/globals.css` picks one per preset, and the organisation theme
 * can choose one too (see features/theme/fonts.ts).
 *
 * They are declared but never preloaded: a page uses one of them at most, and
 * the browser only downloads the faces the stylesheet actually matches. Every
 * call is written out in full — the loader reads these arguments statically,
 * a shared options object would not be understood.
 */
const inter = Inter({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-inter',
});

const roboto = Roboto({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-roboto',
});

const openSans = Open_Sans({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-open-sans',
});

const nunito = Nunito({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-nunito',
});

const manrope = Manrope({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-manrope',
});

const workSans = Work_Sans({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-work-sans',
});

const raleway = Raleway({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-raleway',
});

const lora = Lora({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-lora',
});

const sourceSerif = Source_Serif_4({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-source-serif',
});

const jetBrainsMono = JetBrains_Mono({
    subsets: ['latin', 'latin-ext'],
    display: 'swap',
    preload: false,
    variable: '--font-jetbrains-mono',
});

/** Goes on <html>: defines every `--font-…` the themes may refer to */
export const fontVariables = [
    inter,
    roboto,
    openSans,
    nunito,
    manrope,
    workSans,
    raleway,
    lora,
    sourceSerif,
    jetBrainsMono,
]
    .map(font => font.variable)
    .join(' ');
