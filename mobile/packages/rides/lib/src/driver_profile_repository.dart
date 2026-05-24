import 'dart:io';

import 'package:dio/dio.dart';
import 'package:network/network.dart';

class DriverProfileRepository {
  DriverProfileRepository({required this.client});

  final ApiClient client;

  Future<DriverMe> me() async {
    final response = await client.dio.get('/driver/me');
    final data =
        (response.data as Map<String, Object?>)['data'] as Map<String, Object?>;
    return DriverMe.fromJson(data);
  }

  Future<DriverApplication?> application() async {
    final response = await client.dio.get('/driver/application');
    final data = (response.data as Map<String, Object?>)['data'];
    if (data == null) return null;
    return DriverApplication.fromJson(data as Map<String, Object?>);
  }

  Future<DriverApplication> saveApplication(
    Map<String, Object?> payload,
  ) async {
    final response = await client.dio.put('/driver/application', data: payload);
    final data =
        (response.data as Map<String, Object?>)['data'] as Map<String, Object?>;
    return DriverApplication.fromJson(data);
  }

  Future<DriverApplication> submitApplication() async {
    final response = await client.dio.post('/driver/application/submit');
    final data =
        (response.data as Map<String, Object?>)['data'] as Map<String, Object?>;
    return DriverApplication.fromJson(data);
  }

  Future<DriverApplication> uploadApplicationDocument({
    required String docType,
    required File file,
  }) async {
    final response = await client.dio.post(
      '/driver/application/documents',
      data: FormData.fromMap({
        'doc_type': docType,
        'file': await MultipartFile.fromFile(file.path),
      }),
    );
    final data = (response.data as Map<String, Object?>)['application']
        as Map<String, Object?>;
    return DriverApplication.fromJson(data);
  }
}

class DriverMe {
  const DriverMe({
    required this.userId,
    required this.phone,
    required this.context,
  });

  final String userId;
  final String? phone;
  final DriverContext context;

  factory DriverMe.fromJson(Map<String, Object?> json) {
    return DriverMe(
      userId: json['id'] as String? ?? '',
      phone: json['phone'] as String?,
      context: DriverContext.fromJson(
        (json['driver_context'] as Map?)?.cast<String, Object?>() ?? const {},
      ),
    );
  }
}

class DriverContext {
  const DriverContext({
    required this.hasDriverProfile,
    required this.canGoOnline,
    required this.state,
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

    return DriverContext(
      hasDriverProfile: hasProfile,
      canGoOnline: canGoOnline,
      state: _runtimeState(
        hasProfile: hasProfile,
        canGoOnline: canGoOnline,
        driverStatus: driverStatus,
        applicationStatus: appStatus,
        vehicleStatus: vehicleStatus,
        onlineStatus: onlineStatus,
        reason: reason,
      ),
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
    required String? driverStatus,
    required String? applicationStatus,
    required String? vehicleStatus,
    required bool? onlineStatus,
    required String? reason,
  }) {
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
          .whereType<Map>()
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
