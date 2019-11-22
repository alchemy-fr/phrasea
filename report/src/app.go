package main

import (
	"fmt"
	"github.com/julienschmidt/httprouter"
    "database/sql"
	pq "github.com/lib/pq"
	"log"
	"net/http"
	"os"
)

func indexHandler(w http.ResponseWriter, r *http.Request, _ httprouter.Params) {
    header := w.Header()
    header.Set("Content-Type", "application/json")
	fmt.Fprintf(w, "true")
}

func createDatabase(db Conn, databaseName string) {
    rows, err := db.Query("SELECT 1 FROM pg_database WHERE datname='$1'", databaseName)

    if len(rows) == 0 {
        sqlStatement := fmt.Sprintf("CREATE DATABASE '%s'", databaseName)
        _, err = db.Exec(sqlStatement)
        if err != nil {
          panic(err)
        }
    }
}

func main() {
    psqlInfo := fmt.Sprintf("host=%s port=%d user=%s "+
        "password=%s sslmode=disable",
    os.Getenv("POSTGRES_HOST"), 5432, os.Getenv("POSTGRES_USER"), os.Getenv("POSTGRES_PASSWORD"))
    db, err := sql.Open("postgres", psqlInfo)
    if err != nil {
        panic(err)
    }
    defer db.Close()

    err = db.Ping()
    if err != nil {
        panic(err)
    }
    fmt.Println("Successfully connected!")
    createDatabase(db, os.Getenv("POSTGRES_DATABASE"))

	router := httprouter.New()
	router.GET("/", indexHandler)

	// print env
	env := os.Getenv("APP_ENV")
	if env == "production" {
		log.Println("Running api server in production mode")
	} else {
		log.Println("Running api server in dev mode")
	}

	http.ListenAndServe(":80", router)
}
