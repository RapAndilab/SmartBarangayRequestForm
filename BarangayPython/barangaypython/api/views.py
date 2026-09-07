import os
import secrets
from datetime import datetime, timedelta
from django.core.mail import send_mail

from django.conf import settings

from django.utils import timezone

from rest_framework import views, permissions
from django.contrib.auth import authenticate, login
from rest_framework import status, views
from rest_framework.response import Response
from rest_framework.parsers import MultiPartParser, FormParser

from django.core.files.storage import default_storage
from django.http import FileResponse, Http404

from api.models import CustomUser, UserDocumentRequest
from api.utils import generate_document_file

from .serializers import CustomUserSerializer, DocumentRequestSerializer

# EMAIL VERIFICATION
OTP_TTL = timedelta(minutes=10)


def send_email_otp(user):
    """Generate a fresh 6-digit code, store it, and email it to the user."""
    code = f"{secrets.randbelow(1000000):06d}"
    user.email_otp = code
    user.email_otp_created_at = timezone.now()
    user.save(update_fields=["email_otp", "email_otp_created_at"])

    if user.email:
        try:
            send_mail(
                subject="Your Barangay E-Form verification code",
                message=(
                    f"Hi {user.first_name},\n\n"
                    f"Your verification code is: {code}\n\n"
                    f"It expires in 10 minutes.\n"
                ),
                from_email=settings.DEFAULT_FROM_EMAIL,
                recipient_list=[user.email],
            )
        except Exception as e:
            print("OTP email error:", e)
    return code


# USER AUTHENTICATION
class UserCreateView(views.APIView):
    parser_classes = (MultiPartParser, FormParser)

    def post(self, request):
        serializer = CustomUserSerializer(data=request.data)
        if serializer.is_valid():
            user = serializer.save()
            if settings.EMAIL_VERIFICATION_ENABLED:
                send_email_otp(user)
                return Response(
                    {**serializer.data, "verification_required": True},
                    status=status.HTTP_201_CREATED,
                )
            user.is_verified = True
            user.save(update_fields=["is_verified"])
            return Response(serializer.data, status=status.HTTP_201_CREATED)
        return Response(serializer.errors, status=status.HTTP_200_OK)


class VerifyEmailView(views.APIView):
    def post(self, request):
        username = request.data.get("username")
        otp = request.data.get("otp")
        try:
            user = CustomUser.objects.get(username=username)
        except CustomUser.DoesNotExist:
            return Response({"error": "User not found"}, status=status.HTTP_200_OK)

        if user.is_verified:
            return Response({"message": "Email already verified"}, status=status.HTTP_200_OK)
        if not user.email_otp or user.email_otp != otp:
            return Response({"error": "Invalid verification code"}, status=status.HTTP_200_OK)
        if not user.email_otp_created_at or timezone.now() - user.email_otp_created_at > OTP_TTL:
            return Response({"error": "Code expired. Please request a new one."}, status=status.HTTP_200_OK)

        user.is_verified = True
        user.email_otp = None
        user.email_otp_created_at = None
        user.save(update_fields=["is_verified", "email_otp", "email_otp_created_at"])
        return Response({"message": "Email verified. You can now log in."}, status=status.HTTP_200_OK)


class ResendOtpView(views.APIView):
    def post(self, request):
        username = request.data.get("username")
        try:
            user = CustomUser.objects.get(username=username)
        except CustomUser.DoesNotExist:
            return Response({"error": "User not found"}, status=status.HTTP_200_OK)

        if user.is_verified:
            return Response({"message": "Email already verified"}, status=status.HTTP_200_OK)

        send_email_otp(user)
        return Response({"message": "A new code has been sent to your email."}, status=status.HTTP_200_OK)

