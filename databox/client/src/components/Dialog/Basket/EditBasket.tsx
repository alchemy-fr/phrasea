import {Basket} from '../../../types';
import {useTranslation} from 'react-i18next';
import {toast} from 'react-toastify';
import {useFormSubmit} from '@alchemy/api';
import FormTab from '../Tabbed/FormTab';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import {useInRouterDirtyFormPrompt} from '@alchemy/navigation';
import {useBasketStore} from '../../../store/basketStore.ts';
import {putBasket} from '../../../api/basket.ts';
import {BasketForm} from '../../Form/BasketForm.tsx';

type Props = {
    id: string;
    data: Basket;
} & DialogTabProps;

export default function EditBasket({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();

    const {updateBasket} = useBasketStore();

    const usedFormSubmit = useFormSubmit({
        defaultValues: data,
        onSubmit: async (data: Basket) => {
            return await putBasket(data.id, data);
        },
        onSuccess: data => {
            updateBasket(data);

            toast.success(
                t('form.basket_edit.success', 'Basket edited!') as string
            );
            onClose();
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useInRouterDirtyFormPrompt(t, forbidNavigation);

    const formId = 'edit-basket';

    return (
        <FormTab
            onClose={onClose}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            minHeight={minHeight}
        >
            <BasketForm
                usedFormSubmit={usedFormSubmit}
                data={data}
                formId={formId}
            />
        </FormTab>
    );
}
