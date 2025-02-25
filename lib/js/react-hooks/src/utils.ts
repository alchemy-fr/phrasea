export function propsAreSame(a: any[], b: any[]): boolean {
    for (const i in a) {
        if (b[i] !== a[i]) {
            return false;
        }
    }

    return true;
}
