export type BoundingBox = {
    Width: number;
    Height: number;
    Top: number;
    Left: number;
}

export enum DetectType {
    Labels = 'labels',
    Texts = 'texts',
    Faces = 'faces',
}

export type Instance = {
    BoundingBox: BoundingBox;
    Confidence: number;
};

export type LabelsData = {
    Labels: ImageLabel[];
};

export type TextsData = {
    TextDetections: TextDetection[];
};

export type FacesData = {
    FaceDetails: FaceDetail[];
};

export type TValueConfidence<T> = {
    Value: T;
    Confidence: number;
}

export type FaceDetail = {
    Confidence: number;
    BoundingBox: BoundingBox;
    AgeRange: {
        Low: number;
        High: number;
    };
    Smile: TValueConfidence<boolean>;
    Eyeglasses: TValueConfidence<boolean>;
    Gender: TValueConfidence<"Male" | "Female">;
    Beard: TValueConfidence<boolean>;
    Mustache: TValueConfidence<boolean>;
    EyesOpen: TValueConfidence<boolean>;
    MouthOpen: TValueConfidence<boolean>;
    Emotions: {
        Type: string;
        Confidence: number;
    }[];
};

export type Polygon = {
    X: number;
    Y: number;
}

export type TextDetection = {
    Id: string;
    ParentId: string;
    DetectedText: string;
    Type: "LINE" | "WORD";
    Confidence: number;
    Geometry: {
        BoundingBox: BoundingBox;
    };
    Polygon: Polygon[];
};

export type ImageLabel = {
    Name: string;
    Confidence: number;
    Instances: Instance[];
    Parents: {
        Name: string;
    }[];
};
