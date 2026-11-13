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

# 2. Pastikan Apache hanya memakai SATU modul MPM, yaitu mpm_prefork (yang dibutuhkan PHP).
#    Di Railway modul mpm_event kadang ikut aktif, lalu Apache gagal start dengan error
#    "AH00534: More than one MPM loaded". Karena itu dimatikan di sini, setiap kali server nyala.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
a2enmod -q mpm_prefork

# 3. Jalankan web server Apache.
exec apache2-foreground
