export type S3AmqpConfig = {
    workspaceSlug: string;
    amqp: {
        dsn: string;
    };
    s3: {
        endpoint: string;
        bucketNames: string;
        accessKey: string;
        secretKey: string;
    };
};
