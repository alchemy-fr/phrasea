import {Loader2} from 'lucide-react';
import {cn} from '@/lib/utils/cn';

export function Spinner({className}: {className?: string}) {
    return (
        <Loader2
            className={cn(
                'size-5 animate-spin text-muted-foreground',
                className
            )}
        />
    );
}

export function FullPageLoader({label}: {label?: string}) {
    return (
        <div className="flex h-full min-h-[50vh] w-full flex-col items-center justify-center gap-3 text-muted-foreground">
            <Spinner className="size-8" />
            {label ? <div className="text-sm">{label}</div> : null}
        </div>
    );
}

export function InlineLoader({className}: {className?: string}) {
    return (
        <div className={cn('flex items-center justify-center p-4', className)}>
            <Spinner />
        </div>
    );
}
