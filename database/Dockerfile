FROM mysql:8.0.21

ADD init.sql/* /docker-entrypoint-initdb.d/

CMD ["mysqld"]
EXPOSE 3306