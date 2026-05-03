# Stripe SDK
-keep class com.stripe.android.** { *; }
-keepclassmembers class com.stripe.android.** { *; }
-dontwarn com.stripe.android.**

# Stripe Push Provisioning
-keep class com.stripe.android.pushProvisioning.** { *; }
-keepclassmembers class com.stripe.android.pushProvisioning.** { *; }
-dontwarn com.stripe.android.pushProvisioning.**

# Specifically keep the problematic classes
-keep class com.stripe.android.pushProvisioning.PushProvisioningActivity$g { *; }
-keep class com.stripe.android.pushProvisioning.PushProvisioningActivityStarter { *; }
-keep class com.stripe.android.pushProvisioning.PushProvisioningActivityStarter$* { *; }
-keep class com.stripe.android.pushProvisioning.PushProvisioningEphemeralKeyProvider { *; }

# React Native Stripe SDK
-keep class com.reactnativestripesdk.** { *; }
-keepclassmembers class com.reactnativestripesdk.** { *; }
-dontwarn com.reactnativestripesdk.**

# Razorpay
-keep class com.razorpay.** { *; }
-keepclassmembers class com.razorpay.** { *; }
-dontwarn com.razorpay.**

# Keep annotations and annotated elements
-keep,allowobfuscation @interface proguard.annotation.**
-keep @proguard.annotation.** class * { *; }
-keepclassmembers class * {
    @proguard.annotation.** *;
}

# General rules
-keepattributes *Annotation*
-keepattributes SourceFile,LineNumberTable
-keepattributes Signature
-keepattributes Exceptions

# Keep React Native classes
-keep class com.facebook.react.** { *; }
-dontwarn com.facebook.react.**

# Keep JavascriptInterface
-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

## Flutter wrapper
-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.util.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }
## uncomment this if you are using firebase in the project
-keep class com.google.firebase.** { *; }
-dontwarn io.flutter.embedding.**
-ignorewarnings