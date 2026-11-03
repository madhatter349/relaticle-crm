# The published Relaticle image plus one nginx snippet: "/" redirects to /app.
#
# Nothing else is changed, so this stays a thin layer over the pinned upstream
# release — bump RELATICLE_VERSION in Coolify and the base moves with it.
#
# Kept as a build instead of a bind mount because Coolify drops relative bind
# mounts from a compose stack: the file would arrive as an empty directory.
ARG RELATICLE_VERSION=3.8.0
FROM ghcr.io/relaticle/relaticle:${RELATICLE_VERSION}

USER root
COPY docker/root-redirect.conf /etc/nginx/server-opts.d/00-root-redirect.conf
USER www-data
