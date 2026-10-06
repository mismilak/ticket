import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent


def _load_env():
    f = BASE_DIR / '.env'
    if f.exists():
        for line in f.read_text(encoding='utf-8').splitlines():
            line = line.strip()
            if line and not line.startswith('#') and '=' in line:
                k, v = line.split('=', 1)
                os.environ.setdefault(k.strip(), v.strip().strip('"').strip("'"))


_load_env()
env = os.environ.get

DEBUG = env('DJANGO_DEBUG', '0') == '1'
SECRET_KEY = env('DJANGO_SECRET_KEY') or ('dev-insecure-key-change-me' if DEBUG else None)
if not SECRET_KEY:
    raise RuntimeError('DJANGO_SECRET_KEY را در .env تنظیم کنید.')
ALLOWED_HOSTS = [h.strip() for h in env('DJANGO_ALLOWED_HOSTS', 'localhost,127.0.0.1').split(',') if h.strip()]
CSRF_TRUSTED_ORIGINS = [o.strip() for o in env('DJANGO_CSRF_TRUSTED_ORIGINS', '').split(',') if o.strip()]

INSTALLED_APPS = [
    'django.contrib.auth',
    'django.contrib.contenttypes',
    'django.contrib.sessions',
    'django.contrib.messages',
    'django.contrib.staticfiles',
    'ticketing',
]

MIDDLEWARE = [
    'django.middleware.security.SecurityMiddleware',
    'whitenoise.middleware.WhiteNoiseMiddleware',
    'django.contrib.sessions.middleware.SessionMiddleware',
    'django.middleware.common.CommonMiddleware',
    'django.middleware.csrf.CsrfViewMiddleware',
    'django.contrib.auth.middleware.AuthenticationMiddleware',
    'django.contrib.messages.middleware.MessageMiddleware',
    'django.middleware.clickjacking.XFrameOptionsMiddleware',
]

ROOT_URLCONF = 'config.urls'
WSGI_APPLICATION = 'config.wsgi.application'

TEMPLATES = [{
    'BACKEND': 'django.template.backends.django.DjangoTemplates',
    'DIRS': [BASE_DIR / 'templates'],
    'APP_DIRS': True,
    'OPTIONS': {'context_processors': [
        'django.template.context_processors.request',
        'django.contrib.auth.context_processors.auth',
        'django.contrib.messages.context_processors.messages',
        'ticketing.context_processors.site',
    ], 'builtins': ['django.templatetags.static', 'ticketing.templatetags.ticket_tags']},
}]

if env('DB_ENGINE', 'sqlite') == 'mysql':
    DATABASES = {'default': {
        'ENGINE': 'django.db.backends.mysql', 'NAME': env('DB_NAME', 'ticket'), 'USER': env('DB_USER', 'root'),
        'PASSWORD': env('DB_PASSWORD', ''), 'HOST': env('DB_HOST', '127.0.0.1'), 'PORT': env('DB_PORT', '3306'),
        'OPTIONS': {'charset': 'utf8mb4'},
    }}
else:
    DATABASES = {'default': {'ENGINE': 'django.db.backends.sqlite3', 'NAME': BASE_DIR / 'db.sqlite3'}}
DEFAULT_AUTO_FIELD = 'django.db.models.BigAutoField'

AUTH_USER_MODEL = 'ticketing.User'
AUTHENTICATION_BACKENDS = ['django.contrib.auth.backends.ModelBackend']
LOGIN_URL = '/login/'

LANGUAGE_CODE = 'fa'
TIME_ZONE = 'Asia/Tehran'
USE_I18N = False
USE_TZ = True

STATIC_URL = '/static/'
STATICFILES_DIRS = [BASE_DIR / 'static']
STATIC_ROOT = BASE_DIR / 'staticfiles'
STORAGES = {
    'default': {'BACKEND': 'django.core.files.storage.FileSystemStorage'},
    'staticfiles': {'BACKEND': 'whitenoise.storage.CompressedStaticFilesStorage'},
}
MEDIA_URL = '/media/'
MEDIA_ROOT = BASE_DIR / 'media'
# اگر Nginx/Apache خودش /media/ را سرو می‌کند مقدار 0 بگذارید
SERVE_MEDIA = env('SERVE_MEDIA', '1') == '1'

CACHES = {'default': {'BACKEND': 'django.core.cache.backends.filebased.FileBasedCache', 'LOCATION': BASE_DIR / 'var' / 'cache'}}
SESSION_ENGINE = 'django.contrib.sessions.backends.db'
SESSION_COOKIE_AGE = 60 * 60 * 24 * 30
MESSAGE_STORAGE = 'django.contrib.messages.storage.session.SessionStorage'

SECURE_PROXY_SSL_HEADER = ('HTTP_X_FORWARDED_PROTO', 'https')
if not DEBUG and env('DJANGO_HTTPS', '0') == '1':
    SESSION_COOKIE_SECURE = CSRF_COOKIE_SECURE = True
    SECURE_SSL_REDIRECT = True

FILE_UPLOAD_MAX_MEMORY_SIZE = 6 * 1024 * 1024
DATA_UPLOAD_MAX_NUMBER_FIELDS = 5000   # فرم‌های بزرگ (قیمت صندلی‌ها)
