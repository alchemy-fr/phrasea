export type AQLQuery = {
    id: string;
    query: string;
    disabled?: boolean;
    inversed?: boolean;
};

export type AQLQueries = AQLQuery[];
