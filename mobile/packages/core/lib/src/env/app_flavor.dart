enum AppFlavor { dev, staging, prod }

extension AppFlavorX on AppFlavor {
  bool get isDev => this == AppFlavor.dev;
  bool get isStaging => this == AppFlavor.staging;
  bool get isProd => this == AppFlavor.prod;
}
