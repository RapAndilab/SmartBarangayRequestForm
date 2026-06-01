from django.contrib import admin
import requests

from api.models import CustomUser, UserDocumentRequest
from core import settings

# Register your models here.
@admin.register(CustomUser)
class CustomUserAdmin(admin.ModelAdmin):
    list_display = ('id', 'username', 'first_name', 'last_name', 'email', 'address', 'phone', 'image')
    
@admin.register(UserDocumentRequest)
class UserDocumentRequestAdmin(admin.ModelAdmin):
    list_display = ('id', 'user', 'document_type', 'full_name', 'payment_screenshot', 'confirmed', 'download_link', 'requested_at')
    list_editable = ("confirmed",)

    def save_model(self, request, obj, form, change):
        # Check if "confirmed" field changed to True
        if "confirmed" in form.changed_data and obj.confirmed:
            try:
                # Trigger your API endpoint
                response = requests.post(
                    settings.BASE_URL + "/api/confirm_request/",
                    json={"request_id": obj.id},
                )
            except Exception as e:
                print("Admin confirm API error:", e)

        super().save_model(request, obj, form, change)






