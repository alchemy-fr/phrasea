// Standalone module: imported by components (e.g. DisplayProvider) that
// must not drag the layout implementations into their module graph (SSR)
export enum Layout {
    List = 'l',
    Grid = 'g',
}
