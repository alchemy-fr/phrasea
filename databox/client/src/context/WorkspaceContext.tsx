import React from 'react';

export type TWorkspaceContextContext = {
    workspaceId: string;
};

export const WorkspaceContext =
    React.createContext<TWorkspaceContextContext | null>(null);
