"""Server-rendered pages for residents (the public-facing site).

These reuse the same backend logic as the API (OTP email, document generation)
but render Django templates and use Django's session auth.
"""
from datetime import datetime

from django.conf import settings
from django.contrib import messages
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.shortcuts import redirect, render
from django.utils import timezone

from api.models import CustomUser, UserDocumentRequest
from api.utils import generate_document_file
from api.views import OTP_TTL, send_email_otp

DOCUMENT_TYPES = [
    ("clearance", "Barangay Clearance"),
    ("certificate", "Barangay Certification"),
    ("residency", "Certificate of Residency / Indigency"),
]


def home(request):
    requests_qs = None
    if request.user.is_authenticated:
        requests_qs = UserDocumentRequest.objects.filter(
            user=request.user
        ).order_by("-requested_at")
    return render(request, "web/home.html", {"requests": requests_qs})


def register(request):
    if request.method == "POST":
        data = request.POST
        username = (data.get("username") or "").strip()
        password = data.get("password") or ""
        confirm = data.get("confirm_password") or ""
        image = request.FILES.get("image")

        error = None
        if password != confirm:
            error = "Passwords do not match."
        elif not username.replace("_", "").isalnum():
            error = "Username can only contain letters, numbers, and underscores."
        elif CustomUser.objects.filter(username=username).exists():
            error = "That username is already taken."

        if error:
            messages.error(request, error)
            return render(request, "web/register.html", {"data": data, "genders": ["Male", "Female"]})

        user = CustomUser(
            username=username,
            first_name=data.get("first_name", ""),
            last_name=data.get("last_name", ""),
            email=data.get("email", ""),
            birthdate=data.get("birthdate") or None,
            gender=data.get("gender", ""),
            address=data.get("address", ""),
            phone=data.get("phone", ""),
        )
        # Auto-verify unless email verification is enabled.
        user.is_verified = not settings.EMAIL_VERIFICATION_ENABLED
        if image:
            ext = image.name.split(".")[-1]
            image.name = f"{username.lower()}.{ext}"
            user.image = image
        user.set_password(password)
        user.save()

        if settings.EMAIL_VERIFICATION_ENABLED:
            send_email_otp(user)
            request.session["pending_username"] = username
            messages.success(request, "Account created! We emailed you a 6-digit verification code.")
            return redirect("web_verify")

        messages.success(request, "Account created! You can now log in.")
        return redirect("web_login")

    return render(request, "web/register.html", {"data": {}, "genders": ["Male", "Female"]})


def verify(request):
    username = request.GET.get("username") or request.session.get("pending_username", "")

    if request.method == "POST":
        username = request.POST.get("username") or username
        action = request.POST.get("action", "verify")
        try:
            user = CustomUser.objects.get(username=username)
        except CustomUser.DoesNotExist:
            messages.error(request, "Account not found.")
            return render(request, "web/verify.html", {"username": username})

        if user.is_verified:
            messages.success(request, "Your email is already verified. You can log in.")
            return redirect("web_login")

        if action == "resend":
            send_email_otp(user)
            messages.success(request, "A new code has been sent to your email.")
            return render(request, "web/verify.html", {"username": username})

        otp = (request.POST.get("otp") or "").strip()
        if not user.email_otp or user.email_otp != otp:
            messages.error(request, "Invalid verification code.")
        elif not user.email_otp_created_at or timezone.now() - user.email_otp_created_at > OTP_TTL:
            messages.error(request, "Code expired. Please request a new one.")
        else:
            user.is_verified = True
            user.email_otp = None
            user.email_otp_created_at = None
            user.save(update_fields=["is_verified", "email_otp", "email_otp_created_at"])
            request.session.pop("pending_username", None)
            messages.success(request, "Email verified! You can now log in.")
            return redirect("web_login")

    return render(request, "web/verify.html", {"username": username})


def login_view(request):
    if request.method == "POST":
        username = request.POST.get("username")
        password = request.POST.get("password")
        user = authenticate(request, username=username, password=password)

        if user is None:
            messages.error(request, "Invalid username or password.")
        elif settings.EMAIL_VERIFICATION_ENABLED and not user.is_verified:
            send_email_otp(user)
            request.session["pending_username"] = user.username
            messages.error(request, "Please verify your email first. We sent you a new code.")
            return redirect("web_verify")
        else:
            login(request, user)
            return redirect("web_home")

    return render(request, "web/login.html")


def logout_view(request):
    logout(request)
    messages.success(request, "You have been logged out.")
    return redirect("web_login")


def _ordinal(day):
    if 11 <= day <= 13:
        return f"{day}th"
    return f"{day}{ {1: 'st', 2: 'nd', 3: 'rd'}.get(day % 10, 'th') }"


@login_required(login_url="web_login")
def request_document(request):
    if request.method == "POST":
        data = request.POST.dict()
        document_type = data.get("document_type")
        if document_type not in dict(DOCUMENT_TYPES):
            messages.error(request, "Please choose a valid document type.")
            return render(request, "web/request.html", {"document_types": DOCUMENT_TYPES, "data": data})

        user = request.user
        data["username"] = user.username
        full_name = data.get("full_name") or f"{user.first_name} {user.last_name}"
        data["full_name"] = full_name

        now = datetime.now()
        data["issued_date_long"] = f"{_ordinal(now.day)} day of {now.strftime('%B, %Y')}"
        data["issued_date_short"] = now.strftime("%B %d, %Y")

        try:
            download_url = generate_document_file(data)
        except Exception as e:
            messages.error(request, f"Could not generate the document: {e}")
            return render(request, "web/request.html", {"document_types": DOCUMENT_TYPES, "data": data})

        UserDocumentRequest.objects.create(
            user=user,
            document_type=document_type,
            full_name=full_name,
            download_link=download_url,
            confirmed=False,
        )
        messages.success(
            request,
            "Your request has been submitted and is pending admin approval. "
            "You'll be able to download it once approved.",
        )
        return redirect("web_home")

    initial = {
        "full_name": f"{request.user.first_name} {request.user.last_name}".strip(),
        "address": request.user.address,
    }
    return render(request, "web/request.html", {"document_types": DOCUMENT_TYPES, "data": initial})
