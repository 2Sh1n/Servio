import 'dart:convert';

import 'package:edemand_partner/app/generalImports.dart';

class AppLocalization {
  final Locale locale;

  late Map<String, String> _localizedValues;

  AppLocalization(this.locale);

  static AppLocalization? of(BuildContext context) {
    return Localizations.of(context, AppLocalization);
  }

  Future loadJson() async {
    final String jsonStringValues = await rootBundle.loadString(
      'assets/languages/en.json',
    );
    Map<String, dynamic> mappedJson = {};

    if (HiveRepository.getLanguageJsonData() == null) {
      mappedJson = json.decode(jsonStringValues);
    } else {
      mappedJson = Map<String, dynamic>.from(
        HiveRepository.getLanguageJsonData(),
      );
    }
    _localizedValues = mappedJson.map(
      (key, value) => MapEntry(key, value.toString()),
    );
  }

  String? getTranslatedValues(String? key) {
    return _localizedValues[key!];
  }

  static const LocalizationsDelegate<AppLocalization> delegate =
      _AppLocalizationDelegate();
}

class _AppLocalizationDelegate extends LocalizationsDelegate<AppLocalization> {
  const _AppLocalizationDelegate();

  @override
  bool isSupported(Locale locale) {
    return true;
  }

  @override
  Future<AppLocalization> load(Locale locale) async {
    final AppLocalization localization = AppLocalization(locale);
    await localization.loadJson();
    return localization;
  }

  @override
  bool shouldReload(LocalizationsDelegate<AppLocalization> old) {
    return true;
  }
}
