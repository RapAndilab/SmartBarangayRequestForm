import os
from datetime import datetime
from django.core.mail import send_mail
import requests
from requests.auth import HTTPBasicAuth

from django.conf import settings

from django.utils import timezone

from rest_framework import views, permissions
from django.contrib.auth import authenticate, login
from rest_framework import status, views
from rest_framework.response import Response
from rest_framework.parsers import MultiPartParser, FormParser

from django.core.files.storage import default_storage

from deepface import DeepFace

from api.models import CustomUser, UserDocumentRequest
from api.utils import generate_document_file

from .serializers import CustomUserSerializer, DocumentRequestSerializer

# USER AUTHENTICATION
class UserCreateView(views.APIView):
    parser_classes = (MultiPartParser, FormParser)

    def post(self, request):
        serializer = CustomUserSerializer(data=request.data)
        if serializer.is_valid():
            serializer.save()
            return Response(serializer.data, status=status.HTTP_201_CREATED)
        return Response(serializer.errors, status=status.HTTP_200_OK)

class UserLoginView(views.APIView):
    def post(self, request):
        username = request.data.get('username')
        password = request.data.get('password')

        if not username or not password:
            return Response({"error": "Username and password required"}, status=status.HTTP_200_OK)

        user = authenticate(request, username=username, password=password)
        if user is not None:
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
class VerifyFaceView(views.APIView):
    def post(self, request):
        uploaded_image = request.FILES['captured_face']
        temp_path = default_storage.save("temp_uploaded.jpg", uploaded_image)
        temp_full_path = default_storage.path(temp_path)

        try:
            # Loop through saved user images
            for user in CustomUser.objects.exclude(image=''):
                user_image_path = user.image.path
                try:
                    # DeepFace verify returns a dict with 'verified': True/False
                    result = DeepFace.verify(
                        img1_path=temp_full_path,
                        img2_path=user_image_path,
                        model_name="VGG-Face",  # Options: "Facenet", "ArcFace", etc.
                        enforce_detection=True  # Will throw error if no face found
                    )
                    
                    if result.get("verified", False):
                        return Response(
                            {"match": f"{user.first_name} {user.last_name}"},
                            status=status.HTTP_200_OK
                        )
                except Exception as e:
                    # Skip if face not detected in one of the images
                    continue
                
            return Response({"match": None}, status=status.HTTP_200_OK)

        except Exception as e:
            return Response({"error": str(e)}, status=status.HTTP_400_BAD_REQUEST)
        finally:
            # Clean up temp file
            if os.path.exists(temp_full_path):
                os.remove(temp_full_path)

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

        # -SMS NOTIFICATION-
        print("Sending SMS")
        try:
            payload = {
                    "textMessage": {"text": admin_message},
                    "phoneNumbers": [settings.ADMIN_PHONE_NUMBER]
                }
            response = requests.post(
                settings.LOCAL_SMS_URL,
                json=payload,
                auth=HTTPBasicAuth(settings.USERNAME, settings.PASSWORD)
            )
            print("SMS sent successfully")
        except Exception as e:
            print("Failed to send SMS:", response.text)
        # ------------------------------------------------------------

        serializer = DocumentRequestSerializer(doc_request)
        return Response(serializer.data, status=status.HTTP_200_OK)

class DocumentConfirmRequestView(views.APIView):
    def post(self, request):
        try:
            doc_req = UserDocumentRequest.objects.get(id=request.data.get("request_id"))
        except UserDocumentRequest.DoesNotExist:
            return Response({"error": "Request not found"}, status=status.HTTP_404_NOT_FOUND)

        doc_req.confirmed = True
        doc_req.confirmed_at = timezone.now()
        doc_req.save()

        # -EMAIL NOTIFICATION TO USER-
        if doc_req.user.email:
            try:
                send_mail(
                    subject="Your payment has been confirmed",
                    message=(
                        f"Hi '{doc_req.user.first_name} {doc_req.user.last_name}',\n\n"
                        f"Your payment for '{doc_req.document_type}' "
                        f"has been confirmed.\n\n"
                        f"You may now download your document.\n"
                    ),
                    from_email=settings.DEFAULT_FROM_EMAIL,
                    recipient_list=[doc_req.user.email]
                )
            except Exception as e:
                print("Email error:", e)
        # ------------------------------------------------------------

        return Response({"message": "Payment confirmed and download link updated."})

