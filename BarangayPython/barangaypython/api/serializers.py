from rest_framework import serializers
from .models import CustomUser, UserDocumentRequest

class CustomUserSerializer(serializers.ModelSerializer):
    class Meta:
        model = CustomUser
        fields =  [
            'id',
            'username',
            'first_name',
            'last_name',
            'email',
            'password',   # for creating the user, handled in create()
            'birthdate',
            'gender',
            'address',
            'phone',
            'image',
        ]

    def validate(self, data):
        if CustomUser.objects.filter(username=data['username']).exists():
            raise serializers.ValidationError("User with username already exists.")
        return data

    def create(self, validated_data):
        image = validated_data.pop("image", None)
        password = validated_data.pop("password", None)

        user = CustomUser(**validated_data)

        if image:
            # Extract extension
            ext = image.name.split('.')[-1]
            # Build new filename: firstname_lastname.ext (lowercase, no spaces)
            new_filename = f"{user.username.lower()}.{ext}"
            image.name = new_filename
            user.image = image

        if password:
            user.set_password(password)
        user.save()
        return user
    
class DocumentRequestCreateSerializer(serializers.ModelSerializer):
    class Meta:
        model = UserDocumentRequest
        # include all relevant fields for creation here
        fields = [
            'username', 'document_type', 'full_name', 'address', 'birth_date', 'birth_place', 
            'civil_status', 'citizenship', 'purpose', 'years'
        ]

class DocumentRequestSerializer(serializers.ModelSerializer):
    class Meta:
        model = UserDocumentRequest
        fields = ['id', 'user', 'document_type', 'full_name', 'payment_screenshot', 'confirmed', 'download_link']
        read_only_fields = ['user', 'confirmed', 'download_link']
