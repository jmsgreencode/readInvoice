FROM mysql/mysql-router:8.4

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 6446 6447

ENTRYPOINT ["/entrypoint.sh"]
