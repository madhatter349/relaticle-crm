# A header injector, nothing else.
#
# OpenCode Go refuses any request without an `x-opencode-session` id, and the
# AI SDK Relaticle uses can set a URL and a key but no request headers. This
# proxy adds the header, forwards the caller's Authorization untouched, and
# streams the reply back. It holds no secret of its own.
FROM nginx:1.30.6-alpine

COPY docker/ai-proxy/default.conf.template /etc/nginx/templates/default.conf.template
