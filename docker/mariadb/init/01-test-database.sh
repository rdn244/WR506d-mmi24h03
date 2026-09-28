#!/bin/sh
# Base dédiée aux tests (Doctrine ajoute le suffixe _test en environnement test)
mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MARIADB_DATABASE}_test\`;
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}_test\`.* TO '${MARIADB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
