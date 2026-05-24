import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:rides/rides.dart';

import '../../../di/locator.dart';

final driverMeProvider = FutureProvider.autoDispose<DriverMe>((ref) {
  return ref.watch(driverProfileRepositoryProvider).me();
});
