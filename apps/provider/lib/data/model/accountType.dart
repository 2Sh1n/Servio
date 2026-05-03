enum LoginType {
  email('email'),
  phone('phone');

  const LoginType(this.value);
  final String value;

  /// Convert API string value to LoginType enum
  static LoginType fromAPIValue(String? apiValue) {
    switch (apiValue?.toLowerCase()) {
      case 'email':
        return LoginType.email;
      case 'phone':
        return LoginType.phone;
      default:
        return LoginType.phone; // Default to phone for backward compatibility
    }
  }

  /// Check if login type is email
  bool get isEmail => this == LoginType.email;

  /// Check if login type is phone
  bool get isPhone => this == LoginType.phone;
}
