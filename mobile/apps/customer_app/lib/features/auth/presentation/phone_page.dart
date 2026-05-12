import 'package:flutter/material.dart';
import 'package:ui_kit/ui_kit.dart';

class PhonePage extends StatefulWidget {
  const PhonePage({super.key});

  @override
  State<PhonePage> createState() => _PhonePageState();
}

class _PhonePageState extends State<PhonePage> {
  final _controller = TextEditingController();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Sign in')),
      body: Padding(
        padding: const EdgeInsets.all(Insets.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: Insets.xxl),
            Text(
              'Enter your phone',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: Insets.l),
            AppTextField(
              controller: _controller,
              label: 'Phone',
              hint: '+995…',
              keyboardType: TextInputType.phone,
            ),
            const Spacer(),
            PrimaryButton(
              label: 'Send code',
              onPressed: () {},
            ),
          ],
        ),
      ),
    );
  }
}
