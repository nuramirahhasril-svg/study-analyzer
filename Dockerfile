# Dockerfile untuk deploy ke Railway (atau server lain yang memakai Docker).
# Di XAMPP file ini TIDAK dipakai.
#
# Isinya: PHP 8.2 + Apache (versi PHP yang sama dengan XAMPP).

FROM php:8.2-apache

# Ekstensi mysqli untuk koneksi ke MySQL (tidak ada di image bawaan).
RUN docker-php-ext-install mysqli

# Pengaturan PHP untuk server publik: pesan error tidak ditampilkan ke pengunjung,
# cookie login lebih aman. Detailnya ada di docker/php.ini.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/study-analyzer.ini"

# Apache mendengarkan port dari variabel PORT (Railway mengisinya otomatis, default 80).
ENV PORT=80
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf
COPY docker/apache.conf /etc/apache2/conf-enabled/study-analyzer.conf

# Zona waktu untuk jam MySQL (NOW, CURDATE). MySQL di Railway memakai UTC.
# +08:00 = jam Malaysia. Bisa diganti lewat Variables di Railway.
ENV DB_TIMEZONE=+08:00

# Salin seluruh project ke folder web Apache.
COPY . /var/www/html/

# Folder session (login 30 hari) harus bisa ditulis oleh Apache.
RUN mkdir -p /var/www/html/storage/sessions \
 && chown -R www-data:www-data /var/www/html/storage \
 && chmod 700 /var/www/html/storage/sessions

# Saat server nyala: buat tabel database (kalau belum ada), lalu jalankan Apache.
CMD ["sh", "/var/www/html/docker/start.sh"]
