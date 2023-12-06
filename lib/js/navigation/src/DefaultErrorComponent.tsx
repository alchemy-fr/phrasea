type Props = {
    error: any;
};

export function DefaultErrorComponent({
    error,
}: Props) {
    console.trace(error);

    return <div>
        {error.toString()}
    </div>
}
