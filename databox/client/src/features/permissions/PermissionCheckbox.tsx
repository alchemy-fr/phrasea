import {ComponentProps} from 'react';
import {Checkbox} from '@alchemy/next-ui/ui/controls';
import {Spinner} from '@alchemy/next-ui/ui/loader';

/**
 * Checkbox of a permission table, replaced by a spinner of the same size while
 * its change is saved
 */
export function PermissionCheckbox({
    saving,
    ...props
}: {saving?: boolean} & ComponentProps<typeof Checkbox>) {
    if (saving) {
        return <Spinner className="mx-auto block size-4" />;
    }

    return <Checkbox className="mx-auto" {...props} />;
}
