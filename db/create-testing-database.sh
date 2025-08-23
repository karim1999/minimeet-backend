#!/usr/bin/env bash

mysql --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS testing_core;
    GRANT ALL PRIVILEGES ON \`testing_core%\`.* TO '$MYSQL_USER'@'%';
EOSQL