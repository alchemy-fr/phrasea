import FormDialog from '../Dialog/FormDialog';
import {BasketForm} from '../Form/BasketForm';
import {Basket} from '../../types';
import {useFormSubmit} from '@alchemy/api';
import {postBasket} from '../../api/basket';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPromptOutsideRouter} from '../Dialog/Tabbed/FormTab.tsx';
import {useBasketStore} from "../../store/basketStore.ts";

type Props = {} & StackedModalProps;

export default function CreateBasket({
    modalIndex,
    open,
}: Props) {
    const {closeModal} = useModals();
    const {t} = useTranslation();
    const addBasket = useBasketStore(state => state.addBasket);

    const usedFormSubmit = useFormSubmit<Basket>({
        defaultValues: {
            title: '',
        },
        onSubmit: async (data: Basket) => {
            return await postBasket(data);
        },
        onSuccess: (data) => {
            toast.success(
                t(
                    'form.basket_create.success',
                    'Basket created!'
                ) as string
            );
            addBasket(data);
            closeModal();
        },
    });

    const {submitting, remoteErrors, forbidNavigation} = usedFormSubmit;
    useDirtyFormPromptOutsideRouter(forbidNavigation);
    const formId = 'create-basket';

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={t('form.basket_create.title', 'Create Basket')}
            formId={formId}
            loading={submitting}
            errors={remoteErrors}
            open={open}
        >
            <BasketForm formId={formId} usedFormSubmit={usedFormSubmit}/>
        </FormDialog>
    );
}
