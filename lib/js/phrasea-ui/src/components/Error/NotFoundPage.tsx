import ErrorLayout from './ErrorLayout';

type Props = {};

export default function NotFoundPage({}: Props) {
    return <ErrorLayout title={'404'} description={`Page not found.`} />;
}
