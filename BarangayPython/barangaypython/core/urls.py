"""
URL configuration for core project.

The `urlpatterns` list routes URLs to views. For more information please see:
    https://docs.djangoproject.com/en/5.2/topics/http/urls/
Examples:
Function views
    1. Add an import:  from my_app import views
    2. Add a URL to urlpatterns:  path('', views.home, name='home')
Class-based views
    1. Add an import:  from other_app.views import Home
    2. Add a URL to urlpatterns:  path('', Home.as_view(), name='home')
Including another URLconf
    1. Import the include() function: from django.urls import include, path
    2. Add a URL to urlpatterns:  path('blog/', include('blog.urls'))
"""
from django.conf import settings
from django.conf.urls.static import static

from django.contrib import admin
from django.urls import include, path

from api import web_views

urlpatterns = [
    # Public-facing resident site (server-rendered templates)
    path('', web_views.home, name='web_home'),
    path('register/', web_views.register, name='web_register'),
    path('verify/', web_views.verify, name='web_verify'),
    path('login/', web_views.login_view, name='web_login'),
    path('logout/', web_views.logout_view, name='web_logout'),
    path('request/', web_views.request_document, name='web_request'),
    path('payment/<int:request_id>/', web_views.payment, name='web_payment'),

    path('admin/', admin.site.urls),
    path('api/', include('api.urls')),
]

if settings.DEBUG:  # only serve media directly in dev/local prod
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
