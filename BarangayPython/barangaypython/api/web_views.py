"""Server-rendered pages for residents (the public-facing site).

These reuse the same backend logic as the API (OTP email, document generation)
but render Django templates and use Django's session auth.
"""
import os

from django.conf import settings
from django.contrib import messages
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.core.files.storage import default_storage
from django.core.mail import send_mail
from django.http import Http404
from django.shortcuts import redirect, render
from django.utils import timezone

from api.models import CustomUser, UserDocumentRequest
from api.views import OTP_TTL, send_email_otp, verify_face_against

DOCUMENT_TYPES = [
    ("clearance", "Barangay Clearance"),
    ("certificate", "Barangay Certification"),
    ("residency", "Certificate of Residency / Indigency"),
]

# Require a live face match before a document request is accepted. Turn off with
# REQUIRE_FACE_VERIFICATION=False for local work without a webcam.
REQUIRE_FACE = getattr(settings, "REQUIRE_FACE_VERIFICATION", True)


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



def _request_ctx(data):
    """Context for the document request page (the face widget is conditional)."""
    return {
        "document_types": DOCUMENT_TYPES,
        "data": data,
        "require_face": REQUIRE_FACE,
    }


@login_required(login_url="web_login")
def request_document(request):
    if request.method == "POST":
        data = request.POST.dict()
        document_type = data.get("document_type")
        if document_type not in dict(DOCUMENT_TYPES):
            messages.error(request, "Please choose a valid document type.")
            return render(request, "web/request.html", _request_ctx(data))

        user = request.user

        # Face check. The page also verifies before submitting, but that is only a
        # convenience — the authoritative check has to happen here, server-side.
        if REQUIRE_FACE:
            captured = request.FILES.get("captured_face")
            if not captured:
                messages.error(request, "Please capture your face before submitting the request.")
                return render(request, "web/request.html", _request_ctx(data))
            if not user.image:
                messages.error(
                    request,
                    "You have no profile photo on file, so your face can't be verified. "
                    "Please contact the barangay office.",
                )
                return render(request, "web/request.html", _request_ctx(data))

            tmp_path = default_storage.save(f"face_check_{user.id}.jpg", captured)
            tmp_full_path = default_storage.path(tmp_path)
            try:
                matched = verify_face_against(tmp_full_path, user)
            finally:
                if os.path.exists(tmp_full_path):
                    os.remove(tmp_full_path)

            if not matched:
                messages.error(
                    request,
                    "Face verification failed — the captured photo didn't match your "
                    "profile picture. Please try again in good lighting.",
                )
                return render(request, "web/request.html", _request_ctx(data))

        data["username"] = user.username
        full_name = data.get("full_name") or f"{user.first_name} {user.last_name}"
        data["full_name"] = full_name
        data.pop("csrfmiddlewaretoken", None)

        # The document itself is NOT generated here. We only record the request and
        # the answers given; the .docx is built when an admin approves it, so an
        # unapproved request never has a finished document sitting on disk.
        doc_req = UserDocumentRequest.objects.create(
            user=user,
            document_type=document_type,
            full_name=full_name,
            form_data=data,
            download_link="",
            confirmed=False,
        )
        messages.success(
            request,
            "Identity confirmed and your request was submitted. "
            "Last step: upload your payment screenshot so an admin can review it.",
        )
        # Face check passed -> send them straight to the payment upload.
        return redirect("web_payment", request_id=doc_req.id)

    initial = {
        "full_name": f"{request.user.first_name} {request.user.last_name}".strip(),
        "address": request.user.address,
    }
    return render(request, "web/request.html", _request_ctx(initial))


@login_required(login_url="web_login")
def payment(request, request_id):
    """Upload the payment screenshot for a request so an admin can review it."""
    try:
        doc_req = UserDocumentRequest.objects.get(id=request_id)
    except UserDocumentRequest.DoesNotExist:
        raise Http404("Request not found")

    # Only the owner (or staff) may attach a payment to a request.
    if doc_req.user_id != request.user.id and not request.user.is_staff:
        raise Http404("Request not found")

    if request.method == "POST":
        screenshot = request.FILES.get("payment_screenshot")
        if not screenshot:
            messages.error(request, "Please choose a payment screenshot to upload.")
            return render(request, "web/payment.html", {"req": doc_req})

        # Replace any previous upload rather than orphaning the old file.
        if doc_req.payment_screenshot:
            doc_req.payment_screenshot.delete(save=False)

        ext = os.path.splitext(screenshot.name)[1].lower() or ".jpg"
        screenshot.name = f"{doc_req.user.username}_{doc_req.id}{ext}"
        doc_req.payment_screenshot = screenshot
        doc_req.save(update_fields=["payment_screenshot"])

        _notify_admin_of_payment(doc_req)

        messages.success(
            request,
            "Payment screenshot uploaded. An admin will review it and approve your "
            "request — you'll be able to download the document once approved.",
        )
        return redirect("web_home")

    return render(request, "web/payment.html", {"req": doc_req})


def _notify_admin_of_payment(doc_req):
    """Email the barangay admin that a payment screenshot came in."""
    if not settings.ADMIN_EMAIL:
        return
    user = doc_req.user
    who = f"{user.first_name} {user.last_name}".strip() or user.username
    body = (
        f"PAYMENT SUBMITTED:\n"
        f"'{who}' submitted a payment screenshot for "
        f"Request #{doc_req.id} ({doc_req.document_type}).\n\n"
        f"Review it in the admin: {settings.BASE_URL}"
        f"/admin/api/userdocumentrequest/{doc_req.id}/change/"
    )
    try:
        send_mail(
            subject="New Document Payment Submitted",
            message=body,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[settings.ADMIN_EMAIL],
            fail_silently=True,
        )
    except Exception as e:
        print("Admin payment email error:", e)
