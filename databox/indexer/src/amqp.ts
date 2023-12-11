import amqplib, {Channel, Connection} from 'amqplib';
import {getEnv} from "./env";
import {Logger} from "winston";

type OnEventCallback = (event: string) => Promise<void>;

const retryDelay = 5000;

export function listenToQueue(
    dsn: string,
    queueName: string,
    callback: OnEventCallback,
    logger: Logger,
    concurrency: number
): void {
    logger.info(`AMQP: Connecting...`);

    const connect = (): Promise<{
        channel: Channel,
        connection: Connection
    }> => {
        return amqplib
            .connect(dsn)
            .then(function (conn) {
                process.once('SIGINT', conn.close.bind(conn));
                logger.info(`AMQP: Connected!`);
                return conn.createChannel().then(c => ({
                    connection: conn,
                    channel: c,
                }));
            }, (e: any) => {
                logger.error(`AMQP Error: ${e.message}`);
                logger.info(`Wait ${retryDelay}ms then retry...`);

                return new Promise((resolve) => {
                    setTimeout(() => {
                        connect().then(resolve);
                    }, retryDelay);
                })
            });
    }

    connect()
        .then(async ({
                         connection,
                         channel,
                     }: {
            channel: Channel,
            connection: Connection
        }) => {
            channel.on('close', () => {
                logger.info('AMQP: Channel closed, closing connection...');
                connection.close();

                listenToQueue(dsn, queueName, callback, logger, concurrency);
            });

            logger.debug('AMQP: prefetching channel...');
            await channel.prefetch(parseInt(getEnv('DATABOX_CONCURRENCY', '2')!));

            return channel;
        })
        .then(function (ch) {
            return ch.assertQueue(queueName)
                .then(function () {
                    logger.debug('AMQP: wait for events...');
                    return ch
                        .consume(queueName, function (msg) {
                            if (msg) {
                                callback(msg.content.toString())
                                    .then(() => {
                                        ch.ack(msg)
                                    })
                                .catch(() => ch.reject(msg, false));
                            }
                        });
                });
        }).catch(logger.warn);
}

