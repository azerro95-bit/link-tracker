# استخدام صورة أساسية تحتوي على PHP مع Apache
FROM php:8.2-apache

# تثبيت Python ومكتبات النظام الضرورية (مثل ffmpeg لمعالجة الصوت)
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    ffmpeg \
    git \
    && rm -rf /var/lib/apt/lists/*

# تفعيل مودات أباتشي المهمة
RUN a2enmod rewrite

# إنشاء بيئة افتراضية لـ Python لتثبيت المكتبات بأمان
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# ترقية pip وتثبيت مكتبات بايثون الأساسية (يمكنك إضافة مكتبات RVC أو الصوت لاحقاً)
RUN pip install --no-cache-dir numpy scipy pydub requests

# نسخ ملفات المشروع إلى مسار العمل في الأباتشي
COPY . /var/www/html/

# ضبط الصلاحيات
RUN chown -R www-data:www-data /var/www/html

# تعيين المنفذ الافتراضي لـ Render
EXPOSE 80

# أمر التشغيل (تشغيل Apache في الواجهة الأمامية)
CMD ["apache2-foreground"]
