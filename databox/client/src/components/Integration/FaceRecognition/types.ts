export type FaceBox = {
    x: number;
    y: number;
    w: number;
    h: number;
};

export enum FaceIdentityOrigin {
    User = 'user',
    Auto = 'auto',
}

export type DetectedFace = {
    id: string;
    box: FaceBox;
    confidence: number;
    identity: string | null;
    identityConfidence: number | null;
    identityOrigin: FaceIdentityOrigin | null;
    age?: number | null;
    gender?: string | null;
};

export type FacesData = {
    faces: DetectedFace[];
};
