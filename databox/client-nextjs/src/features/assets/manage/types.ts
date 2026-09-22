import type {Asset} from '@/types/api';

/**
 * Props of the asset tabs, rendered in the side panel of the asset view
 * (see `AssetPanel`).
 */
export type AssetTabProps = {asset: Asset; refresh: () => void};
