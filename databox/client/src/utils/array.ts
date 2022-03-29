export function isSameArray(a: any[], b: any[]): boolean {
    if (!a)
        return false;

    if (b.length !== a.length)
        return false;

    for (let i = 0, l = b.length; i < l; i++) {
        if (b[i] instanceof Array && a[i] instanceof Array) {
            if (!b[i].equals(a[i]))
                return false;
        } else if (b[i] !== a[i]) {
            return false;
        }
    }
    return true;
}
