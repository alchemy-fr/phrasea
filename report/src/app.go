package main

import (
	"fmt"
	"context"
	"github.com/julienschmidt/httprouter"
	"github.com/jackc/pgx/v4"
	"log"
	"net/http"
	"os"
)

var conn *pgx.Conn

func indexHandler(w http.ResponseWriter, r *http.Request, ps httprouter.Params) {
    header := w.Header()
    header.Set("Content-Type", "application/json")
	fmt.Fprintf(w, "true")

	addAction(ps.ByName("action"))
}

func addAction(action string) error {
	_, err := conn.Exec(context.Background(), "INSERT INTO logs(action) values($1)", action)
	return err
}

func main() {
    connStr := fmt.Sprintf("host=db port=5432 user=%s password=%s dbname=%s sslmode=disable",
        os.Getenv("POSTGRES_USER"), os.Getenv("POSTGRES_PASSWORD"), os.Getenv("POSTGRES_DATABASE"))

    var err error
    conn, err = pgx.Connect(context.Background(), connStr)
    if err != nil {
        fmt.Fprintf(os.Stderr, "Unable to connection to database: %v\n", err)
        os.Exit(1)
    }
    defer conn.Close(context.Background())
    fmt.Println("Successfully connected!")

	router := httprouter.New()
	router.POST("/", indexHandler)

	// print env
	env := os.Getenv("APP_ENV")
	if env == "production" {
		log.Println("Running api server in production mode")
	} else {
		log.Println("Running api server in dev mode")
	}

	http.ListenAndServe(":80", router)
}

func checkError(err error) {
    if err != nil {
        panic(err)
    }
}
