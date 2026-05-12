library localization;

import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

class SupportedLocales {
  static const ka = Locale('ka');
  static const en = Locale('en');
  static const ru = Locale('ru');

  static const all = [ka, en, ru];

  static const delegates = [
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ];
}
