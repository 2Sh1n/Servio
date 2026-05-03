// ignore_for_file: avoid_print

import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class BannerAdWidget extends StatefulWidget {
  const BannerAdWidget({super.key});

  @override
  _BannerAdContainer createState() => _BannerAdContainer();
}

class _BannerAdContainer extends State<BannerAdWidget> {
  BannerAd? _googleBannerAd;

  @override
  void initState() {
    super.initState();
    _initBannerAd();
  }

  @override
  void dispose() {
    _googleBannerAd?.dispose();

    super.dispose();
  }

  void _initBannerAd() {
    Future.delayed(Duration.zero, () {
      final systemConfigCubit = context.read<SystemSettingCubit>();
      if (systemConfigCubit.isAdEnabled) {
        _createGoogleBannerAd();
      }
    });
  }

  Future<void> _createGoogleBannerAd() async {
    final AnchoredAdaptiveBannerAdSize? size =
        await AdSize.getCurrentOrientationAnchoredAdaptiveBannerAdSize(
            MediaQuery.of(context).size.width.truncate());

    if (size == null) {
      return;
    }

    final BannerAd banner = BannerAd(
      request: const AdRequest(),
      adUnitId: context.read<SystemSettingCubit>().BannerAdId,
      listener: BannerAdListener(
        onAdLoaded: (Ad ad) {
          setState(() {
            _googleBannerAd = ad as BannerAd;
          });
        },
        onAdFailedToLoad: (Ad ad, LoadAdError error) {},
        onAdOpened: (Ad ad) => print('$BannerAd onAdOpened'),
        onAdClosed: (Ad ad) => print('$BannerAd onAdClosed'),
      ),
      size: size,
    );
    banner.load();
  }

  @override
  Widget build(BuildContext context) {
    final sysConfig = context.read<SystemSettingCubit>();
    if (sysConfig.isAdEnabled) {
      return _googleBannerAd != null
          ? CustomSizedBox(
              width: context.screenWidth,
              height: _googleBannerAd!.size.height.toDouble(),
              child: AdWidget(ad: _googleBannerAd!),
            )
          : const SizedBox.shrink();
    }
    return const SizedBox.shrink();
  }
}
