package main

import (
	"fmt"
	"context"
	"encoding/json"
	"github.com/julienschmidt/httprouter"
	"github.com/jackc/pgx/v5"
	"log"
	"net/http"
	"io/ioutil"
	"os"
	"strings"
	"time"
	"sync"
)

var conn *pgx.Conn

type logJson struct {
    AppName string `json:"appName"`
    AppId string `json:"appId"`
    Action string `json:"action"`
    Item string `json:"item"`
    User string `json:"user"`
    EventDate int `json:"eventDate"`
    Payload json.RawMessage `json:"payload"`
}

var logQueue chan logJson
var reconnecting bool
var reconnectMutex sync.Mutex
var failedLogsFile = "failed_logs.jsonl"
var failedLogsMutex sync.Mutex

func main() {
    logQueue = make(chan logJson, 1000)
    conn = connectWithRetries()
    defer conn.Close(context.Background())

    go logWorker()

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

func logHandler(w http.ResponseWriter, req *http.Request, ps httprouter.Params) {
    header := w.Header()
    header.Set("Content-Type", "application/json")

    decoder := json.NewDecoder(req.Body)
    var t logJson
    err := decoder.Decode(&t)
    if err != nil {
        fmt.Fprintf(os.Stderr, "Unable to decode JSON: %v\n", err)
        http.Error(w, "Invalid JSON", http.StatusBadRequest)
        return
    }
    select {
    case logQueue <- t:
        // Enqueued successfully
        w.Write([]byte("true"))
    default:
        // Queue full
        http.Error(w, "Log queue full", http.StatusServiceUnavailable)
    }
}

func logWorker() {
    for logEntry := range logQueue {
        for {
            err := tryAddAction(logEntry)
            if err == nil {
                break
            }
            if isConnectionError(err) {
                triggerReconnect()
                time.Sleep(1 * time.Second)
                continue
            }
            // Non-connection error, persist to file
            fmt.Fprintf(os.Stderr, "Failed to persist log (non-connection error): %v\n", err)
            persistFailedLog(logEntry)
            break
        }
    }
}

func tryAddAction(log logJson) error {
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

func isConnectionError(err error) bool {
    if err == nil {
        return false
    }
    msg := err.Error()
    return strings.Contains(msg, "broken pipe") || strings.Contains(msg, "unexpected EOF") || strings.Contains(msg, "connection reset") || strings.Contains(msg, "connection refused")
}

func persistFailedLog(logEntry logJson) {
    failedLogsMutex.Lock()
    defer failedLogsMutex.Unlock()
    f, err := os.OpenFile(failedLogsFile, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
    if err != nil {
        fmt.Fprintf(os.Stderr, "Unable to open failed logs file: %v\n", err)
        return
    }
    defer f.Close()
    enc := json.NewEncoder(f)
    if err := enc.Encode(logEntry); err != nil {
        fmt.Fprintf(os.Stderr, "Unable to write failed log: %v\n", err)
    }
}

func replayFailedLogs() {
    failedLogsMutex.Lock()
    defer failedLogsMutex.Unlock()
    data, err := os.ReadFile(failedLogsFile)
    if err != nil {
        if os.IsNotExist(err) {
            return // No failed logs
        }
        fmt.Fprintf(os.Stderr, "Unable to read failed logs file: %v\n", err)
        return
    }
    lines := strings.Split(string(data), "\n")
    var remaining []string
    for _, line := range lines {
        if strings.TrimSpace(line) == "" {
            continue
        }
        var logEntry logJson
        if err := json.Unmarshal([]byte(line), &logEntry); err != nil {
            fmt.Fprintf(os.Stderr, "Corrupt failed log entry: %v\n", err)
            continue
        }
        if err := tryAddAction(logEntry); err != nil {
            fmt.Fprintf(os.Stderr, "Replay failed for log: %v\n", err)
            b, _ := json.Marshal(logEntry)
            remaining = append(remaining, string(b))
        }
    }
    if len(remaining) == 0 {
        os.Remove(failedLogsFile)
    } else {
        os.WriteFile(failedLogsFile, []byte(strings.Join(remaining, "\n")+"\n"), 0644)
    }
}

func triggerReconnect() {
    reconnectMutex.Lock()
    if reconnecting {
        reconnectMutex.Unlock()
        return
    }
    reconnecting = true
    reconnectMutex.Unlock()
    go func() {
        newConn := connectWithRetries()
        reconnectMutex.Lock()
        conn = newConn
        reconnecting = false
        reconnectMutex.Unlock()
        replayFailedLogs()
    }()
}

func connectWithRetries() *pgx.Conn {
    connStr := fmt.Sprintf("host=%s port=%s user=%s password=%s dbname=%s sslmode=disable",
        os.Getenv("POSTGRES_HOST"), os.Getenv("POSTGRES_PORT"), os.Getenv("POSTGRES_USER"), os.Getenv("POSTGRES_PASSWORD"), os.Getenv("POSTGRES_DATABASE"))
    var dbConn *pgx.Conn
    var err error
    maxAttempts := 120 // 10 minutes / 5 seconds
    for attempt := 1; attempt <= maxAttempts; attempt++ {
        dbConn, err = pgx.Connect(context.Background(), connStr)
        if err == nil {
            fmt.Println("Successfully connected!")
            return dbConn
        }
        fmt.Fprintf(os.Stderr, "Attempt %d/%d: Unable to connect to database: %v\n", attempt, maxAttempts, err)
        if attempt < maxAttempts {
            time.Sleep(5 * time.Second)
        }
    }
    fmt.Fprintf(os.Stderr, "Unable to connect to database after 10 minutes: %v\n", err)
    os.Exit(1)
    return nil // unreachable
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
