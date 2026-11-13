#!/bin/sh
# Dijalankan setiap kali server (container) dinyalakan di Railway. Lihat Dockerfile.

# 1. Buat tabel database kalau belum ada.
#    MySQL kadang baru siap beberapa detik setelah aplikasi nyala, jadi dicoba sampai 30 kali.
for i in $(seq 1 30); do
    if php /var/www/html/database/install.php; then
        break
    fi
    echo "MySQL belum siap, coba lagi dalam 2 detik... ($i/30)"
    sleep 2
done

# 2. Jalankan web server Apache.
exec apache2-foreground
