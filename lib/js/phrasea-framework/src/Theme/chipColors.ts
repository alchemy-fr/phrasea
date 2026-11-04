export type ChipColorName =
    | 'red'
    | 'orange'
    | 'yellow'
    | 'green'
    | 'teal'
    | 'cyan'
    | 'blue'
    | 'indigo'
    | 'purple'
    | 'pink'
    | 'brown'
    | 'grey';

export type ChipColor = {
    main: string;
    contrastText: string;
};

export type ChipColors = Record<ChipColorName, ChipColor>;

/**
 * The 12 chip colors available on every MUI theme (palette.chips).
 * Tenant themes may override them through their theme options.
 */
export const defaultChipColors: ChipColors = {
    red: {main: '#d32f2f', contrastText: '#fff'},
    orange: {main: '#ef6c00', contrastText: '#fff'},
    yellow: {main: '#f9a825', contrastText: 'rgba(0, 0, 0, 0.87)'},
    green: {main: '#388e3c', contrastText: '#fff'},
    teal: {main: '#00796b', contrastText: '#fff'},
    cyan: {main: '#0097a7', contrastText: '#fff'},
    blue: {main: '#1976d2', contrastText: '#fff'},
    indigo: {main: '#3949ab', contrastText: '#fff'},
    purple: {main: '#7b1fa2', contrastText: '#fff'},
    pink: {main: '#c2185b', contrastText: '#fff'},
    brown: {main: '#6d4c41', contrastText: '#fff'},
    grey: {main: '#616161', contrastText: '#fff'},
};

export const chipColorNames = Object.keys(defaultChipColors) as ChipColorName[];

declare module '@mui/material/styles' {
    interface Palette {
        chips: ChipColors;
    }

    interface PaletteOptions {
        chips?: Partial<ChipColors>;
    }
}
