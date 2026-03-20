# #!/bin/bash
# set -e

# mkdir -p storage/framework/cache/data
# mkdir -p storage/framework/sessions
# mkdir -p storage/framework/views
# mkdir -p bootstrap/cache

# chown -R www-data:www-data storage bootstrap/cache
# chmod -R 775 storage bootstrap/cache

# php artisan config:clear
# php artisan route:clear
# php artisan cache:clear

# php artisan migrate --force

# apache2-foreground

#!/bin/bash
set -e

mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan config:clear
php artisan route:clear
php artisan cache:clear

# 🔥 ADD THIS LINE
php artisan storage:link || true

php artisan migrate --force

apache2-foreground