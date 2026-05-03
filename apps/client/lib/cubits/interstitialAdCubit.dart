// ignore_for_file: avoid_print

import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/widgets.dart';

abstract class InterstitialAdState {}

class InterstitialAdInitial extends InterstitialAdState {}

class InterstitialAdLoaded extends InterstitialAdState {}

class InterstitialAdLoadInProgress extends InterstitialAdState {}

class InterstitialAdFailToLoad extends InterstitialAdState {}

class InterstitialAdCubit extends Cubit<InterstitialAdState> {
  InterstitialAdCubit() : super(InterstitialAdInitial());

  InterstitialAd? _interstitialAd;

  InterstitialAd? get interstitialAd => _interstitialAd;

  void _createGoogleInterstitialAd(BuildContext context) {
    InterstitialAd.load(
      adUnitId: context.read<SystemSettingCubit>().InterstitialAdId,
      request: const AdRequest(),
      adLoadCallback: InterstitialAdLoadCallback(
        onAdLoaded: (InterstitialAd ad) {
          _interstitialAd = ad;
          emit(InterstitialAdLoaded());
        },
        onAdFailedToLoad: (err) {
          emit(InterstitialAdFailToLoad());
        },
      ),
    );
  }

  void loadInterstitialAd(BuildContext context) {
    final systemConfigCubit = context.read<SystemSettingCubit>();
    if (systemConfigCubit.isAdEnabled) {
      emit(InterstitialAdLoadInProgress());

      _createGoogleInterstitialAd(context);
    }
  }

  Future<void> showAd(BuildContext context, [Function()? callback]) async {
    //if ad is enable
    final sysConfigCubit = context.read<SystemSettingCubit>();
    if (sysConfigCubit.isAdEnabled) {
      //if ad loaded successfully
      if (state is InterstitialAdLoaded) {
        //show google interstitial ad

        interstitialAd?.fullScreenContentCallback = FullScreenContentCallback(
          onAdShowedFullScreenContent: (InterstitialAd ad) {
            if (callback != null) {
              callback.call();
            }
          },
          onAdDismissedFullScreenContent: (InterstitialAd ad) {
            ad.dispose();
            // loadInterstitialAd(context);
          },
          onAdFailedToShowFullScreenContent:
              (InterstitialAd ad, AdError error) {
            ad.dispose();
            //loadInterstitialAd(context);
          },
        );
        await interstitialAd?.show();
        await SystemChrome.setEnabledSystemUIMode(SystemUiMode.manual,
            overlays: SystemUiOverlay.values);
      } else if (state is InterstitialAdFailToLoad) {
        loadInterstitialAd(context);
      }
    }
  }

  @override
  Future<void> close() async {
    _interstitialAd?.dispose();

    return super.close();
  }
}
