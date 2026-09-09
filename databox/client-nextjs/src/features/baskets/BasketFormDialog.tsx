'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Basket} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {postBasket, putBasket} from '@/lib/api/misc';
import {useBasketStore} from './basketStore';

export function BasketFormDialog({
    open,
    onOpenChange,
    basket,
    onSaved,
}: ModalProps & {basket?: Basket; onSaved?: (basket: Basket) => void}) {
    const {t} = useTranslation();
    const [name, setName] = useState(basket?.name ?? '');
    const [description, setDescription] = useState(basket?.description ?? '');
    const [loading, setLoading] = useState(false);
    const upsert = useBasketStore(s => s.upsert);
    const setCurrent = useBasketStore(s => s.setCurrent);

    const submit = async () => {
        setLoading(true);
        try {
            const saved = basket
                ? await putBasket(basket.id, {name, description})
                : await postBasket({name, description});
            upsert(saved);
            if (!basket) {
                setCurrent(saved);
            }
            toast.success(
                basket
                    ? t('basket.updated', 'Basket updated')
                    : t('basket.created', 'Basket created')
            );
            onSaved?.(saved);
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>
                        {basket
                            ? t('basket.edit.title', 'Edit basket')
                            : t('basket.create', 'Create basket')}
                    </DialogTitle>
                </DialogHeader>
                <DialogBody>
                    <FormRow
                        label={t('common.name', 'Name')}
                        htmlFor="basket-name"
                    >
                        <Input
                            id="basket-name"
                            autoFocus
                            value={name}
                            onChange={e => setName(e.target.value)}
                        />
                    </FormRow>
                    <FormRow
                        label={t('common.description', 'Description')}
                        htmlFor="basket-desc"
                    >
                        <Textarea
                            id="basket-desc"
                            value={description}
                            onChange={e => setDescription(e.target.value)}
                        />
                    </FormRow>
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={!name.trim()}
                        loading={loading}
                    >
                        {t('common.save', 'Save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