class UserLoginView(views.APIView):
    def post(self, request):
        username = request.data.get('username')
        password = request.data.get('password')

        if not username or not password:
            return Response({"error": "Username and password required"}, status=status.HTTP_200_OK)

        user = authenticate(request, username=username, password=password)
        if user is not None:
            if settings.EMAIL_VERIFICATION_ENABLED and not user.is_verified:
                return Response(
                    {"error": "Please verify your email before logging in.",
                     "verification_required": True,
                     "username": user.username},
                    status=status.HTTP_200_OK,
                )
            login(request, user)  # Django session login
            return Response({
                "message": "Login successful",
                "user_id": user.id,
                "username": user.username,
                "address": user.address,
                "birthdate": user.birthdate,
                "first_name": user.first_name,
                "last_name": user.last_name,
                "role": getattr(user, 'role', 'user')
            }, status=status.HTTP_200_OK)
        else:
            return Response({"error": "Invalid username or password"}, status=status.HTTP_200_OK)

class GetProfileImageView(views.APIView):
    def get(self, request):
        user_id = request.GET.get('user_id')

        if user_id:
            try:
                user = CustomUser.objects.get(id=user_id)
            except CustomUser.DoesNotExist:
                return Response({"error": "User not found"}, status=status.HTTP_200_OK)
        else:
            user = request.user
            
        if hasattr(user, 'image') and user.image:
            return Response({"image_url": f"{settings.MEDIA_URL}{user.image.name}"})
        return Response({"error": "No profile image"}, status=status.HTTP_200_OK)

# FACE RECOGNITION
FACE_MODEL = "VGG-Face"


def verify_face_against(image_path, user):
    """True if the captured image matches this user's profile photo.

    DeepFace is imported lazily: loading TensorFlow costs several seconds and
    ~1 GB of RAM, and nothing else in the app needs it, so we don't pay that
    price on every `manage.py` command or dev-server reload.
    """
    from deepface import DeepFace

    if not user.image:
        return False
    try:
        result = DeepFace.verify(
            img1_path=image_path,
            img2_path=user.image.path,
            model_name=FACE_MODEL,
            enforce_detection=True,  # raises if no face is found in either image
        )
        return bool(result.get("verified", False))
    except Exception:
        # No face detected in one of the images, or the file is unreadable.
        return False


class VerifyFaceView(views.APIView):
    """Match an uploaded face against stored profile photos.

    Returns {"match": "<full name>"} on success and {"match": None} otherwise,
    which is the contract the original face_validator.js expected. When the
    caller is logged in we compare against their own photo only (1:1) instead
    of scanning every user, so the answer is 'is this you?' rather than the
    weaker 'is this anybody?'.
    """
    parser_classes = [MultiPartParser, FormParser]

    def post(self, request):
        uploaded_image = request.FILES.get('captured_face')
        if not uploaded_image:
            return Response(
                {"error": "No captured_face image was provided."},
                status=status.HTTP_400_BAD_REQUEST,
            )

        temp_path = default_storage.save("temp_uploaded.jpg", uploaded_image)
        temp_full_path = default_storage.path(temp_path)

        try:
            user = request.user
            if user.is_authenticated:
                if not user.image:
                    return Response(
                        {"match": None,
                         "error": "You have no profile photo on file to match against."},
                        status=status.HTTP_400_BAD_REQUEST,
                    )
                if verify_face_against(temp_full_path, user):
                    return Response(
                        {"match": f"{user.first_name} {user.last_name}".strip() or user.username,
                         "match_user_id": user.id},
                        status=status.HTTP_200_OK,
                    )
                return Response({"match": None}, status=status.HTTP_200_OK)

            # Anonymous caller: fall back to identifying against all users.
            for candidate in CustomUser.objects.exclude(image=''):
                if verify_face_against(temp_full_path, candidate):
                    return Response(
                        {"match": f"{candidate.first_name} {candidate.last_name}".strip()
                                  or candidate.username,
                         "match_user_id": candidate.id},
                        status=status.HTTP_200_OK,
                    )
            return Response({"match": None}, status=status.HTTP_200_OK)

        except Exception as e:
            return Response({"error": str(e)}, status=status.HTTP_400_BAD_REQUEST)
        finally:
            if os.path.exists(temp_full_path):
                os.remove(temp_full_path)

