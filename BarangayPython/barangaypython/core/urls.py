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
from django.http import HttpResponse
from django.urls import include, path


def home(request):
    return HttpResponse(
        """<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Barangay Request Form</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0;
               display:flex; min-height:100vh; align-items:center; justify-content:center; }
        .card { background:#fff; padding:48px; border-radius:14px; text-align:center;
                box-shadow:0 8px 24px rgba(0,0,0,0.08); max-width:460px; }
        h1 { color:#0d3b66; margin:0 0 8px; }
        p { color:#555; line-height:1.6; }
        .ok { color:#1e7e34; font-weight:bold; }
        a { display:inline-block; margin:8px; padding:10px 20px; background:#0d3b66;
            color:#fff; text-decoration:none; border-radius:8px; }
        a:hover { background:#09294a; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Smart Barangay Request Form</h1>
        <p class="ok">&#9989; API is running</p>
        <p>This server handles barangay document requests.</p>
        <a href="/admin/">Admin</a>
        <a href="/api/">API</a>
    </div>
</body>
</html>""",
        content_type="text/html",
    )


urlpatterns = [
    path('', home, name='home'),
    path('admin/', admin.site.urls),
    path('api/', include('api.urls')),
]

if settings.DEBUG:  # only serve media directly in dev/local prod
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
