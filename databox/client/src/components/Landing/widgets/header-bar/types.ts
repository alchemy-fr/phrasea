export type HeaderBarLink = {
    label: string;
    url: string;
    target?: '_blank' | '_self';
};

export type HeaderBarWidgetProps = {
    title?: string;

    links?: HeaderBarLink[];
    position?: 'fixed' | 'static';
};
