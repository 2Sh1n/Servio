/// Enum to manage user authentication status codes
/// Used throughout the app to handle different user states during login/registration
enum UserStatus {
  /// User is already registered and active (status code: 101)
  active('101', 'Mobile number already registered and Active'),

  /// User is not registered (status code: 102)
  notRegistered('102', 'Mobile number is not registered'),

  /// User account is deactivated (status code: 103)
  deactive('103', 'Mobile number is De active');

  const UserStatus(this.code, this.description);

  /// The status code string returned from the API
  final String code;

  /// Human-readable description of the status
  final String description;

  /// Convert a status code string to UserStatus enum
  /// Returns null if the code doesn't match any known status
  static UserStatus? fromCode(String code) {
    try {
      return UserStatus.values.firstWhere(
        (status) => status.code == code,
      );
    } catch (e) {
      return null;
    }
  }
}
