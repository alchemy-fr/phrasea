export type UploaderUser = {
    user_id: string;
    email: string;
    username: string;
    permissions: {
        form_schema: boolean;
        target_data: boolean;
    };
}
