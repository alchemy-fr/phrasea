/**
 * The custom stylesheet of the publication (its CSS and its profile's),
 * served by the API. React hoists it into <head> and removes it with the
 * page.
 */
export function PublicationCss({href}: {href: string | null | undefined}) {
    if (!href) {
        return null;
    }

    return <link rel="stylesheet" href={href} precedence="publication" />;
}
