/**
 * Errors of the automation form, by field and by action.
 */
export type AutomationFormErrors = {
    fields: Record<string, string>;
    actions: Record<number, Record<string, string>>;
};

/**
 * Splits the constraint violations of the API ("actions[1].config.collectionId")
 * by field and by action.
 */
export function mapViolations(
    violations: {propertyPath?: string; message?: string}[]
): AutomationFormErrors {
    const errors: AutomationFormErrors = {fields: {}, actions: {}};
    for (const v of violations) {
        const path = v.propertyPath ?? '';
        const message = v.message ?? '';
        const m = /^actions\[(\d+)]\.(?:config\.)?(.+)$/.exec(path);
        if (m) {
            const i = parseInt(m[1], 10);
            errors.actions[i] = {...errors.actions[i], [m[2]]: message};
        } else {
            errors.fields[path] = message;
        }
    }

    return errors;
}
