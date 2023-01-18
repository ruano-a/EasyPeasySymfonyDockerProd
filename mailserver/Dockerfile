FROM mailserver/docker-mailserver:latest
COPY entrypoint.sh /usr/bin/entrypoint.sh
RUN chmod 755 /usr/bin/entrypoint.sh

ENTRYPOINT ["/usr/bin/entrypoint.sh", "--"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]