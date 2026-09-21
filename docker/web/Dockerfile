FROM node:22-alpine

WORKDIR /app

COPY docker/web/entrypoint.sh /usr/local/bin/web-entrypoint
RUN chmod +x /usr/local/bin/web-entrypoint

ENTRYPOINT ["web-entrypoint"]

EXPOSE 5173
