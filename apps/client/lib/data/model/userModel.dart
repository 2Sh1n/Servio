import 'dart:convert';

enum LogInType {
  google,
  apple,
  phone,
  email;

  static LogInType fromAPIValue(final String value) {
    switch (value) {
      case 'google':
        return google;
      case 'apple':
        return apple;
      case 'phone':
        return phone;
      case 'email':
        return email;
      default:
        return phone;
    }
  }

  String get apiValue {
    switch (this) {
      case LogInType.google:
        return 'google';
      case LogInType.apple:
        return 'apple';
      case LogInType.phone:
        return 'phone';
      case LogInType.email:
        return 'email';
    }
  }

  bool get hasPassword {
    switch (this) {
      case LogInType.phone:
      case LogInType.email:
        return true;
      default:
        return false;
    }
  }
}

class UserDetailsModel {
  String? id;
  String? username;
  String? phone;
  String? countryCode;
  String? countryCodeName;
  LogInType? loginType;
  String? email;
  String? fcmId;
  String? image;
  String? latitude;
  String? longitude;
  String? cityId;
  bool? hasPassword;

  UserDetailsModel({
    this.id,
    this.username,
    this.phone,
    this.countryCode,
    this.email,
    this.fcmId,
    this.image,
    this.latitude,
    this.longitude,
    this.cityId,
    this.loginType,
    this.countryCodeName,
    this.hasPassword,
  });

  UserDetailsModel copyWith({
    final String? id,
    final String? username,
    final String? phone,
    final String? countryCode,
    final String? email,
    final String? fcmId,
    final String? image,
    final String? latitude,
    final String? longitude,
    final String? cityId,
    final LogInType? loginType,
    final String? countryCodeName,
    final bool? hasPassword,
  }) =>
      UserDetailsModel(
        id: id ?? this.id,
        username: username ?? this.username,
        phone: phone ?? this.phone,
        email: email ?? this.email,
        fcmId: fcmId ?? this.fcmId,
        image: image ?? this.image,
        latitude: latitude ?? this.latitude,
        longitude: longitude ?? this.longitude,
        cityId: cityId ?? this.cityId,
        loginType: loginType ?? this.loginType,
        countryCode: countryCode ?? this.countryCode,
        countryCodeName: countryCodeName ?? this.countryCodeName,
        hasPassword: hasPassword ?? this.hasPassword,
      );

  Map<String, dynamic> toMap() => <String, dynamic>{
        "id": id,
        "username": username,
        "phone": phone,
        "email": email,
        "fcm_id": fcmId,
        "image": image,
        "latitude": latitude,
        "longitude": longitude,
        "city_id": cityId,
        "country_code": countryCode,
        "login_type": loginType?.apiValue,
        "countryCodeName": countryCodeName,
        "has_password": hasPassword,
      };

  factory UserDetailsModel.fromMap(final Map<String, dynamic> map) =>
      UserDetailsModel(
        id: map["id"] != null ? map["id"].toString() : null,
        username: map["username"] != null ? map["username"].toString() : null,
        phone: map["phone"] != null ? map["phone"].toString() : null,
        countryCode:
            map["country_code"] != null ? map["country_code"].toString() : null,
        email: map["email"] != null ? map["email"].toString() : null,
        fcmId: map["fcm_id"] != null ? map["fcm_id"].toString() : null,
        image: map["image"] != null ? map["image"].toString() : null,
        latitude: map["latitude"] != null ? map['latitude'].toString() : null,
        longitude:
            map["longitude"] != null ? map['longitude'].toString() : null,
        cityId: map["city_id"] != null ? map["city_id"].toString() : null,
        loginType: map["login_type"] != null
            ? LogInType.fromAPIValue(map["login_type"].toString())
            : null,
        countryCodeName: map["countryCodeName"] != null
            ? map["countryCodeName"].toString()
            : null,
        hasPassword: map["has_password"] != null
            ? map["has_password"].toString() == "1"
            : null,
      );

  UserDetailsModel fromMapCopyWith(final Map<String, dynamic> map) =>
      UserDetailsModel(
        id: map["id"] != null ? map["id"].toString() : id,
        username:
            map["username"] != null ? map["username"].toString() : username,
        phone: map["phone"] != null ? map["phone"].toString() : phone,
        countryCode: map["country_code"] != null
            ? map["country_code"].toString()
            : countryCode,
        email: map["email"] != null ? map["email"].toString() : email,
        fcmId: map["fcm_id"] != null ? map["fcm_id"].toString() : fcmId,
        image: map["image"] != null ? map["image"].toString() : image,
        latitude:
            map["latitude"] != null ? map["latitude"].toString() : latitude,
        longitude:
            map["longitude"] != null ? map["longitude"].toString() : longitude,
        cityId: map["city_id"] != null ? map["city_id"].toString() : cityId,
        countryCodeName: map["countryCodeName"] != null
            ? map["countryCodeName"].toString()
            : countryCodeName,
        loginType: map["login_type"] != null
            ? LogInType.fromAPIValue(map["login_type"].toString())
            : loginType,
        hasPassword: map["has_password"] != null
            ? map["has_password"].toString() == "1"
            : hasPassword,
      );

  String toJson() => json.encode(toMap());

  factory UserDetailsModel.fromJson(final String source) =>
      UserDetailsModel.fromMap(json.decode(source) as Map<String, dynamic>);

  @override
  String toString() =>
      "UserDetailsModel(id: $id, username: $username, phone: $phone, email: $email, fcm_id: $fcmId, image: $image, latitude: $latitude, longitude: $longitude, city_id: $cityId)";

  @override
  bool operator ==(final Object other) {
    if (identical(this, other)) return true;

    return other is UserDetailsModel &&
        other.id == id &&
        other.username == username &&
        other.phone == phone &&
        other.email == email &&
        other.fcmId == fcmId &&
        other.image == image &&
        other.latitude == latitude &&
        other.longitude == longitude &&
        other.cityId == cityId;
  }

  @override
  int get hashCode =>
      id.hashCode ^
      username.hashCode ^
      phone.hashCode ^
      email.hashCode ^
      fcmId.hashCode ^
      image.hashCode ^
      latitude.hashCode ^
      longitude.hashCode ^
      cityId.hashCode;
}
