import 'dart:io';

import 'package:core/core.dart';
import 'package:dio/dio.dart';
import 'package:network/network.dart';

class DriverProfileRepository {
  DriverProfileRepository({required this.client});

  final ApiClient client;
  static const mePath = '/auth/me';

  Future<DriverMe> me() async {
    final response = await client.dio.get<Map<String, Object?>>(mePath);
    final data = response.data!['data'] as Map<String, Object?>;
    final me = DriverMe.fromJson(data);
    if (me.userType != 'driver') {
      throw ApiError(
        code: 'auth.wrong_app_context',
        message:
            'Driver app received a ${me.userType} token. Please sign in as a driver.',
        httpStatus: response.statusCode,
        details: {'user_type': me.userType},
      );
    }
    return me;
  }

  Future<DriverApplication?> application() async {
    try {
      final response =
          await client.dio.get<Map<String, Object?>>('/driver/application');
      final data = response.data!['data'];
      if (data == null) return null;
      return DriverApplication.fromJson(data as Map<String, Object?>);
    } on ApiError catch (error) {
      if (error.httpStatus == 404) return null;
      rethrow;
    }
  }

  Future<DriverApplication> saveApplication(
    Map<String, Object?> payload,
  ) async {
    final response = await client.dio.put<Map<String, Object?>>(
      '/driver/application',
      data: payload,
    );
    final data = response.data!['data'] as Map<String, Object?>;
    return DriverApplication.fromJson(data);
  }

  Future<DriverApplication> submitApplication() async {
    final response = await client.dio
        .post<Map<String, Object?>>('/driver/application/submit');
    final data = response.data!['data'] as Map<String, Object?>;
    return DriverApplication.fromJson(data);
  }

  Future<DriverApplication> uploadApplicationDocument({
    required String docType,
    required File file,
  }) async {
    final response = await client.dio.post<Map<String, Object?>>(
      '/driver/application/documents',
      data: FormData.fromMap({
        'doc_type': docType,
        'file': await MultipartFile.fromFile(file.path),
      }),
    );
    final data = response.data!['application'] as Map<String, Object?>;
    return DriverApplication.fromJson(data);
  }
}

class DriverMe {
  const DriverMe({
    required this.userId,
    required this.userType,
    required this.phone,
    required this.context,
  });

  final String userId;
  final String userType;
  final String? phone;
  final DriverContext context;

  factory DriverMe.fromJson(Map<String, Object?> json) {
    final user = (json['user'] as Map?)?.cast<String, Object?>() ?? json;
    final driverContext =
        (json['driver_context'] as Map?)?.cast<String, Object?>() ??
            (user['driver_context'] as Map?)?.cast<String, Object?>() ??
            const <String, Object?>{};

    return DriverMe(
      userId: _string(user['id']) ?? '',
      userType: _string(user['type'] ?? json['user_type'] ?? json['type']) ??
          'unknown',
      phone: _string(user['phone'] ?? json['phone']),
      context: DriverContext.fromJson(driverContext),
    );
  }

  static String? _string(Object? value) {
    if (value == null) return null;
    final text = value.toString().trim();
    return text.isEmpty ? null : text;
  }
}

class DriverContext {
  const DriverContext({
    required this.hasDriverProfile,
    required this.canGoOnline,
    required this.state,
    this.needsApplication = false,
    this.canSubmitApplication = false,
    this.driverProfileStatus,
    this.applicationStatus,
    this.vehicleStatus,
    this.reasonIfCannotGoOnline,
    this.rejectionReason,
    this.todayEarnings,
    this.onlineStatus,
    this.missingRequiredFields = const [],
    this.missingDocuments = const [],
  });

  final bool hasDriverProfile;
  final bool canGoOnline;
  final DriverRuntimeState state;
  final bool needsApplication;
  final bool canSubmitApplication;
  final String? driverProfileStatus;
  final String? applicationStatus;
  final String? vehicleStatus;
  final String? reasonIfCannotGoOnline;
  final String? rejectionReason;
  final String? todayEarnings;
  final bool? onlineStatus;
  final List<String> missingRequiredFields;
  final List<String> missingDocuments;

  bool get canShowDashboard =>
      hasDriverProfile &&
      (canGoOnline ||
          state == DriverRuntimeState.online ||
          state == DriverRuntimeState.offline);

  bool get canShowShiftControls => canGoOnline;

  bool get canShowEarnings =>
      hasDriverProfile && driverProfileStatus == 'approved' && canShowDashboard;

