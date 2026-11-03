# The published Relaticle image plus two small changes:
#
#   1. "/" redirects to /app (the image serves its marketing homepage there).
#   2. The "self-hosted" AI provider speaks chat/completions instead of the
#      Responses API, because our upstream gateway only routes the former.
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
COPY docker/patch-ai-config.php /tmp/patch-ai-config.php
# Guarded: exits non-zero when upstream's config moves, so a version bump that
# invalidates the patch fails the deploy instead of shipping a broken assistant.
RUN php /tmp/patch-ai-config.php && rm /tmp/patch-ai-config.php

# "Companies" reads as "Properties" for a real-estate book.
COPY docker/patch-company-labels.php /tmp/patch-company-labels.php
RUN php /tmp/patch-company-labels.php && rm /tmp/patch-company-labels.php

# Phone number in the people table on a property page.
COPY docker/patch-phone-column.php /tmp/patch-phone-column.php
RUN php /tmp/patch-phone-column.php && rm /tmp/patch-phone-column.php
USER www-data
