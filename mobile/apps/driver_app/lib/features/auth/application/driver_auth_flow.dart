enum DriverAuthFlow {
  login,
  registration,
}

extension DriverAuthFlowCopy on DriverAuthFlow {
  static const backendDriverPurpose = 'driver_signup';

  String get queryValue => switch (this) {
        DriverAuthFlow.login => 'login',
        DriverAuthFlow.registration => 'registration',
      };

  String get otpPurpose => backendDriverPurpose;

  String get phoneTitle => switch (this) {
        DriverAuthFlow.login => 'შესვლა',
        DriverAuthFlow.registration => 'მძღოლად რეგისტრაცია',
      };

  String get phoneSubtitle => switch (this) {
        DriverAuthFlow.login => 'თუ უკვე დარეგისტრირებული ხართ მძღოლად',
        DriverAuthFlow.registration =>
          'შეავსეთ განაცხადი და დაელოდეთ დადასტურებას',
      };

  String get otpTitle => switch (this) {
        DriverAuthFlow.login => 'კოდით შესვლა',
        DriverAuthFlow.registration => 'რეგისტრაციის დადასტურება',
      };

  String get otpActionLabel => switch (this) {
        DriverAuthFlow.login => 'შესვლა',
        DriverAuthFlow.registration => 'განაცხადის გაგრძელება',
      };

  String get roleMismatchMessage => switch (this) {
        DriverAuthFlow.login =>
          'ეს ნომერი მძღოლის ანგარიშად არ არის რეგისტრირებული. გსურთ მძღოლად რეგისტრაცია?',
        DriverAuthFlow.registration =>
          'Driver აპმა მიიღო არასწორი ავტორიზაციის როლი. სცადეთ მძღოლად რეგისტრაცია თავიდან.',
      };
}

DriverAuthFlow driverAuthFlowFromQuery(String? value) {
  return switch (value) {
    'login' => DriverAuthFlow.login,
    'registration' || 'register' || 'signup' => DriverAuthFlow.registration,
    _ => DriverAuthFlow.registration,
  };
}
