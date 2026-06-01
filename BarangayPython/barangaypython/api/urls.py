from django.urls import path

from api.views import UserCreateView, UserLoginView, GetProfileImageView, DocumentRequestCreateView, DocumentProcessRequestView, DocumentConfirmRequestView, DownloadDocumentView, VerifyEmailView, ResendOtpView

urlpatterns = [
    path('register/', UserCreateView.as_view(), name='register'),
    path('login/', UserLoginView.as_view(), name='login'),
    path('verify_email/', VerifyEmailView.as_view(), name='verify_email'),
    path('resend_otp/', ResendOtpView.as_view(), name='resend_otp'),
    path('get_profile_image/', GetProfileImageView.as_view(), name='get_profile_image'),

    path('create_document_request/', DocumentRequestCreateView.as_view(), name='create_document_request'),
    path('manage_request/', DocumentProcessRequestView.as_view(), name='manage_request'),
    path('confirm_request/', DocumentConfirmRequestView.as_view(), name='confirm_request'),
    path('download/<int:request_id>/', DownloadDocumentView.as_view(), name='download_document'),
]