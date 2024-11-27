import {Entity} from "../../../../types.ts";

export type ExposePublication = {
    title: string;
    slug?: string | null | undefined;
    description: string;
    profile?: string | null | undefined;
    parent?: string | null | undefined;
    enabled: boolean;
} & Entity;

export type ExposeProfile = {
    name: string;
} & Entity;
