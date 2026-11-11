'use client';

import {useQuery} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {getAllTargets} from '@/lib/api/uploader';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';

/** One of the targets the user may upload to */
export function TargetSelect({
    id,
    value,
    onChange,
    disabled,
}: {
    id?: string;
    value: string | undefined;
    onChange: (targetId: string) => void;
    disabled?: boolean;
}) {
    const {t} = useTranslation();
    const {data, isPending} = useQuery({
        queryKey: targetsQueryKey,
        queryFn: getAllTargets,
    });

    return (
        <SimpleSelect
            id={id}
            value={value || undefined}
            onValueChange={onChange}
            disabled={disabled || isPending}
            placeholder={t(
                'admin.target_select.placeholder',
                'Choose a target'
            )}
            options={(data ?? []).map(target => ({
                value: target.id,
                label: target.name,
            }))}
        />
    );
}
