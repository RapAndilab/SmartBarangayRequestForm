from django.contrib import admin

from api.models import CustomUser, UserDocumentRequest
from api.views import approve_document_request


@admin.register(CustomUser)
class CustomUserAdmin(admin.ModelAdmin):
    list_display = ('id', 'username', 'first_name', 'last_name', 'email', 'address', 'phone')


@admin.register(UserDocumentRequest)
class UserDocumentRequestAdmin(admin.ModelAdmin):
    list_display = ('id', 'user', 'document_type', 'full_name', 'status', 'requested_at', 'confirmed_at')
    list_filter = ('confirmed', 'document_type', 'requested_at')
    search_fields = ('full_name', 'user__username')
    ordering = ('-requested_at',)
    actions = ('approve_requests',)

    @admin.display(description="Status", boolean=False)
    def status(self, obj):
        return "Approved" if obj.confirmed else "Pending"

    @admin.action(description="Approve selected requests (unlock download + email user)")
    def approve_requests(self, request, queryset):
        count = 0
        for doc_req in queryset.filter(confirmed=False):
            approve_document_request(doc_req)
            count += 1
        self.message_user(request, f"{count} request(s) approved.")

    def save_model(self, request, obj, form, change):
        # If an admin ticks 'confirmed' on the edit page, run the full approval flow.
        if "confirmed" in form.changed_data and obj.confirmed and not obj.confirmed_at:
            approve_document_request(obj)
        else:
            super().save_model(request, obj, form, change)
