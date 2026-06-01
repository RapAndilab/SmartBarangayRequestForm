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
            send_email_otp(user)
            return Response(
                {**serializer.data, "verification_required": True},
                status=status.HTTP_201_CREATED,
            )
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
            if not user.is_verified:
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

        if not doc_request.download_link:
            raise Http404("No document available for this request")

        # download_link looks like "/media/generated_xxx.docx" -> resolve to a real path
        filename = os.path.basename(doc_request.download_link)
        file_path = os.path.join(settings.MEDIA_ROOT, filename)
        if not os.path.exists(file_path):
            raise Http404("Document file is no longer available")

        return FileResponse(
            open(file_path, "rb"), as_attachment=True, filename=filename
        )

# DOCUMENT REQUEST
class DocumentRequestCreateView(views.APIView):
    def post(self, request):
        data = request.data.copy()

        user = CustomUser.objects.get(id=request.data.get("user_id"))
        try:            
            def get_day_with_suffix(day: int):
                if 11 <= day <= 13:
                    return f"{day}th"
                else:
                    return f"{day}{ {1:'st', 2:'nd', 3:'rd'}.get(day % 10, 'th') }"

            # Before calling generate_document_file
            now = datetime.now()

            data["issued_date_long"] = f"{get_day_with_suffix(now.day)} day of {now.strftime('%B, %Y')}"
            data["issued_date_short"] = now.strftime("%B %d, %Y")

            download_url = generate_document_file(data)
        except Exception as e:
            return Response({"error": f"Document generation failed: {str(e)}"}, status=status.HTTP_400_BAD_REQUEST)

        doc_request = UserDocumentRequest.objects.create(
            user=user,
            document_type=data.get("document_type"),
            full_name=f"{user.first_name} {user.last_name}",
            download_link=download_url,
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

def approve_document_request(doc_req):
    """Mark a request approved and email the user. Shared by the API and Django admin."""
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

