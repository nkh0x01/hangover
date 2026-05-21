// Hangover customer-app Android module.
//
// Flavors:
//   dev      → app.hangover.customer.dev      (debug build by default)
//   staging  → app.hangover.customer.staging
//   prod     → app.hangover.customer
//
// Signing:
//   Release builds load keystore creds from `android/key.properties`
//   (gitignored). Debug uses the standard debug keystore.

import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    id("kotlin-android")
    id("dev.flutter.flutter-gradle-plugin")
    // google-services is declared at the project level (settings.gradle.kts)
    // but only applied here when google-services.json is actually present.
    // This keeps Phase 2.6 QA builds working on a fresh machine that hasn't
    // wired up Firebase yet.
    id("com.google.gms.google-services") apply false
}

// Conditionally apply the google-services plugin so the build doesn't error
// out when google-services.json is missing — push delivery just becomes a
// no-op (Dart side already falls back to NullPushService).
val hasGoogleServices = file("google-services.json").exists()
if (hasGoogleServices) {
    apply(plugin = "com.google.gms.google-services")
} else {
    logger.lifecycle("[hangover] google-services.json not found — Firebase plugin skipped, app will run without FCM push.")
}

val keystoreProperties = Properties().apply {
    val keystoreFile = rootProject.file("key.properties")
    if (keystoreFile.exists()) {
        load(FileInputStream(keystoreFile))
    }
}

android {
    namespace = "app.hangover.customer"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        applicationId = "app.hangover.customer"
        minSdk = 23                   // FCM + flutter_secure_storage
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        multiDexEnabled = true

        manifestPlaceholders["MAPS_API_KEY"] =
            (project.findProperty("MAPS_API_KEY") as String?)
                ?: System.getenv("MAPS_API_KEY") ?: ""
    }

    flavorDimensions += "env"
    productFlavors {
        create("dev") {
            dimension = "env"
            applicationIdSuffix = ".dev"
            versionNameSuffix = "-dev"
            resValue("string", "app_name", "Hangover Dev")
        }
        create("staging") {
            dimension = "env"
            applicationIdSuffix = ".staging"
            versionNameSuffix = "-staging"
            resValue("string", "app_name", "Hangover Stg")
        }
        create("prod") {
            dimension = "env"
            resValue("string", "app_name", "Hangover")
        }
    }

    signingConfigs {
        create("release") {
            keyAlias = keystoreProperties["keyAlias"] as String?
            keyPassword = keystoreProperties["keyPassword"] as String?
            storeFile = (keystoreProperties["storeFile"] as String?)?.let { rootProject.file(it) }
            storePassword = keystoreProperties["storePassword"] as String?
        }
    }

    buildTypes {
        release {
            signingConfig = if ((keystoreProperties["storeFile"] as String?)?.isNotBlank() == true) {
                signingConfigs.getByName("release")
            } else {
                // Fall back to debug signing so CI can still produce an
                // installable APK without the prod keystore.
                signingConfigs.getByName("debug")
            }
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
        debug {
            applicationIdSuffix = ".debug"
            versionNameSuffix = "-debug"
            isMinifyEnabled = false
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    implementation("androidx.multidex:multidex:2.0.1")
    // Firebase libs link against the BOM but stay dormant when
    // google-services.json isn't present. The Dart-side push provider
    // already catches the missing-config case and uses NullPushService.
    if (hasGoogleServices) {
        implementation(platform("com.google.firebase:firebase-bom:33.5.1"))
        implementation("com.google.firebase:firebase-messaging-ktx")
        implementation("com.google.firebase:firebase-analytics-ktx")
    }
}
