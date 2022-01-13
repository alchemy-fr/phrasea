import amqplib from 'amqplib';
import {getEnv} from "./env";

type Callback = () => void;
type OnEventCallback = (event: string) => Promise<void>;

export function listenToQueue(
    dsn: string,
    queueName: string,
    callback: OnEventCallback,
    onReady: Callback
): void {
    console.info('Connecting to AMQP...');
    amqplib
        .connect(dsn)
        .then(function (conn) {
            process.once('SIGINT', conn.close.bind(conn));
            console.log('Connected to AMQP!');
            return conn.createChannel();
        })
        .then(async channel => {
            await channel.prefetch(parseInt(getEnv('DATABOX_MAX_CONCURRENCY', '2')));

            return channel;
        })
        .then(function (ch) {
        return ch.assertQueue(queueName).then(function (ok) {
            onReady();
            return ch.consume(queueName, function (msg) {
                if (msg !== null) {
                    callback(msg.content.toString())
                        .then(() => {
                            // TODO
                            // ch.ack(msg)
                        })
                        // .catch(() => ch.reject(msg, false));
                }
            });
        });
    }).catch(console.warn);
}

