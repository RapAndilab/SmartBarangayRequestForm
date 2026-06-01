from django.contrib.auth.models import AbstractUser
from django.db import models

# Create your models here.
class CustomUser(AbstractUser):
    birthdate = models.DateField(null=True)
    gender = models.CharField(max_length=20)
    address = models.TextField()
    phone = models.CharField(max_length=20)
    image = models.ImageField(upload_to='user_images/', blank=True, null=True)

class UserDocumentRequest(models.Model):
    user = models.ForeignKey(CustomUser, on_delete=models.CASCADE)
    document_type = models.CharField(max_length=100)
    full_name=models.CharField(max_length=100)

    payment_screenshot = models.ImageField(upload_to='payment_screenshots/', blank=True, null=True)
    confirmed = models.BooleanField(default=False)
    download_link = models.CharField(max_length=100, blank=True, null=True)
    requested_at = models.DateTimeField(auto_now_add=True)
    confirmed_at = models.DateTimeField(blank=True, null=True)
