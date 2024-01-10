export function all(fns) {
    const results = [];

    fns.reduce((prev, fn) => {
        fn().then(result => results.push(result))
        return results
    }, results)

    return cy.wrap(results);
}
