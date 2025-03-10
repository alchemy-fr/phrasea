export type AQLQuery = {
    id: string;
    query: string;
    disabled?: boolean;
};

export type AQLQueries = AQLQuery[];
