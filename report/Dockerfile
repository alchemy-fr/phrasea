FROM golang:1.12.13-alpine3.10

WORKDIR /go/src/app

RUN apk add --no-cache git mercurial

COPY ./src .

RUN go get \
    && go build \
    && chmod +x go.sh

CMD ./go.sh

EXPOSE 80
