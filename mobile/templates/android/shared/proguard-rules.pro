# Hangover release ProGuard / R8 rules.
#
# Keep Flutter + plugins.  These rules are conservative — when shrinking
# discovers a missing keep, add it here rather than disabling R8.

# --- Flutter / Dart ---
-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.util.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.embedding.** { *; }
-keep class io.flutter.** { *; }
-keep class io.flutter.plugins.** { *; }

# --- Firebase / FCM ---
-keep class com.google.firebase.** { *; }
-keep class com.google.android.gms.** { *; }
-dontwarn com.google.firebase.**

# --- Google Maps ---
-keep class com.google.android.libraries.maps.** { *; }
-keep class com.google.android.gms.maps.** { *; }
-dontwarn com.google.android.gms.maps.**

# --- AndroidX ---
-keep class androidx.lifecycle.** { *; }
-keep class androidx.work.** { *; }

# --- Sentry: keep stack-trace symbolisation classes ---
-keep class io.sentry.** { *; }
-dontwarn io.sentry.**

# --- json_annotation generated code uses reflection in tests only ---
-keepclasseswithmembers class * {
    @org.json.annotations.JsonField <fields>;
}

# Suppress noisy warnings from transitive deps.
-dontwarn javax.annotation.**
-dontwarn org.bouncycastle.**
-dontwarn org.conscrypt.**
-dontwarn org.openjsse.**
