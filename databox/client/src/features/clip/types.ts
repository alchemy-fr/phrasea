export type {ClipCrop, ClipTimeRange, MediaClip} from '@/types/api';

/** What a clip can be applied to, depending on the kind of file */
export type ClipCapabilities = {
    timeRange: boolean;
    crop: boolean;
};
