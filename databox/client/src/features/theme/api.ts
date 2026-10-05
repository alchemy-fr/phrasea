import {api} from '@/lib/api/http';
import type {ClientTheme} from './customTheme';

/**
 * The organisation theme as stored by the API (the `databox.theme` entry of
 * the stack configuration). Only the editor needs it: users get the compiled
 * theme with the page (see compile.ts).
 */

export function getClientTheme(): Promise<ClientTheme | null> {
    return api.get<ClientTheme | null>('/client-theme', {anonymous: true});
}

export function putClientTheme(theme: ClientTheme): Promise<ClientTheme> {
    return api.put<ClientTheme>('/client-theme', theme);
}

export function deleteClientTheme(): Promise<void> {
    return api.delete('/client-theme');
}
