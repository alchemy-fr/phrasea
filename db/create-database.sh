#!/bin/bash

psql -U $POSTGRES_USER -tc "SELECT 1 FROM pg_database WHERE datname = '$1'" | grep -q 1 \
|| psql -U $POSTGRES_USER -c "CREATE DATABASE $1"


