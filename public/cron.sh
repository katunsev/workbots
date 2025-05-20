#!/bin/bash

CRON_TEMP=$(mktemp)

crontab -l > "$CRON_TEMP" 2>/dev/null

add_cron_job() {
    local job="$1"
    grep -F "$job" "$CRON_TEMP" >/dev/null 2>&1 || echo "$job" >> "$CRON_TEMP"
}

add_cron_job "*/30 9-18 * * 1-5 /usr/bin/php /var/www/katunsev.ru/public/cron.php >> /var/log/cron.php.log 2>&1"
add_cron_job "0 9-18 * * 1-5 /usr/bin/php /var/www/katunsev.ru/public/expired.php >> /var/log/expired.php.log 2>&1"
add_cron_job "0 9-18 * * 1-5 /usr/bin/php /var/www/katunsev.ru/public/expired.php >> /var/log/expired-soon.php.log 2>&1"

crontab "$CRON_TEMP"

rm "$CRON_TEMP"

echo "Cron задания успешно обновлены без дублей."