# DOCUMENT DOWNLOAD (gated on admin approval)
class DownloadDocumentView(views.APIView):
    """Serve a generated document file only after the request is approved."""
    def get(self, request, request_id):
        try:
            doc_request = UserDocumentRequest.objects.get(id=request_id)
        except UserDocumentRequest.DoesNotExist:
            raise Http404("Request not found")

        # Only the owner (or staff) may download.
        u = request.user
        if not u.is_authenticated or (doc_request.user_id != u.id and not u.is_staff):
            return Response(
                {"error": "You are not allowed to download this document."},
                status=status.HTTP_403_FORBIDDEN,
            )

        if not doc_request.confirmed:
            return Response(
                {"error": "This request has not been approved yet."},
                status=status.HTTP_403_FORBIDDEN,
            )

        def resolve(link):
            # download_link looks like "/media/generated_xxx.docx" -> real path
            name = os.path.basename(link or "")
            return name, os.path.join(settings.MEDIA_ROOT, name)

        filename, file_path = resolve(doc_request.download_link)

        # Safety net: the request is approved, so a document is owed. If it was never
        # generated (approval-time failure) or the file has gone (Render's free-tier
        # disk is wiped on redeploy), rebuild it from the stored form data.
        if not filename or not os.path.exists(file_path):
            try:
                build_document_for(doc_request)
                doc_request.save(update_fields=["download_link"])
                filename, file_path = resolve(doc_request.download_link)
            except Exception as e:
                print("Document rebuild error:", e)

        if not filename or not os.path.exists(file_path):
            raise Http404("Document file is not available for this request")

        return FileResponse(
            open(file_path, "rb"), as_attachment=True, filename=filename
        )

# DOCUMENT REQUEST
class DocumentRequestCreateView(views.APIView):
    def post(self, request):
        data = request.data.copy()

        user = CustomUser.objects.get(id=request.data.get("user_id"))

        # The .docx is generated at approval time, not here — only the request and
        # the submitted answers are recorded. Issue dates are stamped on approval.
        form_data = {k: v for k, v in data.items() if isinstance(v, (str, int, float, bool))}
        form_data.pop("csrfmiddlewaretoken", None)

        doc_request = UserDocumentRequest.objects.create(
            user=user,
            document_type=data.get("document_type"),
            full_name=f"{user.first_name} {user.last_name}",
            form_data=form_data,
            download_link="",
            confirmed=False,
        )

        return Response({"request_id": doc_request.id}, status=status.HTTP_201_CREATED)

class DocumentProcessRequestView(views.APIView):
    def post(self, request):
        user_id = request.data.get("user_id", None)
        request_id = request.data.get("request_id", None)

        user = CustomUser.objects.get(id=user_id)

        if request_id:
            try:
                req = UserDocumentRequest.objects.get(id=request_id)
                # Optionally check user permission here
                serializer = DocumentRequestSerializer(req)
                return Response(serializer.data)
            except UserDocumentRequest.DoesNotExist:
                return Response({"error": "Request not found."}, status=201)

        if user_id:
            requests = UserDocumentRequest.objects.filter(user=user).order_by('-requested_at')
        else:
            requests = UserDocumentRequest.objects.all().order_by('-requested_at')

        serializer = DocumentRequestSerializer(requests, many=True)
        return Response(serializer.data)

    def patch(self, request):
        user_id = request.data.get("user_id", None)
        request_id = request.data.get("request_id", None)

        user = CustomUser.objects.get(id=user_id)
        
        try:
            doc_request = UserDocumentRequest.objects.get(id=request_id, user=user)
        except UserDocumentRequest.DoesNotExist:
            return Response({"error": "Document request not found."}, status=status.HTTP_404_NOT_FOUND)

        # Only update payment_screenshot on patch
        payment_screenshot = request.FILES.get('payment_screenshot')
        if not payment_screenshot:
            return Response({"error": "No payment screenshot uploaded."}, status=status.HTTP_400_BAD_REQUEST)

        if payment_screenshot:
            if doc_request.payment_screenshot:
                doc_request.payment_screenshot.delete(save=False)

            new_filename = f"{doc_request.user.username}_{doc_request.id}.jpg"
            payment_screenshot.name = new_filename
            doc_request.payment_screenshot = payment_screenshot

        doc_request.payment_screenshot = payment_screenshot
        doc_request.save()

        admin_message = (
            f"PAYMENT SUBMITTED:\n"
            f"'{user.first_name} {user.last_name}' submitted a payment screenshot for Request #{doc_request.id} "
            f"({doc_request.document_type})."
        )
        # -EMAIL NOTIFICATION TO USER-

        print("Sending Email")
        try:
            send_mail(
                subject="New Document Payment Submitted",
                message=admin_message,
                from_email=settings.DEFAULT_FROM_EMAIL,
                recipient_list=[settings.ADMIN_EMAIL],
            )
        except Exception as e:
            print("Admin email error:", e)
        # ------------------------------------------------------------

        serializer = DocumentRequestSerializer(doc_request)
        return Response(serializer.data, status=status.HTTP_200_OK)

