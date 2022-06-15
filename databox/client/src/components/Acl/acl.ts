
export const aclPermissions: { [key: string]: number } = {
    VIEW: 1,
    SHARE: 256,
    CREATE: 2,
    EDIT: 4,
    DELETE: 8,
    UNDELETE: 16,
    OPERATOR: 32,
    MASTER: 64,
    OWNER: 128,
}
