/** Every URL of the application, in one place */
export const routes = {
    home: () => '/',
    upload: (targetId: string) => `/upload/${targetId}`,
    download: (targetId: string) => `/download/${targetId}`,
    forms: () => '/admin/forms',
    form: (targetId: string) => `/admin/forms/${targetId}`,
    targetDataList: () => '/admin/target-data',
    targetData: (targetId: string) => `/admin/target-data/${targetId}`,
};
