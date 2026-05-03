import 'package:edemand_partner/app/generalImports.dart';
import 'package:edemand_partner/app/registerBlocks.dart';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

Future<void> initApp() async {
  WidgetsFlutterBinding.ensureInitialized();
  //locked in portrait mode only
  SystemChrome.setPreferredOrientations(<DeviceOrientation>[
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  if (Firebase.apps.isNotEmpty) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  } else {
    await Firebase.initializeApp();
  }

  await Hive.initFlutter();
  await HiveRepository.init();

  HttpOverrides.global = MyHttpOverrides();

  runApp(MultiBlocProvider(providers: registerBlocks(), child: const App()));
}

class App extends StatefulWidget {
  const App({super.key});

  @override
  State<App> createState() => _AppState();
}

class _AppState extends State<App> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    ClarityService.logAction(ClarityActions.appLaunch);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.resumed:
        ClarityService.logAction(ClarityActions.appResume);
        break;
      case AppLifecycleState.paused:
      case AppLifecycleState.detached:
      case AppLifecycleState.inactive:
      case AppLifecycleState.hidden:
        ClarityService.logAction(ClarityActions.appBackground);
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final AppTheme currentTheme = context.watch<AppThemeCubit>().state.appTheme;

    return BlocBuilder<LanguageDataCubit, LanguageDataState>(
      builder: (context, languageState) {
        return BlocBuilder<AppThemeCubit, ThemeState>(
          builder: (context, themeState) {
            return GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () {
                FocusManager.instance.primaryFocus?.unfocus();
              },
              child: AnnotatedRegion<SystemUiOverlayStyle>(
                value: UiUtils.getSystemUiOverlayStyle(
                  context: context,
                  appTheme: themeState.appTheme,
                ),
                child: MicrosoftClarityInitializer(
                  projectId: microsoftClarityProjectId,
                  child: MaterialApp(
                    title: appName,
                    debugShowCheckedModeBanner: false,
                    navigatorKey: UiUtils.rootNavigatorKey,
                    onGenerateRoute: Routes.onGenerateRouted,
                    initialRoute: Routes.splash,
                    theme: appThemeData[currentTheme],
                    navigatorObservers: [clarityRouteObserver],
                    builder: (context, child) {
                      TextDirection direction = TextDirection.ltr;

                      if (languageState is GetLanguageDataSuccess) {
                        direction = languageState.currentLanguage.isRtl == "1"
                            ? TextDirection.rtl
                            : TextDirection.ltr;
                      }
                      return Directionality(
                        textDirection: direction,
                        child: child!,
                      );
                    },
                    localizationsDelegates: const [
                      AppLocalization.delegate,
                      GlobalMaterialLocalizations.delegate,
                      GlobalWidgetsLocalizations.delegate,
                      GlobalCupertinoLocalizations.delegate,
                    ],
                    locale: loadLocalLanguageIfFail(languageState),
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  dynamic loadLocalLanguageIfFail(LanguageDataState state) {
    if (state is GetLanguageDataSuccess) {
      return Locale(state.currentLanguage.languageCode);
    } else if (state is GetLanguageDataError) {
      return const Locale("en");
    }
  }
}

class MyHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) {
    return super.createHttpClient(context)
      ..badCertificateCallback =
          (X509Certificate cert, String host, int port) => true;
  }
}
