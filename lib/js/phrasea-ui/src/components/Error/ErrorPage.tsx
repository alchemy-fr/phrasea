import ErrorLayout from "./ErrorLayout";

type Props = {
    error?: any;
};

export default function ErrorPage({
    error
}: Props) {
    return <ErrorLayout
        title={'Oops! An error has occurred'}
        description={`We are working to resolve the issue.`}
    >
        <div style={{
            fontSize: 20,
            color: 'red',
        }}>
            {error?.toString()}
        </div>
    </ErrorLayout>
}
