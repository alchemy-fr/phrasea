export type S3AmqpConfig = {
    amqp: {
        dsn: string;
    };
    "s3": {
        endpoint: string;
        bucketNames: string;
        accessKey: string;
        secretKey: string;
    }
};