def build_document_for(doc_req):
    """Generate the .docx for a request and store its download link.

    Called at approval time (not when the resident submits), so an unapproved
    request never has a finished document sitting on disk. Also used to rebuild a
    file that has gone missing — on Render's free tier the disk is wiped on every
    redeploy, and the stored form_data is enough to recreate it.
    """
    data = dict(doc_req.form_data or {})
    if not data:
        # Legacy request from before form_data was stored — fall back to what's
        # on the model. The template placeholders it can't fill stay as-is.
        data = {"document_type": doc_req.document_type,
                "full_name": doc_req.full_name,
                "username": doc_req.user.username}

    data["document_type"] = doc_req.document_type
    data["username"] = doc_req.user.username
    data["full_name"] = doc_req.full_name

    # Stamp the issue date at approval time, so the document carries the date it
    # was actually issued rather than the date it was requested.
    now = datetime.now()
    day = now.day
    suffix = "th" if 11 <= day <= 13 else {1: "st", 2: "nd", 3: "rd"}.get(day % 10, "th")
    data["issued_date_long"] = f"{day}{suffix} day of {now.strftime('%B, %Y')}"
    data["issued_date_short"] = now.strftime("%B %d, %Y")

    download_url = generate_document_file(data)
    doc_req.download_link = download_url
    return download_url


def approve_document_request(doc_req):
    """Approve a request: generate the document, flip the status, email the user.

    The request record itself is kept as-is — only the status changes and the
    downloadable file becomes ready.
    """
    try:
        build_document_for(doc_req)
    except Exception as e:
        # Don't lose the approval if generation fails; the download view will
        # retry building the file on demand.
        print("Document generation error on approval:", e)

    doc_req.confirmed = True
    doc_req.confirmed_at = timezone.now()
    doc_req.save()

    # -EMAIL NOTIFICATION TO USER-
    if doc_req.user.email:
        try:
            send_mail(
                subject="Your document request has been approved",
                message=(
                    f"Hi '{doc_req.user.first_name} {doc_req.user.last_name}',\n\n"
                    f"Your request for '{doc_req.document_type}' "
                    f"has been approved.\n\n"
                    f"You may now download your document.\n"
                ),
                from_email=settings.DEFAULT_FROM_EMAIL,
                recipient_list=[doc_req.user.email]
            )
        except Exception as e:
            print("Email error:", e)
    # ------------------------------------------------------------


class DocumentConfirmRequestView(views.APIView):
    def post(self, request):
        try:
            doc_req = UserDocumentRequest.objects.get(id=request.data.get("request_id"))
        except UserDocumentRequest.DoesNotExist:
            return Response({"error": "Request not found"}, status=status.HTTP_404_NOT_FOUND)

        approve_document_request(doc_req)
        return Response({"message": "Request approved and download link unlocked."})