  factory DriverContext.fromJson(Map<String, Object?> json) {
    final hasProfile = json['has_driver_profile'] == true;
    final canGoOnline = json['can_go_online'] == true;
    final driverStatus = json['driver_profile_status'] as String?;
    final appStatus = json['application_status'] as String?;
    final vehicleStatus = json['vehicle_status'] as String?;
    final onlineStatus = json['online_status'] as bool?;
    final reason = json['reason_if_cannot_go_online'] as String?;
    final needsApplication = json['needs_application'] == true;
    final canSubmitApplication = json['can_submit_application'] == true;

    return DriverContext(
      hasDriverProfile: hasProfile,
      canGoOnline: canGoOnline,
      state: _runtimeState(
        hasProfile: hasProfile,
        canGoOnline: canGoOnline,
        needsApplication: needsApplication,
        canSubmitApplication: canSubmitApplication,
        driverStatus: driverStatus,
        applicationStatus: appStatus,
        vehicleStatus: vehicleStatus,
        onlineStatus: onlineStatus,
        reason: reason,
      ),
      needsApplication: needsApplication,
      canSubmitApplication: canSubmitApplication,
      driverProfileStatus: driverStatus,
      applicationStatus: appStatus,
      vehicleStatus: vehicleStatus,
      reasonIfCannotGoOnline: reason,
      rejectionReason: json['rejection_reason'] as String?,
      todayEarnings: json['today_earnings'] as String?,
      onlineStatus: onlineStatus,
      missingRequiredFields: _stringList(json['missing_required_fields']),
      missingDocuments: _stringList(json['missing_documents']),
    );
  }

  static DriverRuntimeState _runtimeState({
    required bool hasProfile,
    required bool canGoOnline,
    required bool needsApplication,
    required bool canSubmitApplication,
    required String? driverStatus,
    required String? applicationStatus,
    required String? vehicleStatus,
    required bool? onlineStatus,
    required String? reason,
  }) {
    if (!hasProfile && (needsApplication || canSubmitApplication)) {
      return DriverRuntimeState.noDriverProfile;
    }
    if (!hasProfile) {
      return switch (applicationStatus) {
        'draft' => DriverRuntimeState.applicationDraft,
        'submitted' || 'pending' => DriverRuntimeState.applicationPending,
        'rejected' => DriverRuntimeState.applicationRejected,
        'needs_changes' => DriverRuntimeState.applicationDraft,
        _ => DriverRuntimeState.noDriverProfile,
      };
    }
    if (driverStatus == 'suspended' || reason == 'driver.suspended') {
      return DriverRuntimeState.suspended;
    }
    if (driverStatus == 'rejected') {
      return DriverRuntimeState.applicationRejected;
    }
    if (driverStatus == 'approved' && vehicleStatus == 'missing') {
      return DriverRuntimeState.approvedMissingVehicle;
    }
    if (canGoOnline) {
      return onlineStatus == true
          ? DriverRuntimeState.online
          : DriverRuntimeState.offline;
    }
    return DriverRuntimeState.applicationPending;
  }

  static List<String> _stringList(Object? value) {
    if (value is! List) return const [];
    return value.whereType<String>().toList(growable: false);
  }
}

enum DriverRuntimeState {
  guest,
  noDriverProfile,
  applicationDraft,
  applicationPending,
  applicationRejected,
  approvedMissingVehicle,
  approvedReady,
  online,
  offline,
  suspended,
}

class DriverApplication {
  const DriverApplication({
    this.id,
    this.status = 'draft',
    this.rejectionReason,
    this.documents = const [],
    this.raw = const {},
  });

  final int? id;
  final String status;
  final String? rejectionReason;
  final List<DriverApplicationDocument> documents;
  final Map<String, Object?> raw;

  factory DriverApplication.fromJson(Map<String, Object?> json) {
    return DriverApplication(
      id: json['id'] as int?,
      status: json['status'] as String? ?? 'draft',
      rejectionReason: json['rejection_reason'] as String?,
      documents: ((json['documents'] as List?) ?? const [])
          .whereType<Map<Object?, Object?>>()
          .map((item) => DriverApplicationDocument.fromJson(
                item.cast<String, Object?>(),
              ))
          .toList(growable: false),
      raw: json,
    );
  }
}

class DriverApplicationDocument {
  const DriverApplicationDocument({
    required this.docType,
    required this.status,
    this.previewName,
  });

  final String docType;
  final String status;
  final String? previewName;

  factory DriverApplicationDocument.fromJson(Map<String, Object?> json) {
    return DriverApplicationDocument(
      docType: json['doc_type'] as String? ?? '',
      status: json['status'] as String? ?? 'pending',
      previewName: json['preview_name'] as String?,
    );
  }
}
