type Props = {
    data: Record<string, any>;
};

export default function JobData({data}: Props) {
    return <pre className={'job-data'}>{JSON.stringify(data, null, 4)}</pre>;
}
