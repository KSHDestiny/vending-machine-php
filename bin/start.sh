#!/bin/sh
set -eu

php bin/wait-for-db.php
php bin/migrate.php
php bin/seed.php
exec apache2-foreground
