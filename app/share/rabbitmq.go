package share

import (
    "os"
    "time"
    "log"
    "fmt"
    "github.com/streadway/amqp"
    "encoding/json"
)

//configuration RabbitMQ
var (
    ch *amqp.Channel
    conn *amqp.Connection
)

func init() {
    // Подключаемся к RabbitMQ
    config := amqp.Config{
        Heartbeat: 10 * time.Second,    // Интервал heartbeat
        Dial: amqp.DefaultDial(30 * time.Second), // Keepalive
    }
    var err error
    conn, err = amqp.DialConfig(fmt.Sprintf(
        "amqp://%s:%s@%s:%s/%s",
        os.Getenv("RABBITMQ_USER"),
        os.Getenv("RABBITMQ_PASS"),
        os.Getenv("RABBITMQ_HOST"),
        os.Getenv("RABBITMQ_PORT"),
        os.Getenv("RABBITMQ_VHOST"),
    ), config)
    failOnError(err, "Failed to connect to RabbitMQ")
    

    // Открываем канал
    ch, err = conn.Channel()
    failOnError(err, "Failed to open a channel")
    

    // Устанавливаем prefetchCount на 1
    err = ch.Qos(
        1,     // prefetchCount
        0,     // prefetchSize
        false, // global
    )
    failOnError(err, "Failed to set QoS")
}

func CloseConnection() {
    defer conn.Close()
    defer ch.Close()
}

func DeclarateQueue(queueName string) {
    // Объявляем очередь, из которой будем читать сообщения
    _, err := ch.QueueDeclare(
        queueName, // имя очереди
        true,   // долговечность
        false,   // автоудаление
        false,   // эксклюзивность
        false,   // без ожидания
        nil,     // аргументы
    )
    failOnError(err, "Failed declare queue")
}

func failOnError(err error, msg string) {
    if err != nil {
        time.Sleep(30 * time.Second)
        log.Fatalf("%s: %s", msg, err)
    }
}


func RabbitConnect(queueFrom, queueTo string, callback func(video Video) (Video, error)) {
    // Подписываемся на сообщения в очереди
    msgs, err := ch.Consume(
        queueFrom, // имя очереди
        "",     // имя потребителя
        false,   // автоподтверждение
        false,  // эксклюзивность
        false,  // не локально
        false,  // без ожидания
        nil,    // аргументы
    )
    failOnError(err, "Failed to register a consumer")

    // Канал для завершения работы
    forever := make(chan bool)

    go func() {
        for d := range msgs {
            var video Video

            err := json.Unmarshal(d.Body, &video)
            if err != nil {
                log.Printf("Error decoding JSON: %s", err)
                continue
            }

            // Обработка сообщения
            video, err = callback(video)
            if err != nil {
                log.Printf("Error: %s", err)
                err = d.Nack(false, true)
                failOnError(err, "Failed to nack the message")
                continue
            }

            if(queueTo != "") {
                PushMessage(queueTo, video)
            }

            // Подтверждение обработки сообщения
            err = d.Ack(false)
            failOnError(err, "Failed to acknowledge the message")    
        }
    }()

    log.Printf(" [*] Waiting for messages. To exit press CTRL+C")
    <-forever
}



func PushMessage(queueTo string, video Video) {

    // Сериализуем структуру в JSON
    body, err := json.Marshal(video)
    failOnError(err, "Failed to marshal JSON")

    err = ch.Publish(
    "",                   // обменник
    queueTo, // ключ маршрутизации (имя очереди)
    false,                // обязательное
    false,                // немедленное
    amqp.Publishing{
        DeliveryMode: amqp.Persistent,
        ContentType: "application/json",
        Body:        body,
    })
    failOnError(err, "Failed to publish a message to destination queue")
}