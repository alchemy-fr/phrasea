export function normalizeDate(
    date: string | null | undefined
): string | null | undefined {
    if (date) {
        return new Date(date).toISOString();
    }

    return date;
}
