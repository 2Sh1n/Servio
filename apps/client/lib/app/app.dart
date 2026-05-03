import 'package:e_demand/app/generalImports.dart';
import 'package:e_demand/app/registerBlocks.dart';
import 'package:flutter/material.dart';

Future<void> initApp() async {
  WidgetsFlutterBinding.ensureInitialized();

  //locked in portrait mode only
  await SystemChrome.setPreferredOrientations(
    [DeviceOrientation.portraitUp, DeviceOrientation.portraitDown],
  );

  if (Firebase.apps.isNotEmpty) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  } else {
    await Firebase.initializeApp();
  }

  await Hive.initFlutter();
  await HiveRepository.init();

  ErrorWidget.builder = (FlutterErrorDetails errorDetails) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.error_outline_outlined,
            color: Colors.red,
            size: 100,
          ),
          CustomText(
            errorDetails.exception.toString(),
          ),
        ],
      ),
    );
  };

  HttpOverrides.global = MyHttpOverrides();

  runApp(
    MultiBlocProvider(
      providers: registerBlocks(),
      child: const App(),
    ),
  );
}

class App extends StatefulWidget {
  const App({final Key? key}) : super(key: key);

  @override
  State<App> createState() => _AppState();
}

class _AppState extends State<App> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();

    WidgetsBinding.instance.addObserver(this);
    ClarityService.logAction(
      ClarityActions.appLaunch,
      {
        'timestamp': DateTime.now().toIso8601String(),
      },
    );

    Future.delayed(Duration.zero).then((final value) {
      if (HiveRepository.isDarkThemeUsing) {
        context.read<AppThemeCubit>().changeTheme(AppTheme.dark);
      } else {
        context.read<AppThemeCubit>().changeTheme(AppTheme.light);
      }
    });
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
        ClarityService.logAction(
          ClarityActions.appResume,
          {
            'timestamp': DateTime.now().toIso8601String(),
          },
        );
        break;
      case AppLifecycleState.paused:
      case AppLifecycleState.inactive:
      case AppLifecycleState.detached:
      case AppLifecycleState.hidden:
        ClarityService.logAction(
          ClarityActions.appBackground,
          {
            'timestamp': DateTime.now().toIso8601String(),
            'state': state.name,
          },
        );
        break;
    }
  }

  @override
  Widget build(final BuildContext context) => Builder(
        builder: (final context) {
          final AppTheme currentTheme =
              context.watch<AppThemeCubit>().state.appTheme;
          return BlocBuilder<LanguageDataCubit, LanguageDataState>(
              builder: (context, languageState) {
            return GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () {
                FocusManager.instance.primaryFocus?.unfocus();
              },
              child: MicrosoftClarityInitializer(
                projectId: microsoftClarityProjectId,
                child: MaterialApp(
                  //supportedLocales: supportedLocales,
                  theme: appThemeData[currentTheme],
                  title: appName,
                  debugShowCheckedModeBanner: false,
                  navigatorKey: UiUtils.rootNavigatorKey,
                  navigatorObservers: [clarityRouteObserver],
                  onGenerateRoute: Routes.onGeneratedRoute,
                  initialRoute: splashRoute,
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
            );
          });
        },
      );

  dynamic loadLocalLanguageIfFail(LanguageDataState state) {
    if (state is GetLanguageDataSuccess) {
      return Locale(state.currentLanguage.languageCode);
    } else if (state is GetLanguageDataError) {
      return const Locale("en");
    }
  }
}

///To remove scroll-glow from the ListView/GridView etc..
class CustomScrollBehaviour extends ScrollBehavior {
  @override
  Widget buildOverscrollIndicator(
    final BuildContext context,
    final Widget child,
    final ScrollableDetails details,
  ) =>
      child;
}

///To apply BouncingScrollPhysics() to every scrollable widget
class GlobalScrollBehavior extends ScrollBehavior {
  @override
  ScrollPhysics getScrollPhysics(final BuildContext context) =>
      const BouncingScrollPhysics();
}

class MyHttpOverrides extends HttpOverrides {
  @override
  HttpClient createHttpClient(SecurityContext? context) {
    return super.createHttpClient(context)
      ..badCertificateCallback =
          (X509Certificate cert, String host, int port) => true;
  }
}
