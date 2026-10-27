#!/bin/sh
# Private Telegram bot only. The Codex process has its own account and sees no site files.
set -eu

case "${1:-}" in
 status|reply) bot_action=$1 ;;
 *) exit 64 ;;
esac

set -- /usr/bin/timeout 50s /usr/sbin/runuser -u projectpintar-ai -- \
 /usr/bin/bwrap --die-with-parent --unshare-pid --clearenv \
 --setenv HOME /var/lib/projectpintar-ai \
 --setenv USER projectpintar-ai \
 --setenv LOGNAME projectpintar-ai \
 --setenv PATH /usr/local/bin:/usr/bin:/bin \
 --ro-bind /usr /usr \
 --ro-bind /lib /lib \
 --ro-bind /lib64 /lib64 \
 --ro-bind /etc/ssl /etc/ssl \
 --ro-bind /etc/passwd /etc/passwd \
 --ro-bind /etc/group /etc/group \
 --ro-bind /etc/resolv.conf /etc/resolv.conf \
 --ro-bind /etc/hosts /etc/hosts \
 --ro-bind /etc/nsswitch.conf /etc/nsswitch.conf \
 --proc /proc --dev /dev --tmpfs /tmp \
 --dir /var --dir /var/lib \
 --bind /var/lib/projectpintar-ai /var/lib/projectpintar-ai \
 --chdir /var/lib/projectpintar-ai/scratch \
 /usr/local/bin/projectpintar-codex

if [ "$bot_action" = status ]; then
 exec "$@" login status
fi
exec "$@" exec --ephemeral --ignore-user-config --sandbox read-only \
 --skip-git-repo-check --disable shell_tool --disable multi_agent \
 --disable apps --disable code_mode -m gpt-6-luna -
