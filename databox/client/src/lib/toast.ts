import {Id, toast, ToastOptions, UpdateOptions} from 'react-toastify';

export enum ToastProgressStatus {
    InProgress = 0,
    Done = 1,
    Error = 2,
}

type ToastProgressProps = {
    status: ToastProgressStatus;
    progress: number | undefined;
    render: UpdateOptions['render'];
    onClose: () => void;
} & ToastOptions;

export function upsertProgressiveToast(
    id: Id | null | undefined,
    options: ToastProgressProps
): Id {
    const {render, autoClose, onClose, progress, status, ...rest} = options;

    const isLoading = status === ToastProgressStatus.InProgress;

    const common: ToastOptions = {
        ...rest,
        // https://github.com/fkhadra/react-toastify/issues/1116
        progress: isLoading ? (progress === 1 ? 0.99 : progress) : undefined,
        isLoading,
        closeButton: !isLoading,
        autoClose: isLoading ? false : autoClose,
        type:
            status === ToastProgressStatus.Error
                ? 'error'
                : status === ToastProgressStatus.Done
                  ? 'success'
                  : 'info',
    };

    if (id) {
        toast.update(id, {
            ...common,
            render,
        });

        return id;
    } else {
        return toast.info(render, {
            ...common,
            onClose,
        });
    }
}
