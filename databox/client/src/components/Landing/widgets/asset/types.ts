export type AssetWidgetProps = {
    assetId?: string;
    maxWidth: number;
    maxHeight: number;
    borderRadius?: number;
    gap: number;
    openOnClick: boolean;
    imagePosition: Position;
};

export type Position = 'left' | 'right' | 'top' | 'bottom';
