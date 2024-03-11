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
};

export type StateSetter<T> = (handler: T | ((prev: T) => T)) => void;

export type UploadedFile = {
    id: string;
} & File;

export type FormData = Record<string, any>;
