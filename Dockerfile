# استخدام صورة أساسية تحتوي على PHP 8.2 مع Apache
FROM php:8.2-apache

# تثبيت Python و FFmpeg ومكتبات معالجة الصوت والأكواد البرمجية الصوتية (libopus)
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    ffmpeg \
    libopus-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# تفعيل مود الأباتشي الخاص بإعادة التوجيه
RUN a2enmod rewrite

# إنشاء بيئة Python الافتراضية وتحديث pip
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# تثبيت مكتبات معالجة وتعديل ترددات وشكل الصوت
RUN pip install --no-cache-dir numpy scipy pydub soundfile librosa

# إنشاء المجلدات المطلوبة للرفع والمعالجة
RUN mkdir -p /var/www/html/uploads /var/www/html/outputs /var/www/html/models

# نسخ ملفات الكود إلى السيرفر
COPY . /var/www/html/

# ضبط الصلاحيات للمجلدات
RUN chown -R www-data:www-data /var/www/html && chmod -R 775 /var/www/html/uploads /var/www/html/outputs

EXPOSE 80

CMD ["apache2-foreground"]
