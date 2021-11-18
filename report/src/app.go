package main

import (
	"fmt"
	"context"
	"encoding/json"
	"github.com/julienschmidt/httprouter"
	"github.com/jackc/pgx/v4"
	"log"
	"net/http"
	"io/ioutil"
	"os"
)

var conn *pgx.Conn

type logJson struct {
    AppName string
    AppId string
    Action string
    Item string
    User string
    EventDate int
    Payload map[string]string
}

func logHandler(w http.ResponseWriter, req *http.Request, ps httprouter.Params) {
    header := w.Header()
    header.Set("Content-Type", "application/json")

    decoder := json.NewDecoder(req.Body)
    var t logJson
    err := decoder.Decode(&t)
    if err != nil {
        fmt.Fprintf(os.Stderr, "Unable to decode JSON: %v\n", err)
        return
    }
    err = addAction(t)
    if err != nil {
        fmt.Fprintf(os.Stderr, "Unable to persist log: %v\n", err)
        fmt.Fprintf(w, "{\"error\":\"%v\"}", err)
        os.Exit(1)
    }

    fmt.Fprintf(w, "true")
}

func addAction(log logJson) error {
	_, err := conn.Exec(context.Background(),
        "INSERT INTO logs(app_name, app_id, action, item, user_id, payload, event_date) values($1, $2, $3, $4, $5, $6, to_timestamp($7))",
        log.AppName,
        log.AppId,
        log.Action,
        log.Item,
        log.User,
        log.Payload,
        log.EventDate)
	return err
}

func main() {
    connStr := fmt.Sprintf("host=%s port=%s user=%s password=%s dbname=%s sslmode=disable",
        os.Getenv("POSTGRES_HOST"), os.Getenv("POSTGRES_PORT"), os.Getenv("POSTGRES_USER"), os.Getenv("POSTGRES_PASSWORD"), os.Getenv("POSTGRES_DATABASE"))

    var err error
    conn, err = pgx.Connect(context.Background(), connStr)
    if err != nil {
        fmt.Fprintf(os.Stderr, "Unable to connect to database: %v\n", err)
        os.Exit(1)
    }
    defer conn.Close(context.Background())
    fmt.Println("Successfully connected!")

    createSchema()

	router := httprouter.New()
	router.POST("/log", logHandler)

	// print env
	env := os.Getenv("APP_ENV")
	if env == "prod" {
		log.Println("Running api server in production mode")
	} else {
		log.Println("Running api server in dev mode")
	}

	http.ListenAndServe(":80", router)
}

func createSchema() {
    dat, err := ioutil.ReadFile("./structure.sql")
    if err != nil {
        fmt.Fprintf(os.Stderr, "Unable to load file: %v\n", err)
        os.Exit(1)
    }

    fmt.Println("Creating schema....")
    _, err2 := conn.Exec(context.Background(), string(dat))
    if err2 != nil {
        fmt.Fprintf(os.Stderr, "Unable to create schema: %v\n", err2)
        os.Exit(1)
    }

    fmt.Println("Done.")
}
