FROM golang:1.12.13-alpine3.10

RUN mkdir /usr/app

WORKDIR /usr/app

RUN apk add --no-cache git mercurial

COPY ./src .

RUN go build -o app . \
    && chmod +x go.sh \
    && rm *.go *.mod *.sum

RUN pwd
RUN ls -la

EXPOSE 80

CMD ./go.sh
