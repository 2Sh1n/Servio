import 'package:e_demand/app/generalImports.dart';
import 'package:flutter/material.dart';

class CountDownTimer {
  StreamController timerController = StreamController.broadcast();
  StreamSubscription<int>? _subscription;

  int maxTimeValue = resendOTPCountDownTime;
  Stream get listenChanges => timerController.stream;

  void start(final VoidCallback onEnd) {
    final stream = Stream<int>.periodic(
      const Duration(seconds: 1),
      (final int computationCount) {
        maxTimeValue -= 1;

        return maxTimeValue;
      },
    );
    _subscription?.cancel();

    _subscription = stream.listen((final int data) {
      if (!timerController.isClosed) {
        timerController.add(data);

        if (data == 0) {
          maxTimeValue = resendOTPCountDownTime;
          onEnd();
          _subscription?.pause();
          _subscription?.cancel();
        }
      }
    });
  }

  StreamBuilder listenText({final Color? color}) => StreamBuilder(
        stream: timerController.stream,
        builder: (final BuildContext context, final AsyncSnapshot snapshot) {
          if (!snapshot.hasData) {
            return CustomText(
              "00:${resendOTPCountDownTime.toString().padLeft(2, '0')}",
              color: color,
              fontWeight: FontWeight.w400,
              fontStyle: FontStyle.normal,
              fontSize: 14,
              textAlign: TextAlign.center,
            );
          }

          return CustomText(
            "00:${snapshot.data.toString().padLeft(2, '0')}",
            color: color,
            fontWeight: FontWeight.w400,
            fontStyle: FontStyle.normal,
            fontSize: 14,
            textAlign: TextAlign.center,
          );
        },
      );

  void pause() {
    _subscription?.pause();
  }

  void reset() {
    _subscription?.cancel();
    start(() {});
  }

  void resume() {
    _subscription?.resume();
  }

  void close() {
    _subscription?.cancel();
  }
}
