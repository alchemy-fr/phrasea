import amqplib from 'amqplib';

type Callback = () => void;
type OnEventCallback = (event: string) => Promise<void>;

export function listenToQueue(
    dsn: string,
    queueName: string,
    callback: OnEventCallback,
    onReady: Callback
): void {
    console.log('Connecting to AMQP...');
    amqplib
        .connect(dsn)
        .then(function (conn) {
            console.log('Connected to AMQP!');
            return conn.createChannel();
        }).then(function (ch) {
        return ch.assertQueue(queueName).then(function (ok) {
            onReady();
            return ch.consume(queueName, function (msg) {
                if (msg !== null) {
                    callback(msg.content.toString())
                        .then(() => {
                            // TODO
                            // ch.ack(msg)
                        });
                }
            });
        });
    }).catch(console.warn);
}

