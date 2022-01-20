import amqplib, {Channel, Connection} from 'amqplib';
import {getEnv} from "./env";

type OnEventCallback = (event: string) => Promise<void>;

const retryDelay = 5000;

export function listenToQueue(
    dsn: string,
    queueName: string,
    callback: OnEventCallback
): void {
    console.info('AMQP: Connecting...');

    const connect = () => {
        return amqplib
            .connect(dsn)
            .then(function (conn) {
                process.once('SIGINT', conn.close.bind(conn));
                console.info('AMQP: Connected!');
                return conn.createChannel().then(c => ({
                    connection: conn,
                    channel: c,
                }));
            }, (e: any) => {
                console.error('AMQP Error:', e.message);
                console.info(`Wait ${retryDelay}ms then retry...`);

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
                console.log('AMQP: Channel closed, closing connection...');
                connection.close();

                listenToQueue(dsn, queueName, callback);
            });

            console.debug('AMQP: prefetching channel...');
            await channel.prefetch(parseInt(getEnv('DATABOX_MAX_CONCURRENCY', '2')));

            return channel;
        })
        .then(function (ch) {
            return ch.assertQueue(queueName)
                .then(function (ok) {
                    console.debug('AMQP: wait for events...');
                    return ch
                        .consume(queueName, function (msg) {
                            if (msg !== null) {
                                callback(msg.content.toString())
                                    .then(() => {
                                        // TODO
                                        ch.ack(msg)
                                    })
                                .catch(() => ch.reject(msg, false));
                            }
                        });
                });
        }).catch(console.warn);
}

