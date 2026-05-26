import 'dart:io';

import 'package:core/core.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:rides/rides.dart';
import 'package:ui_kit/ui_kit.dart';

import '../../../di/locator.dart';
import '../../diagnostics/presentation/auth_diagnostics_panel.dart';
import '../../diagnostics/presentation/driver_build_identity_label.dart';
import '../../profile/state/driver_profile_controller.dart';

class DriverApplicationPage extends ConsumerStatefulWidget {
  const DriverApplicationPage({super.key});

  @override
  ConsumerState<DriverApplicationPage> createState() =>
      _DriverApplicationPageState();
}

class _DriverApplicationPageState extends ConsumerState<DriverApplicationPage> {
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _personalId = TextEditingController();
  final _phone = TextEditingController(text: '+995');
  final _email = TextEditingController();
  final _birthDate = TextEditingController();
  final _serviceZone = TextEditingController(text: 'Tbilisi');
  final _brand = TextEditingController();
  final _model = TextEditingController();
  final _year = TextEditingController();
  final _color = TextEditingController();
  final _plate = TextEditingController();
  final _engine = TextEditingController();
  final _insurance = TextEditingController();
  final _inspection = TextEditingController();

  final _picker = ImagePicker();
  final Map<String, File> _picked = {};
  final Map<String, DriverApplicationDocument> _uploaded = {};
  int _step = 0;
  bool _busy = false;
  bool _loaded = false;
  String? _error;
  String _driverType = 'moto';
  String _vehicleType = 'scooter_petrol';
  bool _confirmInfo = false;
  bool _acceptTerms = false;
  bool _acceptPrivacy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_loaded) return;
    _loaded = true;
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadDraft());
  }

  Future<void> _loadDraft() async {
    try {
      final application =
          await ref.read(driverProfileRepositoryProvider).application();
      if (application == null || !mounted) return;
      setState(() {
        _fillFrom(application);
        _error = application.rejectionReason;
        _uploaded
          ..clear()
          ..addEntries(application.documents.map((document) {
            return MapEntry(document.docType, document);
          }));
      });
    } on ApiError catch (error) {
      if (error.httpStatus == 404) return;
      if (!mounted) return;
      setState(() {
        _error = _kaApiError(error);
      });
    } catch (_) {
      // Opening the form must never dead-end because draft loading failed.
    }
  }

  Future<void> _pick(String docType) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final picked = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 78,
      );
      if (picked == null) return;
      final file = File(picked.path);
      await ref.read(driverProfileRepositoryProvider).uploadApplicationDocument(
            docType: docType,
            file: file,
          );
      if (!mounted) return;
      setState(() {
        _picked[docType] = file;
        _uploaded.remove(docType);
      });
      ref.invalidate(driverMeProvider);
    } on ApiError catch (e) {
      setState(() => _error = e.message);
    } catch (_) {
      setState(() => _error = 'ფაილის ატვირთვა ვერ მოხერხდა.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<bool> _saveDraft({bool submit = false}) async {
    final validation = _validate(submit: submit);
    if (validation != null) {
      setState(() => _error = validation);
      return false;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref
          .read(driverProfileRepositoryProvider)
          .saveApplication(_payload());
      if (submit) {
        await ref.read(driverProfileRepositoryProvider).submitApplication();
      }
      ref.invalidate(driverMeProvider);
      if (!mounted) return false;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            submit ? 'განაცხადი გაგზავნილია.' : 'განაცხადი შენახულია.',
          ),
        ),
      );
      if (submit) Navigator.of(context).pop();
      return true;
    } on ApiError catch (e) {
      setState(() => _error = _kaApiError(e));
    } catch (_) {
      setState(
        () => _error = 'სერვერთან კავშირი ვერ მოხერხდა. დეტალები ქვემოთ ჩანს.',
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
    return false;
  }

  void _fillFrom(DriverApplication application) {
    final data = application.raw;
    _setText(_firstName, data['first_name']);
    _setText(_lastName, data['last_name']);
    _setText(_personalId, data['personal_id']);
    _setText(_phone, data['phone_e164'], fallback: '+995');
    _setText(_email, data['email']);
    _setText(_birthDate, data['birth_date']);
    _setText(_serviceZone, data['service_zone'], fallback: 'Tbilisi');
    _setText(_brand, data['vehicle_brand']);
    _setText(_model, data['vehicle_model']);
    _setText(_year, data['vehicle_year']);
    _setText(_color, data['vehicle_color']);
    _setText(_plate, data['vehicle_plate']);
    _setText(_engine, data['engine_cc']);
    _setText(_insurance, data['insurance_expires_on']);
    _setText(_inspection, data['inspection_expires_on']);
    _driverType = _stringValue(data['driver_type'], _driverType) ?? _driverType;
    _vehicleType =
        _stringValue(data['vehicle_type'], _vehicleType) ?? _vehicleType;
    _confirmInfo = data['information_confirmed'] == true;
    _acceptTerms = data['terms_accepted'] == true;
    _acceptPrivacy = data['privacy_accepted'] == true;
  }

  void _setText(
    TextEditingController controller,
    Object? value, {
    String? fallback,
  }) {
    final text = _stringValue(value, fallback);
    if (text == null || text.isEmpty) return;
    controller.text = text;
  }

  String? _stringValue(Object? value, String? fallback) {
    if (value == null) return fallback;
    final text = value.toString().trim();
    return text.isEmpty ? fallback : text;
  }

  Map<String, Object?> _payload() {
    return {
      'first_name': _firstName.text.trim(),
      'last_name': _lastName.text.trim(),
      'personal_id': _personalId.text.trim(),
      'phone_e164': _phone.text.trim(),
      'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
      'birth_date':
          _birthDate.text.trim().isEmpty ? null : _birthDate.text.trim(),
      'service_zone': _serviceZone.text.trim(),
      'driver_type': _driverType,
      'vehicle_type': _vehicleType,
      'vehicle_brand': _brand.text.trim(),
      'vehicle_model': _model.text.trim(),
      'vehicle_year': int.tryParse(_year.text.trim()),
      'vehicle_color': _color.text.trim(),
      'vehicle_plate': _plate.text.trim(),
      'engine_cc': _engine.text.trim().isEmpty ? null : _engine.text.trim(),
      'insurance_expires_on':
          _insurance.text.trim().isEmpty ? null : _insurance.text.trim(),
      'inspection_expires_on':
          _inspection.text.trim().isEmpty ? null : _inspection.text.trim(),
      'information_confirmed': _confirmInfo,
      'terms_accepted': _acceptTerms,
      'privacy_accepted': _acceptPrivacy,
    };
  }

  String? _validate({required bool submit}) {
    final phoneOk = RegExp(r'^\+9955\d{8}$').hasMatch(_phone.text.trim());
    final personalOk = RegExp(r'^\d{9,11}$').hasMatch(_personalId.text.trim());
    if (!phoneOk) return 'ტელეფონის ნომერი უნდა იყოს +9955XXXXXXXX ფორმატში.';
    if (!personalOk) return 'პირადი ნომერი უნდა შედგებოდეს 9-11 ციფრისგან.';
    if (!submit) return null;
    if (_firstName.text.trim().isEmpty || _lastName.text.trim().isEmpty) {
      return 'სახელი და გვარი სავალდებულოა.';
    }
    if (_brand.text.trim().isEmpty ||
        _model.text.trim().isEmpty ||
        _plate.text.trim().isEmpty) {
      return 'ტრანსპორტის ძირითადი მონაცემები სავალდებულოა.';
    }
    if (!_confirmInfo || !_acceptTerms || !_acceptPrivacy) {
      return 'გთხოვთ დაადასტუროთ ინფორმაცია, წესები და კონფიდენციალურობა.';
    }
    return null;
  }

  String _kaApiError(ApiError e) {
    return switch (e.code) {
      'validation.failed' => 'გთხოვთ შეამოწმოთ შევსებული მონაცემები.',
      'auth.invalid_token' => 'სესიის ვადა ამოიწურა, გთხოვთ თავიდან შეხვიდეთ.',
      'auth.wrong_app_context' =>
        'ამ მოქმედებისთვის არ გაქვთ შესაბამისი წვდომა.',
      'driver.application_locked' => 'განაცხადი უკვე განხილვაშია.',
      'driver.application_incomplete' => 'განაცხადი სრულად არ არის შევსებული.',
      _ => e.message.isEmpty ? 'განაცხადის შენახვა ვერ მოხერხდა.' : e.message,
    };
  }

  Future<void> _clearSessionAndReturn() async {
    await ref.read(tokenStoreProvider).clear();
    ref.invalidate(driverMeProvider);
    if (mounted) context.go('/welcome');
  }

  @override
  void dispose() {
    for (final controller in [
      _firstName,
      _lastName,
      _personalId,
      _phone,
      _email,
      _birthDate,
      _serviceZone,
      _brand,
      _model,
      _year,
      _color,
      _plate,
      _engine,
      _insurance,
      _inspection,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('მძღოლის განაცხადი'),
        actions: [
          IconButton(
            tooltip: 'დიაგნოსტიკა',
            onPressed: () => context.push('/diagnostics'),
            icon: const Icon(Icons.info_outline_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            const Padding(
              padding: EdgeInsets.fromLTRB(Insets.l, Insets.s, Insets.l, 0),
              child: Align(
                alignment: Alignment.centerLeft,
                child: DriverBuildIdentityLabel(),
              ),
            ),
            Padding(
              padding:
                  const EdgeInsets.fromLTRB(Insets.l, Insets.s, Insets.l, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  OutlinedButton.icon(
                    onPressed: () => context.go('/welcome'),
                    icon: const Icon(Icons.home_outlined),
                    label: const Text('მთავარ არჩევანზე დაბრუნება'),
                  ),
                  const SizedBox(height: Insets.xs),
                  OutlinedButton.icon(
                    onPressed: _clearSessionAndReturn,
                    icon: const Icon(Icons.logout_rounded),
                    label: const Text('სესიის გასუფთავება'),
                  ),
                ],
              ),
            ),
            if (_error != null)
              Container(
                width: double.infinity,
                margin: const EdgeInsets.fromLTRB(
                  Insets.l,
                  Insets.m,
                  Insets.l,
                  0,
                ),
                padding: const EdgeInsets.all(Insets.m),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.errorContainer,
                  borderRadius: BorderRadius.circular(Radii.m),
                ),
                child: Text(_error!),
              ),
            if (_error != null)
              const Padding(
                padding: EdgeInsets.fromLTRB(Insets.l, Insets.s, Insets.l, 0),
                child: AuthDiagnosticsPanel(),
              ),
            Expanded(
              child: Stepper(
                currentStep: _step,
                onStepTapped: (value) => setState(() => _step = value),
                controlsBuilder: (context, details) => const SizedBox.shrink(),
                steps: [
                  Step(
                    title: const Text('პირადი ინფორმაცია'),
                    isActive: _step >= 0,
                    content: _PersonalStep(
                      firstName: _firstName,
                      lastName: _lastName,
                      personalId: _personalId,
                      phone: _phone,
                      email: _email,
                      birthDate: _birthDate,
                      serviceZone: _serviceZone,
                      driverType: _driverType,
                      onDriverType: (value) =>
                          setState(() => _driverType = value),
                    ),
                  ),
                  Step(
                    title: const Text('ტრანსპორტი'),
                    isActive: _step >= 1,
                    content: _VehicleStep(
                      vehicleType: _vehicleType,
                      onVehicleType: (value) =>
                          setState(() => _vehicleType = value),
                      brand: _brand,
                      model: _model,
                      year: _year,
                      color: _color,
                      plate: _plate,
                      engine: _engine,
                      insurance: _insurance,
                      inspection: _inspection,
                    ),
                  ),
                  Step(
                    title: const Text('დოკუმენტები'),
                    isActive: _step >= 2,
                    content: _DocumentsStep(
                      picked: _picked,
                      uploaded: _uploaded,
                      busy: _busy,
                      onPick: _pick,
                    ),
                  ),
                  Step(
                    title: const Text('დადასტურება'),
                    isActive: _step >= 3,
                    content: Column(
                      children: [
                        CheckboxListTile(
                          value: _confirmInfo,
                          onChanged: (value) =>
                              setState(() => _confirmInfo = value ?? false),
                          title:
                              const Text('ვადასტურებ, რომ ინფორმაცია სწორია'),
                        ),
                        CheckboxListTile(
                          value: _acceptTerms,
                          onChanged: (value) =>
                              setState(() => _acceptTerms = value ?? false),
                          title: const Text('ვეთანხმები წესებსა და პირობებს'),
                        ),
                        CheckboxListTile(
                          value: _acceptPrivacy,
                          onChanged: (value) =>
                              setState(() => _acceptPrivacy = value ?? false),
                          title: const Text(
                              'ვეთანხმები მონაცემების დამუშავების პირობებს'),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(Insets.l),
              child: Row(
                children: [
                  if (_step > 0)
                    IconButton(
                      tooltip: 'Back',
                      onPressed:
                          _busy ? null : () => setState(() => _step -= 1),
                      icon: const Icon(Icons.arrow_back_rounded),
                    ),
                  Expanded(
                    child: PrimaryButton(
                      label: _step == 3 ? 'გაგზავნა' : 'შემდეგი',
                      busy: _busy,
                      onPressed: _busy
                          ? null
                          : () async {
                              if (_step == 3) {
                                await _saveDraft(submit: true);
                              } else {
                                final saved = await _saveDraft();
                                if (saved && mounted) {
                                  setState(() => _step += 1);
                                }
                              }
                            },
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PersonalStep extends StatelessWidget {
  const _PersonalStep({
    required this.firstName,
    required this.lastName,
    required this.personalId,
    required this.phone,
    required this.email,
    required this.birthDate,
    required this.serviceZone,
    required this.driverType,
    required this.onDriverType,
  });

  final TextEditingController firstName;
  final TextEditingController lastName;
  final TextEditingController personalId;
  final TextEditingController phone;
  final TextEditingController email;
  final TextEditingController birthDate;
  final TextEditingController serviceZone;
  final String driverType;
  final ValueChanged<String> onDriverType;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        AppTextField(controller: firstName, label: 'სახელი'),
        const SizedBox(height: Insets.s),
        AppTextField(controller: lastName, label: 'გვარი'),
        const SizedBox(height: Insets.s),
        AppTextField(
          controller: personalId,
          label: 'პირადი ნომერი',
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
        ),
        const SizedBox(height: Insets.s),
        AppTextField(
          controller: phone,
          label: 'ტელეფონის ნომერი',
          keyboardType: TextInputType.phone,
        ),
        const SizedBox(height: Insets.s),
        AppTextField(
          controller: email,
          label: 'ელფოსტა',
          keyboardType: TextInputType.emailAddress,
        ),
        const SizedBox(height: Insets.s),
        AppTextField(
          controller: birthDate,
          label: 'დაბადების თარიღი',
          hint: 'YYYY-MM-DD',
        ),
        const SizedBox(height: Insets.s),
        AppTextField(controller: serviceZone, label: 'ქალაქი / ზონა'),
        const SizedBox(height: Insets.m),
        _Dropdown(
          label: 'მძღოლის ტიპი',
          value: driverType,
          onChanged: onDriverType,
          values: const {
            'moto': 'მოტო მძღოლი',
            'car': 'ავტომობილის მძღოლი',
            'courier': 'კურიერი / delivery',
          },
        ),
      ],
    );
  }
}

class _VehicleStep extends StatelessWidget {
  const _VehicleStep({
    required this.vehicleType,
    required this.onVehicleType,
    required this.brand,
    required this.model,
    required this.year,
    required this.color,
    required this.plate,
    required this.engine,
    required this.insurance,
    required this.inspection,
  });

  final String vehicleType;
  final ValueChanged<String> onVehicleType;
  final TextEditingController brand;
  final TextEditingController model;
  final TextEditingController year;
  final TextEditingController color;
  final TextEditingController plate;
  final TextEditingController engine;
  final TextEditingController insurance;
  final TextEditingController inspection;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _Dropdown(
          label: 'ტრანსპორტის ტიპი',
          value: vehicleType,
          onChanged: onVehicleType,
          values: const {
            'scooter_electric': 'ელექტრო სკუტერი',
            'scooter_petrol': 'ბენზინის სკუტერი',
            'moped': 'მოპედი',
            'bicycle_electric': 'ელექტრო ველოსიპედი',
            'car': 'ავტომობილი',
          },
        ),
        const SizedBox(height: Insets.s),
        AppTextField(controller: brand, label: 'ბრენდი'),
        const SizedBox(height: Insets.s),
        AppTextField(controller: model, label: 'მოდელი'),
        const SizedBox(height: Insets.s),
        AppTextField(
          controller: year,
          label: 'წელი',
          keyboardType: TextInputType.number,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
        ),
        const SizedBox(height: Insets.s),
        AppTextField(controller: color, label: 'ფერი'),
        const SizedBox(height: Insets.s),
        AppTextField(controller: plate, label: 'სახელმწიფო ნომერი'),
        const SizedBox(height: Insets.s),
        AppTextField(controller: engine, label: 'ძრავის მოცულობა'),
        const SizedBox(height: Insets.s),
        AppTextField(controller: insurance, label: 'დაზღვევა ვადა YYYY-MM-DD'),
        const SizedBox(height: Insets.s),
        AppTextField(
          controller: inspection,
          label: 'ტექდათვალიერება ვადა YYYY-MM-DD',
        ),
      ],
    );
  }
}

class _DocumentsStep extends StatelessWidget {
  const _DocumentsStep({
    required this.picked,
    required this.uploaded,
    required this.busy,
    required this.onPick,
  });

  final Map<String, File> picked;
  final Map<String, DriverApplicationDocument> uploaded;
  final bool busy;
  final Future<void> Function(String docType) onPick;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: _docs.entries
          .map(
            (entry) => Padding(
              padding: const EdgeInsets.only(bottom: Insets.s),
              child: _DocumentTile(
                label: entry.value,
                file: picked[entry.key],
                uploaded: uploaded[entry.key],
                busy: busy,
                onTap: () => onPick(entry.key),
              ),
            ),
          )
          .toList(),
    );
  }

  static const _docs = {
    'id_front': 'პირადობის მოწმობა - წინა მხარე',
    'id_back': 'პირადობის მოწმობა - უკანა მხარე',
    'license_front': 'მართვის მოწმობა - წინა მხარე',
    'license_back': 'მართვის მოწმობა - უკანა მხარე',
    'vehicle_registration': 'ტრანსპორტის სარეგისტრაციო დოკუმენტი',
    'vehicle_photo': 'ტრანსპორტის ფოტო',
    'selfie': 'მძღოლის სელფი',
    'insurance': 'დაზღვევის დოკუმენტი',
  };
}

class _DocumentTile extends StatelessWidget {
  const _DocumentTile({
    required this.label,
    required this.file,
    required this.uploaded,
    required this.busy,
    required this.onTap,
  });

  final String label;
  final File? file;
  final DriverApplicationDocument? uploaded;
  final bool busy;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(Radii.s),
        child: file == null && uploaded == null
            ? Container(
                width: 48,
                height: 48,
                color: AppColors.surfaceVariant,
                child: const Icon(Icons.image_rounded),
              )
            : file != null
                ? Image.file(file!, width: 48, height: 48, fit: BoxFit.cover)
                : Container(
                    width: 48,
                    height: 48,
                    color: AppColors.surfaceVariant,
                    child: const Icon(Icons.check_circle_rounded),
                  ),
      ),
      title: Text(label),
      subtitle: Text(_subtitle),
      trailing: TextButton(
        onPressed: busy ? null : onTap,
        child: Text(file == null && uploaded == null ? 'არჩევა' : 'შეცვლა'),
      ),
    );
  }

  String get _subtitle {
    if (file != null) return 'ატვირთულია';
    if (uploaded != null) return 'სერვერზე ატვირთულია';
    return 'ატვირთვა საჭიროა';
  }
}

class _Dropdown extends StatelessWidget {
  const _Dropdown({
    required this.label,
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String label;
  final String value;
  final Map<String, String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    // Keep `value` for the older Flutter toolchains accepted by this repo.
    return DropdownButtonFormField<String>(
      // ignore: deprecated_member_use
      value: value,
      decoration: InputDecoration(labelText: label),
      items: values.entries
          .map(
            (entry) => DropdownMenuItem(
              value: entry.key,
              child: Text(entry.value),
            ),
          )
          .toList(),
      onChanged: (value) {
        if (value != null) onChanged(value);
      },
    );
  }
}
