/** Every URL of the application, in one place */
export const routes = {
    home: () => '/',
    upload: (targetId: string) => `/upload/${targetId}`,
    download: (targetId: string) => `/download/${targetId}`,
    forms: () => '/admin/forms',
    form: (targetId: string) => `/admin/forms/${targetId}`,
    targetDataList: () => '/admin/target-data',
    targetData: (targetId: string) => `/admin/target-data/${targetId}`,
    uploadLinks: () => '/admin/upload-links',
    /** The page of an upload link: no account needed */
    uploadLink: (token: string) => `/l/${token}`,
};

/** Pages opened with an upload link, by people without an account */
export function isUploadLinkPath(pathname: string): boolean {
    return pathname.startsWith('/l/');
}
