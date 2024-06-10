export type ExposePublication = {
    id: string;
    title: string;
    slug?: string | null | undefined;
    description: string;
    profile?: string | null | undefined;
    parent?: string | null | undefined;
    enabled: boolean;
}

export type ExposeProfile = {
    id: string;
    name: string;
}
