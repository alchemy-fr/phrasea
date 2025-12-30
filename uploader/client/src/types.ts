export type UploaderUser = {
    user_id: string;
    email: string;
    username: string;
    permissions: {
        form_schema: boolean;
        target_data: boolean;
    };
};

export type Target = {
    id: string;
    name: string;
    description?: string;
    capabilities: {
        edit: boolean;
        delete: boolean;
    };
};

export type StateSetter<T> = (handler: T | ((prev: T) => T)) => void;

export type UploadedFile = {
    id: string;
} & File;

export type FormData = Record<string, any>;

export type LiFormSchema = {} & LiFormField;

export type LiFormField = {
    required?: string[];
    propertyOrder?: number;
    widget?: string;
    format?: string;
    defaultValue?: any;
    type?: string;
    allOf?: LiFormField[];
    title?: string;
    description?: string;
    items?: Partial<LiFormField>;
    showLabel?: boolean;
    enum?: string[];
    enum_titles?: string[];
    properties?: Record<string, LiFormField>;
};

export type FormSchema = {
    id: string;
    data: LiFormSchema;
};

export type UploadFormData = Record<string, any>;

export type AbortableFile = {
    file: File;
    abortController: AbortController | null;
    id?: string;
    error?: FileUploadError;
    event?: FileCompleteEvent;
};
export type FileUploadError = string;

export type UploadedAsset = {
    id: string;
};

export type FileCompleteEvent = {
    totalLoaded: number;
    totalSize: number;
    totalPercent: number;
    fileSize: number;
    fileLoaded: number;
    filePercent: number;
    index: number;
    asset: UploadedAsset;
};
export type OnFileCompleteListener = (event: FileCompleteEvent) => void;
