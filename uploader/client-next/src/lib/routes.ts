/** Every URL of the application, in one place */
export const routes = {
    home: () => '/',
    upload: (targetId: string) => `/upload/${targetId}`,
    download: (targetId: string) => `/download/${targetId}`,
    formSchemas: () => '/admin/form-schemas',
    formSchemaCreate: () => '/admin/form-schemas/create',
    formSchemaEdit: (id: string) => `/admin/form-schemas/${id}/edit`,
    targetParams: () => '/admin/target-param',
    targetParamsCreate: () => '/admin/target-param/create',
    targetParamsEdit: (id: string) => `/admin/target-param/${id}/edit`,
};
