import React from 'react';
import type {WithTranslations} from '@alchemy/react-form';
import {UseFormSetValue} from 'react-hook-form';

type Props<T extends WithTranslations> = {
    data: T | undefined;
    setValue: UseFormSetValue<T>;
    putFn: (id: string, data: Partial<T>) => Promise<T>;
    setData?: (data: T) => void;
};

export function useCreateSaveTranslations<T extends WithTranslations>({
    data,
    setValue,
    putFn,
    setData,
}: Props<T>) {
    return React.useCallback(
        (field: keyof T) => {
            if (data?.id) {
                return async (d: Partial<T>) => {
                    const r = await putFn(data!.id, d);
                    setValue(field as any, r[field] as any);
                    setValue('translations' as any, r.translations as any);

                    setData?.(r);

                    return r;
                };
            }

            return async (d: Partial<T>) => {
                setValue(field as any, d[field]! as any);
                setValue('translations' as any, d.translations as any);

                return d as T;
            };
        },
        [data?.id, setValue]
    );
}
