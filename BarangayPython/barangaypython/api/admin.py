from django.contrib import admin
from django.utils.html import format_html

from api.models import CustomUser, UserDocumentRequest
from api.views import approve_document_request


class HasPaymentFilter(admin.SimpleListFilter):
    """Filter the request list by whether a payment screenshot was uploaded."""
    title = "payment screenshot"
    parameter_name = "has_payment"

    def lookups(self, request, model_admin):
        return (("yes", "Uploaded"), ("no", "Not uploaded"))

    def queryset(self, request, queryset):
        if self.value() == "yes":
            return queryset.exclude(payment_screenshot="").exclude(payment_screenshot=None)
        if self.value() == "no":
            return queryset.filter(payment_screenshot__in=["", None])
        return queryset


@admin.register(CustomUser)
class CustomUserAdmin(admin.ModelAdmin):
    list_display = ('id', 'username', 'first_name', 'last_name', 'email', 'address', 'phone')


@admin.register(UserDocumentRequest)
class UserDocumentRequestAdmin(admin.ModelAdmin):
    list_display = ('id', 'user', 'document_type', 'full_name', 'status', 'payment',
                    'requested_at', 'confirmed_at')
    list_filter = ('confirmed', HasPaymentFilter, 'document_type', 'requested_at')
    search_fields = ('full_name', 'user__username')
    ordering = ('-requested_at',)
    actions = ('approve_requests',)
    readonly_fields = ('payment_preview',)

    @admin.display(description="Status", boolean=False)
    def status(self, obj):
        return "Approved" if obj.confirmed else "Pending"

    @admin.display(description="Payment")
    def payment(self, obj):
        """Thumbnail + link to the resident's payment screenshot, in the list view."""
        if not obj.payment_screenshot:
            return format_html('<span style="color:#b9770e;">Not uploaded</span>')
        return format_html(
            '<a href="{}" target="_blank" rel="noopener" title="Open full size">'
            '<img src="{}" style="height:38px;border-radius:4px;'
            'border:1px solid #ccc;vertical-align:middle;"></a>',
            obj.payment_screenshot.url, obj.payment_screenshot.url,
        )

    @admin.display(description="Payment screenshot")
    def payment_preview(self, obj):
        """Large preview on the request's detail page."""
        if not obj.payment_screenshot:
            return "No payment screenshot uploaded yet."
        return format_html(
            '<a href="{}" target="_blank" rel="noopener">'
            '<img src="{}" style="max-width:420px;border-radius:8px;border:1px solid #ccc;">'
            '</a>',
            obj.payment_screenshot.url, obj.payment_screenshot.url,
        )

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
